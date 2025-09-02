package main

import (
	"context"
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

	// Initialize Stripe webhook handler
	stripeHandler := handlers.NewStripeWebhookHandler(cfg, dbService, laravelService)

	// Setup Gin router
	router := setupRouter(stripeHandler, dbService, laravelService)

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

	// Give outstanding requests 30 seconds to complete
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()

	if err := srv.Shutdown(ctx); err != nil {
		logrus.WithError(err).Error("Server forced to shutdown")
	}

	logrus.Info("Server exited")
}

// setupRouter configures the Gin router with all routes
func setupRouter(stripeHandler *handlers.StripeWebhookHandler, dbService *services.DatabaseService, laravelService *services.LaravelService) *gin.Engine {
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
		// For now, assume database is ready if service started successfully
		// In production, you might want to add a health check method to DatabaseService

		// Check Laravel connectivity (optional - don't fail if Laravel is down)
		laravelHealthy := true
		if err := laravelService.HealthCheck(); err != nil {
			laravelHealthy = false
			logrus.WithError(err).Warn("Laravel health check failed")
		}

		c.JSON(http.StatusOK, gin.H{
			"status":          "ready",
			"timestamp":       time.Now().UTC(),
			"database":        "connected",
			"laravel":         map[string]interface{}{"healthy": laravelHealthy},
		})
	})

	// Webhook endpoints
	webhookGroup := router.Group("/webhooks")
	{
		webhookGroup.POST("/stripe", stripeHandler.HandleWebhook)
	}

	// Status endpoint for monitoring
	router.GET("/status", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{
			"service":     "orange-webhook-service",
			"version":     "1.0.0",
			"status":      "running",
			"timestamp":   time.Now().UTC(),
			"uptime":      time.Since(startTime).String(),
		})
	})

	return router
}

var startTime = time.Now()