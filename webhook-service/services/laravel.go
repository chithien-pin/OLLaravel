package services

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
	"time"

	"github.com/sirupsen/logrus"

	"orange-webhook-service/config"
)

// LaravelService handles communication with Laravel API
type LaravelService struct {
	config     *config.Config
	httpClient *http.Client
}

// NewLaravelService creates a new Laravel service
func NewLaravelService(cfg *config.Config) *LaravelService {
	return &LaravelService{
		config: cfg,
		httpClient: &http.Client{
			Timeout: 30 * time.Second,
		},
	}
}

// SubscriptionConfirmationRequest represents the request payload for Laravel
type SubscriptionConfirmationRequest struct {
	UserID           int    `json:"user_id"`
	SubscriptionID   int    `json:"subscription_id"`
	PaymentIntentID  string `json:"payment_intent_id"`
	ConfirmedBy      string `json:"confirmed_by"`
	ConfirmedAt      string `json:"confirmed_at"`
}

// NotifySubscriptionConfirmed notifies Laravel about confirmed subscription
func (ls *LaravelService) NotifySubscriptionConfirmed(userID, subscriptionID int, paymentIntentID string) error {
	payload := SubscriptionConfirmationRequest{
		UserID:          userID,
		SubscriptionID:  subscriptionID,
		PaymentIntentID: paymentIntentID,
		ConfirmedBy:     "webhook",
		ConfirmedAt:     time.Now().Format(time.RFC3339),
	}

	jsonPayload, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("failed to marshal payload: %w", err)
	}

	// Create request
	url := fmt.Sprintf("%s/api/webhook/subscription-confirmed", ls.config.Laravel.BaseURL)
	req, err := http.NewRequest("POST", url, bytes.NewBuffer(jsonPayload))
	if err != nil {
		return fmt.Errorf("failed to create request: %w", err)
	}

	// Set headers
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("apikey", ls.config.Laravel.APIKey)
	req.Header.Set("User-Agent", "Orange-Webhook-Service/1.0")

	logrus.WithFields(logrus.Fields{
		"url":               url,
		"user_id":           userID,
		"subscription_id":   subscriptionID,
		"payment_intent_id": paymentIntentID,
	}).Info("Sending subscription confirmation to Laravel")

	// Send request
	resp, err := ls.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("failed to send request: %w", err)
	}
	defer resp.Body.Close()

	// Check response status
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return fmt.Errorf("Laravel API returned status %d", resp.StatusCode)
	}

	logrus.WithFields(logrus.Fields{
		"status_code":       resp.StatusCode,
		"user_id":           userID,
		"subscription_id":   subscriptionID,
		"payment_intent_id": paymentIntentID,
	}).Info("Laravel notification sent successfully")

	return nil
}

// HealthCheck checks if Laravel API is accessible
func (ls *LaravelService) HealthCheck() error {
	url := fmt.Sprintf("%s/api/health", ls.config.Laravel.BaseURL)
	req, err := http.NewRequest("GET", url, nil)
	if err != nil {
		return fmt.Errorf("failed to create health check request: %w", err)
	}

	req.Header.Set("apikey", ls.config.Laravel.APIKey)

	resp, err := ls.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("failed to send health check request: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("Laravel health check failed with status %d", resp.StatusCode)
	}

	return nil
}