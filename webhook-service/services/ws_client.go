package services

import (
	"time"

	"github.com/gorilla/websocket"
	"github.com/sirupsen/logrus"
)

const (
	// writeWait is the time allowed to write a message to the peer
	writeWait = 10 * time.Second

	// pongWait is the time allowed to read the next pong message from the peer
	pongWait = 60 * time.Second

	// pingPeriod sends pings to peer with this period. Must be less than pongWait.
	pingPeriod = 30 * time.Second

	// maxMessageSize is the maximum message size allowed from peer
	maxMessageSize = 512

	// sendBufferSize is the size of the client's outgoing message buffer
	sendBufferSize = 256
)

// Client represents a single WebSocket connection
type Client struct {
	hub    *Hub
	conn   *websocket.Conn
	UserID int
	send   chan []byte
}

// NewClient creates a new Client instance
func NewClient(hub *Hub, conn *websocket.Conn, userID int) *Client {
	return &Client{
		hub:    hub,
		conn:   conn,
		UserID: userID,
		send:   make(chan []byte, sendBufferSize),
	}
}

// ReadPump pumps messages from the WebSocket connection to the hub.
// It handles connection lifecycle (close, ping/pong) and discards incoming
// messages since this is a server-push-only design.
func (c *Client) ReadPump() {
	defer func() {
		c.hub.unregister <- c
		c.conn.Close()
	}()

	c.conn.SetReadLimit(maxMessageSize)
	c.conn.SetReadDeadline(time.Now().Add(pongWait))
	c.conn.SetPongHandler(func(string) error {
		c.conn.SetReadDeadline(time.Now().Add(pongWait))
		return nil
	})

	for {
		_, _, err := c.conn.ReadMessage()
		if err != nil {
			if websocket.IsUnexpectedCloseError(err, websocket.CloseGoingAway, websocket.CloseNormalClosure) {
				logrus.WithError(err).WithField("user_id", c.UserID).Warn("Unexpected WebSocket close")
			}
			break
		}
		// Incoming messages from client are ignored (server-push only)
	}
}

// WritePump pumps messages from the hub to the WebSocket connection.
// It sends queued messages and periodic pings to keep the connection alive.
func (c *Client) WritePump() {
	ticker := time.NewTicker(pingPeriod)
	defer func() {
		ticker.Stop()
		c.conn.Close()
	}()

	for {
		select {
		case message, ok := <-c.send:
			c.conn.SetWriteDeadline(time.Now().Add(writeWait))
			if !ok {
				// Hub closed the channel
				c.conn.WriteMessage(websocket.CloseMessage, []byte{})
				return
			}

			if err := c.conn.WriteMessage(websocket.TextMessage, message); err != nil {
				logrus.WithError(err).WithField("user_id", c.UserID).Warn("Failed to write WebSocket message")
				return
			}

		case <-ticker.C:
			c.conn.SetWriteDeadline(time.Now().Add(writeWait))
			if err := c.conn.WriteMessage(websocket.PingMessage, nil); err != nil {
				return
			}
		}
	}
}
