package main

import (
	"context"
	"encoding/json"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/sirupsen/logrus"

	"orange-webhook-service/config"
	"orange-webhook-service/handlers"
	"orange-webhook-service/services"
)

// redisEvent represents the JSON structure received from Redis pub/sub
type redisEvent struct {
	EventType   string          `json:"event_type"`
	TargetUsers []int           `json:"target_users"`
	Data        json.RawMessage `json:"data"`
	Timestamp   int64           `json:"timestamp"`
}

// clientMessage is the message forwarded to WebSocket clients (without target_users)
type clientMessage struct {
	EventType string          `json:"event_type"`
	Data      json.RawMessage `json:"data"`
	Timestamp int64           `json:"timestamp"`
}

func main() {
	// Initialize logger
	logrus.SetFormatter(&logrus.JSONFormatter{})
	logrus.SetLevel(logrus.InfoLevel)

	// Load configuration
	cfg := config.Load()
	if err := cfg.Validate(); err != nil {
		logrus.WithError(err).Fatal("Invalid configuration")
	}

	logrus.Info("Starting Orange Webhook Service")

	// Initialize database service
	dbService, err := services.NewDatabaseService(cfg)
	if err != nil {
		logrus.WithError(err).Fatal("Failed to initialize database service")
	}
	defer dbService.Close()

	// Initialize Laravel service
	laravelService := services.NewLaravelService(cfg)

	// Initialize Redis service (non-fatal: WS disabled if Redis unavailable)
	var hub *services.Hub
	var cancelRedis context.CancelFunc
	redisService, err := services.NewRedisService(cfg)
	if err != nil {
		logrus.WithError(err).Warn("Redis unavailable — WebSocket disabled, webhooks still active")
	} else {
		defer redisService.Close()

		// Initialize WebSocket hub
		hub = services.NewHub()
		go hub.Run()

		// Start Redis subscriber
		var ctx context.Context
		ctx, cancelRedis = context.WithCancel(context.Background())
		go subscribeRedisEvents(ctx, redisService, hub)
	}

	// Initialize Stripe webhook handler
	stripeHandler := handlers.NewStripeWebhookHandler(cfg, dbService, laravelService)

	// Setup Gin router
	router := setupRouter(stripeHandler, dbService, laravelService, hub)

	// Start HTTP server
	srv := &http.Server{
		Addr:    ":" + cfg.Port,
		Handler: router,
	}

	// Start server in a goroutine
	go func() {
		logrus.WithField("port", cfg.Port).Info("Starting HTTP server")
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			logrus.WithError(err).Fatal("Failed to start server")
		}
	}()

	// Wait for interrupt signal to gracefully shutdown the server
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	logrus.Info("Shutting down server...")

	// Cancel Redis subscriber (if started)
	if cancelRedis != nil {
		cancelRedis()
	}

	// Give outstanding requests 30 seconds to complete
	shutdownCtx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	if err := srv.Shutdown(shutdownCtx); err != nil {
		logrus.WithError(err).Error("Server forced to shutdown")
	}

	logrus.Info("Server exited")
}

// subscribeRedisEvents listens on the Redis pub/sub channel and forwards
// events to the appropriate WebSocket clients via the hub.
func subscribeRedisEvents(ctx context.Context, redisService *services.RedisService, hub *services.Hub) {
	const channel = "orange:ws:events"

	pubsub := redisService.Subscribe(ctx, channel)
	defer pubsub.Close()

	logrus.WithField("channel", channel).Info("Subscribed to Redis channel")

	ch := pubsub.Channel()
	for {
		select {
		case <-ctx.Done():
			logrus.Info("Redis subscriber stopped")
			return
		case msg, ok := <-ch:
			if !ok {
				logrus.Warn("Redis pub/sub channel closed")
				return
			}

			var event redisEvent
			if err := json.Unmarshal([]byte(msg.Payload), &event); err != nil {
				logrus.WithError(err).WithField("payload", msg.Payload).Error("Failed to parse Redis event")
				continue
			}

			logrus.WithFields(logrus.Fields{
				"event_type":   event.EventType,
				"target_users": event.TargetUsers,
			}).Debug("Received Redis event")

			// Build the message to send to clients (without target_users)
			outgoing := clientMessage{
				EventType: event.EventType,
				Data:      event.Data,
				Timestamp: event.Timestamp,
			}

			outgoingBytes, err := json.Marshal(outgoing)
			if err != nil {
				logrus.WithError(err).Error("Failed to marshal client message")
				continue
			}

			// Send to each target user
			for _, userID := range event.TargetUsers {
				hub.SendToUser(userID, outgoingBytes)
			}
		}
	}
}

// setupRouter configures the Gin router with all routes
func setupRouter(stripeHandler *handlers.StripeWebhookHandler, dbService *services.DatabaseService, laravelService *services.LaravelService, hub *services.Hub) *gin.Engine {
	// Set Gin mode
	if os.Getenv("GIN_MODE") == "release" {
		gin.SetMode(gin.ReleaseMode)
	}

	router := gin.New()

	// Add middleware
	router.Use(gin.Logger())
	router.Use(gin.Recovery())

	// Health check endpoints
	router.GET("/health", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{
			"status":    "healthy",
			"timestamp": time.Now().UTC(),
			"service":   "orange-webhook-service",
		})
	})

	router.GET("/ready", func(c *gin.Context) {
		// Check Laravel connectivity (optional - don't fail if Laravel is down)
		laravelHealthy := true
		if err := laravelService.HealthCheck(); err != nil {
			laravelHealthy = false
			logrus.WithError(err).Warn("Laravel health check failed")
		}

		c.JSON(http.StatusOK, gin.H{
			"status":    "ready",
			"timestamp": time.Now().UTC(),
			"database":  "connected",
			"laravel":   map[string]interface{}{"healthy": laravelHealthy},
		})
	})

	// WebSocket endpoint (only if Redis/Hub is available)
	if hub != nil {
		router.GET("/ws", handlers.HandleWebSocket(hub))
	}

	// Webhook endpoints
	webhookGroup := router.Group("/webhooks")
	{
		webhookGroup.POST("/stripe", stripeHandler.HandleWebhook)
	}

	// Status endpoint for monitoring
	router.GET("/status", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{
			"service":   "orange-webhook-service",
			"version":   "1.0.0",
			"status":    "running",
			"timestamp": time.Now().UTC(),
			"uptime":    time.Since(startTime).String(),
		})
	})

	return router
}

var startTime = time.Now()
