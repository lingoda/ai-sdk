<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Security\Pattern;

final readonly class PatternRegistry
{
    /**
     * @param array<string, string> $configPatterns
     * @param array<string> $customPatterns
     */
    public function __construct(
        private DefaultPatterns $defaultPatterns,
        private array $configPatterns = [],
        private array $customPatterns = []
    ) {}

    /**
     * Get all patterns merged from defaults, config, and custom patterns
     * 
     * @return array<string, string> pattern => replacement mapping
     */
    public function getAllPatterns(): array
    {
        $patterns = $this->defaultPatterns->getPatterns();
        
        // Override/add config patterns (pattern => replacement format)
        foreach ($this->configPatterns as $pattern => $replacement) {
            if (is_string($pattern) && is_string($replacement)) {
                $patterns[$pattern] = $replacement;
            }
        }
        
        // Add custom patterns (use default replacement for these)
        foreach ($this->customPatterns as $pattern) {
            if (is_string($pattern)) {
                $patterns[$pattern] = '[REDACTED]';
            }
        }
        
        return $patterns;
    }

    /**
     * Get pattern by name/key
     */
    public function getPattern(string $name): ?string
    {
        $patterns = $this->getAllPatterns();
        
        return $patterns[$name] ?? null;
    }

    /**
     * Check if pattern exists
     */
    public function hasPattern(string $name): bool
    {
        return $this->getPattern($name) !== null;
    }

    /**
     * Get all pattern names/keys
     * 
     * @return array<string>
     */
    public function getPatternNames(): array
    {
        return array_keys($this->getAllPatterns());
    }

    /**
     * Create a registry with additional patterns
     * 
     * @param array<string, string> $additionalPatterns
     */
    public function withAdditionalPatterns(array $additionalPatterns): self
    {
        return new self(
            $this->defaultPatterns,
            array_merge($this->configPatterns, $additionalPatterns),
            $this->customPatterns
        );
    }

    /**
     * Create a registry with custom patterns added
     * 
     * @param array<string> $customPatterns
     */
    public function withCustomPatterns(array $customPatterns): self
    {
        return new self(
            $this->defaultPatterns,
            $this->configPatterns,
            array_merge($this->customPatterns, $customPatterns)
        );
    }

    /**
     * Create default registry instance
     */
    public static function createDefault(): self
    {
        return new self(new DefaultPatterns());
    }
}