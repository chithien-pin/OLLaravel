package handlers

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"

	"github.com/gin-gonic/gin"
	"github.com/sirupsen/logrus"
	"github.com/stripe/stripe-go/v76"
	"github.com/stripe/stripe-go/v76/webhook"

	"orange-webhook-service/config"
	"orange-webhook-service/services"
)

// StripeWebhookHandler handles Stripe webhook events
type StripeWebhookHandler struct {
	config *config.Config
	db     *services.DatabaseService
	laravel *services.LaravelService
}

// NewStripeWebhookHandler creates a new Stripe webhook handler
func NewStripeWebhookHandler(cfg *config.Config, db *services.DatabaseService, laravel *services.LaravelService) *StripeWebhookHandler {
	return &StripeWebhookHandler{
		config:  cfg,
		db:      db,
		laravel: laravel,
	}
}

// HandleWebhook processes incoming Stripe webhook events
func (h *StripeWebhookHandler) HandleWebhook(c *gin.Context) {
	payload, err := io.ReadAll(c.Request.Body)
	if err != nil {
		logrus.WithError(err).Error("Failed to read request body")
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid request body"})
		return
	}

	// Debug signature
	signature := c.GetHeader("Stripe-Signature")
	logrus.WithFields(logrus.Fields{
		"signature": signature,
		"secret_prefix": h.config.StripeWebhookSecret[:10] + "...",
	}).Info("Debug webhook signature")
	
	// Verify webhook signature - allow API version mismatch
	event, err := webhook.ConstructEventWithOptions(payload, signature, h.config.StripeWebhookSecret, webhook.ConstructEventOptions{
		IgnoreAPIVersionMismatch: true,
	})
	if err != nil {
		logrus.WithError(err).Error("Webhook signature verification failed")
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid signature"})
		return
	}

	logrus.WithFields(logrus.Fields{
		"event_id":   event.ID,
		"event_type": event.Type,
	}).Info("Webhook event received")

	// Process the event
	switch event.Type {
	case "payment_intent.succeeded":
		err = h.handlePaymentIntentSucceeded(&event)
	default:
		logrus.WithField("event_type", event.Type).Info("Unhandled event type")
		c.JSON(http.StatusOK, gin.H{"message": "Event type not handled"})
		return
	}

	if err != nil {
		logrus.WithError(err).Error("Failed to process webhook event")
		// Log the error in database for audit
		_ = h.db.LogWebhookEvent(event.ID, string(event.Type), "", "error", err.Error())
		c.JSON(http.StatusInternalServerError, gin.H{"error": "Failed to process event"})
		return
	}

	c.JSON(http.StatusOK, gin.H{"message": "Event processed successfully"})
}

// handlePaymentIntentSucceeded processes payment_intent.succeeded events
func (h *StripeWebhookHandler) handlePaymentIntentSucceeded(event *stripe.Event) error {
	var paymentIntent struct {
		ID       string `json:"id"`
		Status   string `json:"status"`
		Amount   int64  `json:"amount"`
		Currency string `json:"currency"`
		Metadata map[string]string `json:"metadata"`
	}

	if err := json.Unmarshal(event.Data.Raw, &paymentIntent); err != nil {
		return fmt.Errorf("failed to parse payment intent: %w", err)
	}

	logrus.WithFields(logrus.Fields{
		"payment_intent_id": paymentIntent.ID,
		"status":           paymentIntent.Status,
		"amount":           paymentIntent.Amount,
		"currency":         paymentIntent.Currency,
	}).Info("Processing payment intent succeeded event")

	// Check if we have this payment intent in our database with retry logic
	subscription, err := h.db.GetSubscriptionByPaymentIntent(paymentIntent.ID)
	if err != nil {
		logrus.WithError(err).WithField("payment_intent_id", paymentIntent.ID).Warn("Subscription not found, will retry")
		
		// Return error to trigger Stripe's automatic retry mechanism
		// Stripe will retry this webhook multiple times over several hours
		_ = h.db.LogWebhookEvent(event.ID, string(event.Type), paymentIntent.ID, "retry", "Subscription not found, triggering retry")
		return fmt.Errorf("subscription not found for payment_intent_id: %s, will retry", paymentIntent.ID)
	}

	// Check if subscription is in pending_webhook status
	if subscription.Status != "pending_webhook" {
		logrus.WithFields(logrus.Fields{
			"payment_intent_id": paymentIntent.ID,
			"current_status":   subscription.Status,
		}).Warn("Subscription is not in pending_webhook status")
		_ = h.db.LogWebhookEvent(event.ID, string(event.Type), paymentIntent.ID, "skipped", "Subscription not in pending_webhook status")
		return nil
	}

	// Update subscription status to active
	if err := h.db.UpdateSubscriptionStatus(paymentIntent.ID); err != nil {
		logErr := h.db.LogWebhookEvent(event.ID, string(event.Type), paymentIntent.ID, "error", err.Error())
		if logErr != nil {
			logrus.WithError(logErr).Error("Failed to log webhook error")
		}
		return fmt.Errorf("failed to update subscription status: %w", err)
	}

	// Notify Laravel about the confirmed subscription
	if err := h.laravel.NotifySubscriptionConfirmed(subscription.UserID, subscription.ID, paymentIntent.ID); err != nil {
		logrus.WithError(err).Warn("Failed to notify Laravel about subscription confirmation")
		// Don't fail the webhook as the subscription was already updated
	}

	// Log successful processing
	if err := h.db.LogWebhookEvent(event.ID, string(event.Type), paymentIntent.ID, "success", ""); err != nil {
		logrus.WithError(err).Error("Failed to log successful webhook processing")
	}

	logrus.WithFields(logrus.Fields{
		"payment_intent_id": paymentIntent.ID,
		"subscription_id":  subscription.ID,
		"user_id":          subscription.UserID,
	}).Info("Subscription confirmed successfully via webhook")

	return nil
}