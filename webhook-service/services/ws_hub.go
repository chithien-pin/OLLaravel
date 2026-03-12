package services

import (
	"sync"

	"github.com/sirupsen/logrus"
)

// Hub manages all active WebSocket client connections
type Hub struct {
	// clients maps user IDs to their active connections
	clients map[int][]*Client

	// register channel for new client connections
	register chan *Client

	// unregister channel for disconnected clients
	unregister chan *Client

	mu sync.RWMutex
}

// NewHub creates a new Hub instance
func NewHub() *Hub {
	return &Hub{
		clients:    make(map[int][]*Client),
		register:   make(chan *Client),
		unregister: make(chan *Client),
	}
}

// Run starts the hub's main event loop. Should be called as a goroutine.
func (h *Hub) Run() {
	for {
		select {
		case client := <-h.register:
			h.mu.Lock()
			h.clients[client.UserID] = append(h.clients[client.UserID], client)
			h.mu.Unlock()

			logrus.WithFields(logrus.Fields{
				"user_id":     client.UserID,
				"connections": len(h.clients[client.UserID]),
			}).Info("Client registered")

		case client := <-h.unregister:
			h.mu.Lock()
			connections := h.clients[client.UserID]
			found := false
			for i, c := range connections {
				if c == client {
					h.clients[client.UserID] = append(connections[:i], connections[i+1:]...)
					found = true
					break
				}
			}
			if len(h.clients[client.UserID]) == 0 {
				delete(h.clients, client.UserID)
			}
			// Only close send channel once (prevent double-close panic)
			if found {
				close(client.send)
			}
			h.mu.Unlock()

			logrus.WithFields(logrus.Fields{
				"user_id": client.UserID,
			}).Info("Client unregistered")
		}
	}
}

// Register adds a client to the hub
func (h *Hub) Register(client *Client) {
	h.register <- client
}

// SendToUser sends a message to all connections belonging to a user
func (h *Hub) SendToUser(userID int, message []byte) {
	h.mu.RLock()
	connections, ok := h.clients[userID]
	h.mu.RUnlock()

	if !ok {
		return
	}

	for _, client := range connections {
		select {
		case client.send <- message:
		default:
			// Client send buffer is full; schedule unregister
			go func(c *Client) {
				h.unregister <- c
			}(client)
		}
	}
}
