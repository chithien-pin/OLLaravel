<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class TranslationService
{
    /**
     * Supported locales matching Flutter frontend
     */
    const SUPPORTED_LOCALES = [
        'en', 'vi', 'ar', 'da', 'de', 'el', 'es', 'fr', 'hi', 'id',
        'it', 'ja', 'ko', 'nb', 'nl', 'pl', 'pt', 'ru', 'th', 'tr', 'zh'
    ];

    /**
     * Default fallback locale
     */
    const DEFAULT_LOCALE = 'en';

    /**
     * Cache for loaded translations
     */
    private static $translationsCache = [];

    /**
     * Translate a message for a specific user based on their language preference
     *
     * @param object|null $user User model with 'language' field
     * @param string $key Translation key (e.g., 'notification.follow')
     * @param array $replace Placeholder replacements (e.g., ['name' => 'John'])
     * @return string Translated message
     */
    public static function forUser($user, string $key, array $replace = []): string
    {
        $locale = self::DEFAULT_LOCALE;

        if ($user && !empty($user->language)) {
            $locale = self::getSupportedLocale($user->language);
        }

        return self::translate($key, $locale, $replace);
    }

    /**
     * Translate a message with a specific locale
     *
     * @param string $key Translation key
     * @param string $locale Locale code (e.g., 'en', 'vi')
     * @param array $replace Placeholder replacements
     * @return string Translated message
     */
    public static function translate(string $key, string $locale, array $replace = []): string
    {
        $locale = self::getSupportedLocale($locale);
        $translations = self::loadTranslations($locale);

        // Get translation or fallback to English
        $message = $translations[$key] ?? null;

        // Fallback to English if key not found in requested locale
        if ($message === null && $locale !== self::DEFAULT_LOCALE) {
            $englishTranslations = self::loadTranslations(self::DEFAULT_LOCALE);
            $message = $englishTranslations[$key] ?? $key;
        }

        // If still not found, return the key itself
        if ($message === null) {
            Log::warning("Translation key not found: {$key}");
            return $key;
        }

        // Replace placeholders (:name, :comment, etc.)
        return self::replacePlaceholders($message, $replace);
    }

    /**
     * Get a supported locale, falling back to default if not supported
     *
     * @param string|null $locale Locale to validate
     * @return string Valid locale code
     */
    public static function getSupportedLocale(?string $locale): string
    {
        if (empty($locale)) {
            return self::DEFAULT_LOCALE;
        }

        // Normalize locale (lowercase, handle variations like 'en-US' -> 'en')
        $locale = strtolower(trim($locale));

        // Handle locale variations (e.g., 'en-US' -> 'en', 'zh-CN' -> 'zh')
        if (strpos($locale, '-') !== false) {
            $locale = explode('-', $locale)[0];
        }
        if (strpos($locale, '_') !== false) {
            $locale = explode('_', $locale)[0];
        }

        return in_array($locale, self::SUPPORTED_LOCALES) ? $locale : self::DEFAULT_LOCALE;
    }

    /**
     * Load translations from JSON file for a specific locale
     *
     * @param string $locale Locale code
     * @return array Translations array
     */
    private static function loadTranslations(string $locale): array
    {
        // Return from cache if already loaded
        if (isset(self::$translationsCache[$locale])) {
            return self::$translationsCache[$locale];
        }

        $path = resource_path("lang/{$locale}.json");

        if (!File::exists($path)) {
            Log::warning("Translation file not found: {$path}");

            // Try to load English as fallback
            if ($locale !== self::DEFAULT_LOCALE) {
                return self::loadTranslations(self::DEFAULT_LOCALE);
            }

            return [];
        }

        $content = File::get($path);
        $translations = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("Invalid JSON in translation file: {$path}");
            return [];
        }

        // Cache the translations
        self::$translationsCache[$locale] = $translations;

        return $translations;
    }

    /**
     * Replace placeholders in message with actual values
     * Supports Laravel-style :placeholder syntax
     *
     * @param string $message Message with placeholders
     * @param array $replace Key-value pairs for replacement
     * @return string Message with replaced placeholders
     */
    private static function replacePlaceholders(string $message, array $replace): string
    {
        if (empty($replace)) {
            return $message;
        }

        foreach ($replace as $key => $value) {
            // Support both :key and :Key (case variations)
            $message = str_replace(
                [':' . $key, ':' . ucfirst($key), ':' . strtoupper($key)],
                [$value, ucfirst($value), strtoupper($value)],
                $message
            );
        }

        return $message;
    }

    /**
     * Clear the translations cache (useful for testing or after updates)
     */
    public static function clearCache(): void
    {
        self::$translationsCache = [];
    }

    /**
     * Check if a locale is supported
     *
     * @param string $locale Locale to check
     * @return bool
     */
    public static function isSupported(string $locale): bool
    {
        $normalized = self::getSupportedLocale($locale);
        return $normalized !== self::DEFAULT_LOCALE || strtolower($locale) === self::DEFAULT_LOCALE;
    }

    /**
     * Get all supported locales
     *
     * @return array
     */
    public static function getSupportedLocales(): array
    {
        return self::SUPPORTED_LOCALES;
    }
}
