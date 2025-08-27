<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\RateLimit;

use Lingoda\AiSdk\ModelInterface;

abstract class AbstractTokenEstimator implements TokenEstimatorInterface
{
    private const array SPECIAL_TOKEN_PATTERNS = [
        // JSON structure tokens
        '/[{}"\[\]:,]/' => 1,
        // Code blocks
        '/```[\s\S]*?```/' => 50,
        // URLs
        '/https?:\/\/[^\s]+/' => 5,
    ];

    public function estimate(ModelInterface $model, array|string $payload): int
    {
        if (is_string($payload)) {
            return $this->estimateTokensFromText($payload);
        }

        $text = $this->extractTextFromPayload($payload);

        return $this->estimateTokensFromText($text);
    }

    /**
     * Extract text from provider-specific payload format.
     * Each provider implements their own extraction logic.
     *
     * @param array<string, mixed>|array<int, array{role: string, content: string}> $payload
     */
    abstract protected function extractTextFromPayload(array $payload): string;

    /**
     * Get provider-specific token calculation adjustments.
     *
     * @return array{char_divisor: float, word_multiplier: float, sentence_tokens: int, efficiency_factor: float}
     */
    protected function getProviderAdjustments(): array
    {
        // Default adjustments (similar to OpenAI)
        return [
            'char_divisor' => 4.0,        // 1 token ≈ 4 characters
            'word_multiplier' => 0.75,    // 1 token ≈ 0.75 words
            'sentence_tokens' => 20,      // ~20 tokens per sentence
            'efficiency_factor' => 1.1,   // 10% safety buffer
        ];
    }

    protected function estimateTokensFromText(string $text): int
    {
        if (empty($text)) {
            return 1; // Minimum token count
        }

        // Handle special patterns first and remove them for basic calculation
        $specialTokens = 0;
        $cleanText = $text;
        
        foreach (self::SPECIAL_TOKEN_PATTERNS as $pattern => $tokenCount) {
            $matches = preg_match_all($pattern, $cleanText);
            $specialTokens += ($matches ?: 0) * $tokenCount;
            $cleanText = preg_replace($pattern, ' ', $cleanText) ?: $cleanText;
        }

        // Clean text for basic estimation
        $cleanText = preg_replace('/\s+/', ' ', (string) $cleanText);
        $cleanText = mb_trim((string) $cleanText);

        if (empty($cleanText)) {
            return max(1, $specialTokens);
        }

        $charCount = mb_strlen($cleanText);
        $wordCount = str_word_count($cleanText);
        $sentenceCount = max(1, preg_match_all('/[.!?]+/', $cleanText, $matches));

        // Get provider-specific adjustments
        $adjustments = $this->getProviderAdjustments();

        // Multiple estimation methods with provider adjustments
        $tokensByChar = $charCount / $adjustments['char_divisor'];
        $tokensByWord = $wordCount * $adjustments['word_multiplier'];
        $tokensBySentence = $sentenceCount * $adjustments['sentence_tokens'];

        // Weighted average (give more weight to character and word counts)
        $baseEstimate = ($tokensByChar * 0.4 + $tokensByWord * 0.4 + $tokensBySentence * 0.2);
        
        // Add special tokens
        $totalEstimate = $baseEstimate + $specialTokens;

        // Apply provider efficiency factor and round up
        return max(1, (int) ceil($totalEstimate * $adjustments['efficiency_factor']));
    }
}
