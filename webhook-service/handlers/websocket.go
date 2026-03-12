package handlers

import (
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/gorilla/websocket"
	"github.com/sirupsen/logrus"

	"orange-webhook-service/services"
)

const wsAPIKey = "123"

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
	CheckOrigin: func(r *http.Request) bool {
		return true // Allow all origins for mobile clients
	},
}

// authMessage is the first message a client sends to authenticate
type authMessage struct {
	UserID int    `json:"user_id"`
	APIKey string `json:"apikey"`
}

// HandleWebSocket returns a Gin handler that upgrades HTTP connections to WebSocket
// and registers authenticated clients with the hub.
func HandleWebSocket(hub *services.Hub) gin.HandlerFunc {
	return func(c *gin.Context) {
		conn, err := upgrader.Upgrade(c.Writer, c.Request, nil)
		if err != nil {
			logrus.WithError(err).Error("Failed to upgrade to WebSocket")
			return
		}

		// Read authentication message
		var auth authMessage
		if err := conn.ReadJSON(&auth); err != nil {
			logrus.WithError(err).Warn("Failed to read auth message")
			conn.WriteJSON(gin.H{"error": "invalid auth message"})
			conn.Close()
			return
		}

		// Validate API key
		if auth.APIKey != wsAPIKey {
			logrus.WithField("user_id", auth.UserID).Warn("Invalid API key in WebSocket auth")
			conn.WriteJSON(gin.H{"error": "unauthorized"})
			conn.Close()
			return
		}

		if auth.UserID <= 0 {
			logrus.Warn("Invalid user_id in WebSocket auth")
			conn.WriteJSON(gin.H{"error": "invalid user_id"})
			conn.Close()
			return
		}

		logrus.WithField("user_id", auth.UserID).Info("WebSocket client authenticated")

		// Create and register client
		client := services.NewClient(hub, conn, auth.UserID)
		hub.Register(client)

		// Start read and write pumps
		go client.WritePump()
		go client.ReadPump()
	}
}
