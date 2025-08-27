<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Security;

use Lingoda\AiSdk\Security\Pattern\PatternRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Webmozart\Assert\Assert;

final readonly class DataSanitizer
{
    public function __construct(
        private SensitiveContentFilter $filter,
        private bool $enabled = true,
        private bool $auditLog = false,
        private LoggerInterface $logger = new NullLogger(),
        private ?AttributeSanitizer $attributeSanitizer = null
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Create a DataSanitizer with default production settings
     */
    public static function createDefault(LoggerInterface $logger = new NullLogger()): self
    {
        $filter = new SensitiveContentFilter(
            PatternRegistry::createDefault(),
            $logger
        );
        
        return new self(
            filter: $filter,
            enabled: true,
            auditLog: true,
            logger: $logger,
            attributeSanitizer: AttributeSanitizer::createDefault($filter, $logger)
        );
    }

    /**
     * Create a DataSanitizer with attribute-based sanitization enabled
     */
    public static function withAttributeSupport(
        SensitiveContentFilter $filter,
        LoggerInterface $logger = new NullLogger(),
        bool $enabled = true,
        bool $auditLog = true
    ): self {
        return new self(
            filter: $filter,
            enabled: $enabled,
            auditLog: $auditLog,
            logger: $logger,
            attributeSanitizer: AttributeSanitizer::createDefault($filter, $logger)
        );
    }

    /**
     * Sanitize data recursively
     *
     * @param mixed $data
     * @return mixed
     */
    public function sanitize(mixed $data): mixed
    {
        if (!$this->enabled) {
            return $data;
        }

        return $this->sanitizeRecursive($data);
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    private function sanitizeRecursive(mixed $data): mixed
    {
        return match (true) {
            is_string($data) => $this->sanitizeString($data),
            is_array($data) => $this->sanitizeArray($data),
            is_object($data) => $this->sanitizeObjectData($data),
            default => $data
        };
    }

    /**
     * Sanitize object data using attribute-based sanitization first, then pattern-based
     *
     * @param object $data
     * @return object
     */
    private function sanitizeObjectData(object $data): object
    {
        // First, apply attribute-based sanitization if available
        if ($this->attributeSanitizer !== null) {
            $sanitizedData = $this->attributeSanitizer->sanitize($data);
            // AttributeSanitizer should only return objects when given objects
            Assert::object($sanitizedData, 'AttributeSanitizer must return objects when given objects');
            $data = $sanitizedData;
        }
        
        // Then always apply pattern-based object sanitization
        return $this->sanitizeObject($data);
    }

    private function sanitizeString(string $data): string
    {
        $originalData = $data;
        $sanitizedData = $this->filter->filter($data);
        
        if ($this->auditLog && $originalData !== $sanitizedData) {
            // Extract detected pattern types by checking which patterns matched
            $detectedPatterns = $this->getDetectedPatterns($originalData);
            
            $this->logger->warning('Sensitive data detected and sanitized', [
                'detected_patterns' => $detectedPatterns,
                'original_length' => mb_strlen($originalData),
                'sanitized_length' => mb_strlen($sanitizedData),
                'redacted_sample' => mb_substr($sanitizedData, 0, 100) . (mb_strlen($sanitizedData) > 100 ? '...' : '')
            ]);
        }
        
        return $sanitizedData;
    }

    /**
     * Get the names of patterns that matched in the given content
     *
     * @return list<string>
     */
    private function getDetectedPatterns(string $content): array
    {
        $detected = [];
        $patterns = $this->filter->getPatternRegistry()->getAllPatterns();
        
        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $content)) {
                // Extract pattern name from the replacement text or create a descriptive name
                if (str_contains($replacement, 'EMAIL')) {
                    $detected[] = 'email';
                } elseif (str_contains($replacement, 'PHONE')) {
                    $detected[] = 'phone';
                } elseif (str_contains($replacement, 'CREDIT_CARD')) {
                    $detected[] = 'credit_card';
                } elseif (str_contains($replacement, 'SSN')) {
                    $detected[] = 'ssn';
                } elseif (str_contains($replacement, 'API_KEY')) {
                    $detected[] = 'api_key';
                } else {
                    $detected[] = 'sensitive_data';
                }
            }
        }
        
        return array_values(array_unique($detected));
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $sanitizedKey = is_string($key) ? $this->sanitizeString($key) : $key;
            $sanitized[$sanitizedKey] = $this->sanitizeRecursive($value);
        }
        
        return $sanitized;
    }

    /**
     * @param object $data
     * @return object
     */
    private function sanitizeObject(object $data): object
    {
        try {
            // Convert object to array and sanitize
            $jsonString = json_encode($data, JSON_THROW_ON_ERROR);
            if ($jsonString === false) {
                return (object) ['sanitized_object' => '[OBJECT_CONVERSION_FAILED]'];
            }
            
            $array = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
            
            if (!is_array($array)) {
                return (object) ['sanitized_object' => '[OBJECT_CONVERSION_FAILED]'];
            }
            
            $sanitizedArray = $this->sanitizeArray($array);
            
            // Convert back to object
            $sanitizedJsonString = json_encode($sanitizedArray, JSON_THROW_ON_ERROR);
            if ($sanitizedJsonString === false) {
                return (object) ['sanitized_object' => '[OBJECT_CONVERSION_FAILED]'];
            }
            
            $result = json_decode($sanitizedJsonString, false, 512, JSON_THROW_ON_ERROR);
            if (!is_object($result)) {
                return (object) ['sanitized_object' => '[OBJECT_CONVERSION_FAILED]'];
            }
            return $result;
        } catch (\JsonException $e) {
            $this->logger->error('Failed to sanitize object', [
                'error' => $e->getMessage(),
                'object_class' => get_class($data)
            ]);
            
            return (object) ['sanitized_object' => '[OBJECT_SANITIZATION_FAILED]'];
        }
    }
}
