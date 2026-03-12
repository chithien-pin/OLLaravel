package services

import (
	"context"
	"fmt"

	"github.com/redis/go-redis/v9"
	"github.com/sirupsen/logrus"

	"orange-webhook-service/config"
)

// RedisService handles Redis pub/sub operations
type RedisService struct {
	client *redis.Client
}

// NewRedisService creates a new Redis service and verifies the connection
func NewRedisService(cfg *config.Config) (*RedisService, error) {
	addr := fmt.Sprintf("%s:%s", cfg.Redis.Host, cfg.Redis.Port)

	client := redis.NewClient(&redis.Options{
		Addr: addr,
	})

	// Verify connection
	ctx := context.Background()
	if err := client.Ping(ctx).Err(); err != nil {
		return nil, fmt.Errorf("failed to connect to Redis at %s: %w", addr, err)
	}

	logrus.WithField("addr", addr).Info("Redis connection established successfully")

	return &RedisService{client: client}, nil
}

// Subscribe subscribes to a Redis pub/sub channel
func (rs *RedisService) Subscribe(ctx context.Context, channel string) *redis.PubSub {
	return rs.client.Subscribe(ctx, channel)
}

// Close closes the Redis connection
func (rs *RedisService) Close() error {
	return rs.client.Close()
}
