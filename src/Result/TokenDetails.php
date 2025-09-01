<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Result;

/**
 * Detailed breakdown of token usage for specific categories.
 * Used to provide granular information about token consumption.
 */
final class TokenDetails
{
    /**
     * @param int|null $audioTokens Tokens used for audio processing
     * @param int|null $cachedTokens Tokens from cached content
     * @param int|null $reasoningTokens Tokens used for reasoning (OpenAI o1)
     * @param int|null $acceptedPredictionTokens Accepted prediction tokens
     * @param int|null $rejectedPredictionTokens Rejected prediction tokens
     * @param array<string, int>|null $modalityBreakdown Breakdown by modality (text, image, audio, etc.)
     */
    public function __construct(
        public readonly ?int $audioTokens = null,
        public readonly ?int $cachedTokens = null,
        public readonly ?int $reasoningTokens = null,
        public readonly ?int $acceptedPredictionTokens = null,
        public readonly ?int $rejectedPredictionTokens = null,
        public readonly ?array $modalityBreakdown = null,
    ) {
    }

    /**
     * Check if this details object has any non-null values.
     */
    public function hasData(): bool
    {
        return $this->audioTokens !== null
            || $this->cachedTokens !== null
            || $this->reasoningTokens !== null
            || $this->acceptedPredictionTokens !== null
            || $this->rejectedPredictionTokens !== null
            || $this->modalityBreakdown !== null;
    }

    /**
     * Convert to array, excluding null values.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->audioTokens !== null) {
            $result['audio_tokens'] = $this->audioTokens;
        }

        if ($this->cachedTokens !== null) {
            $result['cached_tokens'] = $this->cachedTokens;
        }

        if ($this->reasoningTokens !== null) {
            $result['reasoning_tokens'] = $this->reasoningTokens;
        }

        if ($this->acceptedPredictionTokens !== null) {
            $result['accepted_prediction_tokens'] = $this->acceptedPredictionTokens;
        }

        if ($this->rejectedPredictionTokens !== null) {
            $result['rejected_prediction_tokens'] = $this->rejectedPredictionTokens;
        }

        if ($this->modalityBreakdown !== null) {
            $result['modality_breakdown'] = $this->modalityBreakdown;
        }

        return $result;
    }
}
