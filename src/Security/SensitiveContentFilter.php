<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Security;

use Lingoda\AiSdk\Security\Pattern\PatternRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class SensitiveContentFilter
{
    public function __construct(
        private PatternRegistry $patternRegistry,
        private LoggerInterface $logger = new NullLogger()
    ) {}

    /**
     * Get the pattern registry for audit purposes
     */
    public function getPatternRegistry(): PatternRegistry
    {
        return $this->patternRegistry;
    }

    /**
     * Filter sensitive content from a string
     */
    public function filter(string $content): string
    {
        if (empty($content)) {
            return $content;
        }

        $originalContent = $content;
        $patterns = $this->patternRegistry->getAllPatterns();
        $detectedCount = 0;
        
        foreach ($patterns as $pattern => $replacement) {
            $matches = [];
            if (preg_match_all($pattern, $content, $matches)) {
                $detectedCount++;
                
                $result = preg_replace($pattern, $replacement, $content);
                if ($result !== null) {
                    $content = $result;
                }
            }
        }

        // Log only if sensitive content was actually found and filtered
        if ($detectedCount > 0 && $content !== $originalContent) {
            $this->logger->debug('Sensitive content filtered', [
                'patterns_matched' => $detectedCount,
                'original_length' => strlen($originalContent),
                'filtered_length' => strlen($content),
                'content_sample' => substr($content, 0, 100) . (strlen($content) > 100 ? '...' : '')
            ]);
        }

        return $content;
    }

    /**
     * Filter sensitive content using specific pattern
     */
    public function filterWithPattern(string $content, string $patternName, ?string $replacement = null): string
    {
        $pattern = $this->patternRegistry->getPattern($patternName);
        if ($pattern === null) {
            return $content;
        }

        $replacement = $replacement ?? '[REDACTED]';
        
        $matches = [];
        if (preg_match_all($pattern, $content, $matches)) {
            $this->logger->info('Sensitive content filtered with specific pattern', [
                'pattern_name' => $patternName,
                'matches_count' => count($matches[0])
            ]);
            
            $content = preg_replace($pattern, $replacement, $content);
        }

        return (string) $content;
    }

}