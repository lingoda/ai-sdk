<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Security;

use Lingoda\AiSdk\Security\Attribute\Redact;
use Lingoda\AiSdk\Security\Attribute\Sensitive;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionObject;
use ReflectionProperty;

final readonly class AttributeSanitizer
{
    public function __construct(
        private SensitiveContentFilter $fallbackFilter,
        private bool $enabled = true,
        private bool $auditLog = false,
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Create an AttributeSanitizer with default settings
     */
    public static function createDefault(
        SensitiveContentFilter $fallbackFilter,
        LoggerInterface $logger = new NullLogger()
    ): self {
        return new self(
            fallbackFilter: $fallbackFilter,
            enabled: true,
            auditLog: true,
            logger: $logger
        );
    }

    /**
     * Sanitize object using attribute-based configuration
     * Only processes objects, returns other data unchanged
     *
     * @param mixed $data
     * @return mixed
     */
    public function sanitize(mixed $data): mixed
    {
        if (!$this->enabled || !is_object($data)) {
            return $data;
        }

        return $this->sanitizeObject($data);
    }

    /**
     * Sanitize an object by processing its properties with attributes
     */
    private function sanitizeObject(object $data): object
    {
        try {
            $reflection = new ReflectionObject($data);
            $sanitized = clone $data;

            foreach ($reflection->getProperties() as $property) {
                $property->setAccessible(true);
                $originalValue = $property->getValue($sanitized);
                
                if ($originalValue === null) {
                    continue;
                }

                $sanitizedValue = $this->sanitizeProperty($property, $originalValue);
                
                if ($sanitizedValue !== $originalValue) {
                    $property->setValue($sanitized, $sanitizedValue);
                    
                    if ($this->auditLog) {
                        $this->logSanitization($property, $originalValue, $sanitizedValue);
                    }
                }
            }

            return $sanitized;
        } catch (\ReflectionException $e) {
            $this->logger->error('Failed to sanitize object using attributes', [
                'error' => $e->getMessage(),
                'object_class' => get_class($data)
            ]);
            
            return $data;
        }
    }

    /**
     * Sanitize a property value based on its attributes
     */
    private function sanitizeProperty(ReflectionProperty $property, mixed $value): mixed
    {
        // Process #[Redact] attributes first
        $value = $this->processRedactAttributes($property, $value);
        
        // Process #[Sensitive] attributes
        $value = $this->processSensitiveAttributes($property, $value);
        
        // For nested objects, we need to process them too (but DataSanitizer will handle arrays and strings)
        if (is_object($value)) {
            $value = $this->sanitizeObject($value);
        }
        
        return $value;
    }

    /**
     * Process #[Redact] attributes on a property
     */
    private function processRedactAttributes(ReflectionProperty $property, mixed $value): mixed
    {
        $redactAttributes = $property->getAttributes(Redact::class);
        
        foreach ($redactAttributes as $attribute) {
            $redact = $attribute->newInstance();
            
            if (is_string($value)) {
                $sanitized = preg_replace(
                    $redact->getPattern(),
                    $redact->getReplacement(),
                    $value
                );
                
                if ($sanitized !== null) {
                    $value = $sanitized;
                }
            } elseif (is_array($value)) {
                $value = $this->applyRedactToArray($value, $redact);
            }
        }
        
        return $value;
    }

    /**
     * Process #[Sensitive] attributes on a property
     */
    private function processSensitiveAttributes(ReflectionProperty $property, mixed $value): mixed
    {
        $sensitiveAttributes = $property->getAttributes(Sensitive::class);
        
        foreach ($sensitiveAttributes as $attribute) {
            $sensitive = $attribute->newInstance();
            
            if (is_string($value)) {
                // Use fallback filter for sensitive content detection
                $filteredValue = $this->fallbackFilter->filter($value);
                
                // If content was filtered, replace with custom redaction text
                if ($filteredValue !== $value) {
                    $value = $sensitive->getRedactionText();
                }
            } elseif (is_array($value)) {
                $value = $this->applySensitiveToArray($value, $sensitive);
            }
        }
        
        return $value;
    }

    /**
     * Apply redact pattern to array elements
     *
     * @param array<mixed> $array
     * @return array<mixed>
     */
    private function applyRedactToArray(array $array, Redact $redact): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $sanitizedKey = is_string($key) ? $this->applyRedactToString($key, $redact) : $key;
            
            if (is_string($value)) {
                $result[$sanitizedKey] = $this->applyRedactToString($value, $redact);
            } elseif (is_array($value)) {
                $result[$sanitizedKey] = $this->applyRedactToArray($value, $redact);
            } else {
                $result[$sanitizedKey] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Apply sensitive filtering to array elements
     *
     * @param array<mixed> $array
     * @return array<mixed>
     */
    private function applySensitiveToArray(array $array, Sensitive $sensitive): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $filteredValue = $this->fallbackFilter->filter($value);
                $result[$key] = $filteredValue !== $value ? $sensitive->getRedactionText() : $value;
            } elseif (is_array($value)) {
                $result[$key] = $this->applySensitiveToArray($value, $sensitive);
            } else {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Apply redact pattern to a string value
     */
    private function applyRedactToString(string $value, Redact $redact): string
    {
        $sanitized = preg_replace(
            $redact->getPattern(),
            $redact->getReplacement(),
            $value
        );
        
        return $sanitized ?? $value;
    }


    /**
     * Log sanitization activity
     */
    private function logSanitization(ReflectionProperty $property, mixed $originalValue, mixed $sanitizedValue): void
    {
        $attributes = [];
        
        foreach ($property->getAttributes(Redact::class) as $attr) {
            $attributes[] = 'Redact';
        }
        
        foreach ($property->getAttributes(Sensitive::class) as $attr) {
            $attributes[] = 'Sensitive';
        }
        
        $this->logger->warning('Property sanitized using attributes', [
            'class' => $property->getDeclaringClass()->getName(),
            'property' => $property->getName(),
            'attributes' => $attributes,
            'original_type' => get_debug_type($originalValue),
            'sanitized_type' => get_debug_type($sanitizedValue),
            'original_length' => is_string($originalValue) ? mb_strlen($originalValue) : null,
            'sanitized_length' => is_string($sanitizedValue) ? mb_strlen($sanitizedValue) : null,
        ]);
    }
}
