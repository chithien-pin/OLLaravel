package services

import (
	"database/sql"
	"fmt"
	"time"

	_ "github.com/go-sql-driver/mysql"
	"github.com/sirupsen/logrus"

	"orange-webhook-service/config"
)

// DatabaseService handles database operations
type DatabaseService struct {
	db *sql.DB
}

// NewDatabaseService creates a new database service
func NewDatabaseService(cfg *config.Config) (*DatabaseService, error) {
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?charset=utf8mb4&parseTime=True&loc=Local",
		cfg.Database.User,
		cfg.Database.Password,
		cfg.Database.Host,
		cfg.Database.Port,
		cfg.Database.Name,
	)

	db, err := sql.Open("mysql", dsn)
	if err != nil {
		return nil, fmt.Errorf("failed to open database: %w", err)
	}

	// Configure connection pool
	db.SetMaxOpenConns(25)
	db.SetMaxIdleConns(10)
	db.SetConnMaxLifetime(5 * time.Minute)

	// Test connection
	if err := db.Ping(); err != nil {
		return nil, fmt.Errorf("failed to ping database: %w", err)
	}

	logrus.Info("Database connection established successfully")
	return &DatabaseService{db: db}, nil
}

// UpdateSubscriptionStatus updates subscription status from pending_webhook to active
func (ds *DatabaseService) UpdateSubscriptionStatus(paymentIntentID string) error {
	query := `
		UPDATE subscriptions 
		SET status = 'active', webhook_confirmed_at = NOW()
		WHERE stripe_subscription_id = ? AND status = 'pending_webhook'
	`

	result, err := ds.db.Exec(query, paymentIntentID)
	if err != nil {
		return fmt.Errorf("failed to update subscription status: %w", err)
	}

	rowsAffected, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("failed to get rows affected: %w", err)
	}

	if rowsAffected == 0 {
		return fmt.Errorf("no subscription found with payment_intent_id %s in pending_webhook status", paymentIntentID)
	}

	logrus.WithFields(logrus.Fields{
		"payment_intent_id": paymentIntentID,
		"rows_affected":     rowsAffected,
	}).Info("Subscription status updated successfully")

	return nil
}

// LogWebhookEvent logs webhook events for audit trail
func (ds *DatabaseService) LogWebhookEvent(eventID, eventType, paymentIntentID, status, errorMessage string) error {
	query := `
		INSERT INTO webhook_logs (
			stripe_event_id, event_type, payment_intent_id, status, 
			error_message, processed_at, created_at, updated_at
		) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW())
		ON DUPLICATE KEY UPDATE
			status = VALUES(status),
			error_message = VALUES(error_message),
			processed_at = VALUES(processed_at),
			updated_at = NOW()
	`

	_, err := ds.db.Exec(query, eventID, eventType, paymentIntentID, status, errorMessage)
	if err != nil {
		return fmt.Errorf("failed to log webhook event: %w", err)
	}

	return nil
}

// GetSubscriptionByPaymentIntent retrieves subscription details by payment intent ID
func (ds *DatabaseService) GetSubscriptionByPaymentIntent(paymentIntentID string) (*Subscription, error) {
	query := `
		SELECT id, user_id, stripe_subscription_id, plan_type, status, 
		       amount, currency, starts_at, ends_at, payment_intent_verified
		FROM subscriptions 
		WHERE stripe_subscription_id = ?
	`

	var sub Subscription
	err := ds.db.QueryRow(query, paymentIntentID).Scan(
		&sub.ID,
		&sub.UserID,
		&sub.StripeSubscriptionID,
		&sub.PlanType,
		&sub.Status,
		&sub.Amount,
		&sub.Currency,
		&sub.StartsAt,
		&sub.EndsAt,
		&sub.PaymentIntentVerified,
	)

	if err != nil {
		if err == sql.ErrNoRows {
			return nil, fmt.Errorf("subscription not found for payment_intent_id: %s", paymentIntentID)
		}
		return nil, fmt.Errorf("failed to get subscription: %w", err)
	}

	return &sub, nil
}

// Close closes the database connection
func (ds *DatabaseService) Close() error {
	return ds.db.Close()
}

// Subscription represents a subscription record
type Subscription struct {
	ID                     int       `json:"id"`
	UserID                 int       `json:"user_id"`
	StripeSubscriptionID   string    `json:"stripe_subscription_id"`
	PlanType               string    `json:"plan_type"`
	Status                 string    `json:"status"`
	Amount                 float64   `json:"amount"`
	Currency               string    `json:"currency"`
	StartsAt               time.Time `json:"starts_at"`
	EndsAt                 time.Time `json:"ends_at"`
	PaymentIntentVerified  bool      `json:"payment_intent_verified"`
}