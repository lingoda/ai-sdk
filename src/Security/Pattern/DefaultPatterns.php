<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Security\Pattern;

final readonly class DefaultPatterns
{
    /**
     * @param array<string, string> $patterns
     */
    public function __construct(
        private array $patterns = []
    ) {}

    /**
     * @return array<string, string> Pattern => Replacement mapping
     */
    public function getPatterns(): array
    {
        return !empty($this->patterns) ? $this->patterns : $this->getFallbackPatterns();
    }

    /**
     * Comprehensive default patterns for sensitive data detection
     * Based on GDPR, CCPA, and common security best practices
     * 
     * @return array<string, string>
     */
    private function getFallbackPatterns(): array
    {
        return [
            // Financial Information - Process first to avoid conflicts with phone patterns
            '/\b(?:\d{4}[\s\-]?){3}\d{4}\b/' => '[REDACTED_CREDIT_CARD]',
            '/\b3[47]\d{13}\b/' => '[REDACTED_AMEX]',
            '/\b(?:4\d{12}(?:\d{3})?|5[1-5]\d{14}|6(?:011|5\d{2})\d{12})\b/' => '[REDACTED_CREDIT_CARD]',
            '/\bIBAN\s*[:=]?\s*[A-Z]{2}\d{2}[A-Z0-9]{4}\d{7}([A-Z0-9]?){0,16}\b/i' => '[REDACTED_IBAN]',

            // Contact Information - Process after financial to avoid conflicts
            '/\b[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Z|a-z]{2,}\b/' => '[REDACTED_EMAIL]',
            '/(?:\b|\()?(?:\+\d{1,2}\s?)?(?:\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}|\d{3}[\s.\-]\d{3}[\s.\-]\d{4})(?:\b|(?=\s|$))/' => '[REDACTED_PHONE]',

            // Identification Numbers
            '/\b\d{3}-\d{2}-\d{4}\b/' => '[REDACTED_SSN]',
            '/\b\d{9}\b/' => '[REDACTED_ID]', // Generic 9-digit ID
            '/\b[A-Z]{2}\d{6}[A-Z]\b/' => '[REDACTED_PASSPORT]',

            // API Keys and Tokens
            '/\b[\w\-]*(?:secret|key|token|api|auth)[\w\-]*$/i' => '[REDACTED]',
            '/\bapi[_\-]?key\s*[:=]\s*\S+/i' => 'api_key=[REDACTED]',
            '/\bpassword\s*[:=]\s*\S+/i' => 'password=[REDACTED]',
            '/\b(?:sk|pk)_(?:live|test)_[0-9a-zA-Z]{10,}\b/' => '[REDACTED_STRIPE_KEY]',
            '/\b(?:AKIA|ASIA)[0-9A-Z]{16}\b/' => '[REDACTED_AWS_KEY]',
            '/\beyJ[A-Za-z0-9_\-]*\.[A-Za-z0-9_\-]*\.[A-Za-z0-9_\-]*\b/' => '[REDACTED_JWT]',
            '/\bbearer\s+[a-zA-Z0-9\-_=]+/i' => 'bearer [REDACTED]',
            '/\btoken\s*[:=]\s*(?!eyJ|\\[REDACTED_JWT\\])\S+/i' => 'token=[REDACTED]',

            // Geographic Information
            '/(?<!#)\b\d{5}(?:[\s\-]\d{4})?(?=\s|$|[^\w\-])/' => '[REDACTED_ZIP]',

            // IP Addresses
            '/\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/' => '[REDACTED_IP]',
            '/\b(?:[A-F0-9]{1,4}:){7}[A-F0-9]{1,4}\b/i' => '[REDACTED_IP]',

            // Cryptocurrency Addresses
            '/\b[13][a-km-zA-HJ-NP-Z1-9]{25,34}\b/' => '[REDACTED_BTC_ADDRESS]',
            '/\b0x[a-fA-F0-9]{40}\b/' => '[REDACTED_ETH_ADDRESS]',

            // Common Database Connection Strings
            '/\bmongodb:\/\/[^\s]+/i' => 'mongodb://[REDACTED]',
            '/\bmysql:\/\/[^\s]+/i' => 'mysql://[REDACTED]',
            '/\bpostgres:\/\/[^\s]+/i' => 'postgres://[REDACTED]',
        ];
    }
}