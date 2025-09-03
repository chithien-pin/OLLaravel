package config

import (
	"log"
	"os"

	"github.com/joho/godotenv"
)

// Config holds application configuration
type Config struct {
	Port               string
	StripeWebhookSecret string
	Database           DatabaseConfig
	Laravel            LaravelConfig
}

// DatabaseConfig holds database configuration
type DatabaseConfig struct {
	Host     string
	Port     string
	Name     string
	User     string
	Password string
}

// LaravelConfig holds Laravel API configuration
type LaravelConfig struct {
	BaseURL string
	APIKey  string
}

// Load loads configuration from environment variables
func Load() *Config {
	// Load .env file if it exists
	_ = godotenv.Load()

	return &Config{
		Port:               getEnvOrDefault("PORT", "8080"),
		StripeWebhookSecret: getEnvOrDefault("STRIPE_WEBHOOK_SECRET", ""),
		Database: DatabaseConfig{
			Host:     getEnvOrDefault("DB_HOST", "mysql"),
			Port:     getEnvOrDefault("DB_PORT", "3306"),
			Name:     getEnvOrDefault("DB_NAME", "orange_db"),
			User:     getEnvOrDefault("DB_USER", "orange_user"),
			Password: getEnvOrDefault("DB_PASS", "orange_pass"),
		},
		Laravel: LaravelConfig{
			BaseURL: getEnvOrDefault("LARAVEL_API_URL", "http://backend:9000"),
			APIKey:  getEnvOrDefault("LARAVEL_API_KEY", "123"),
		},
	}
}

// Validate checks if required configuration is present
func (c *Config) Validate() error {
	if c.StripeWebhookSecret == "" {
		log.Fatal("STRIPE_WEBHOOK_SECRET is required")
	}
	return nil
}

func getEnvOrDefault(key, defaultValue string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return defaultValue
}