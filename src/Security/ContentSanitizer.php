<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Sanitizes user-generated content to prevent XSS attacks.
 *
 * This is a simple sanitizer that escapes HTML entities.
 * For more complex needs (allowing some HTML), consider using
 * a library like HTMLPurifier.
 */
class ContentSanitizer
{
    /**
     * Sanitize text content by escaping HTML entities.
     *
     * @param string|null $content The content to sanitize
     * @return string|null Sanitized content
     */
    public function sanitize(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        return htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitize content while preserving safe markdown-like formatting.
     * Escapes HTML but allows basic text formatting to be rendered later.
     *
     * @param string|null $content The content to sanitize
     * @return string|null Sanitized content
     */
    public function sanitizePreservingMarkdown(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        // Escape HTML entities
        $sanitized = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $sanitized;
    }

    /**
     * Sanitize an array of strings recursively.
     *
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function sanitizeArray(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $result[$key] = $this->sanitize($value);
            } elseif (is_array($value)) {
                $result[$key] = $this->sanitizeArray($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Strip all HTML tags from content.
     *
     * @param string|null $content The content to strip
     * @return string|null Content with HTML tags removed
     */
    public function stripHtml(?string $content): ?string
    {
        if ($content === null) {
            return null;
        }

        return strip_tags($content);
    }

    /**
     * Validate that content doesn't contain potential XSS vectors.
     *
     * @param string|null $content The content to validate
     * @return bool True if content is safe
     */
    public function isSafe(?string $content): bool
    {
        if ($content === null) {
            return true;
        }

        // Check for common XSS patterns
        $dangerousPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',  // onclick, onerror, etc.
            '/data:/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/<link/i',
            '/<style/i',
            '/expression\s*\(/i',
            '/url\s*\(/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }
}
