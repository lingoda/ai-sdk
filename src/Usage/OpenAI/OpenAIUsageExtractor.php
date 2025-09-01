<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Usage\OpenAI;

use Lingoda\AiSdk\Result\TokenDetails;
use Lingoda\AiSdk\Result\Usage;
use Lingoda\AiSdk\Usage\UsageExtractorInterface;

final class OpenAIUsageExtractor implements UsageExtractorInterface
{
    /**
     * @param array{
     *      prompt_tokens?: int|null,
     *      completion_tokens?: int|null,
     *      total_tokens?: int|null,
     *      prompt_tokens_details?: null|array{
     *          cached_tokens?: int|null,
     *          audio_tokens?: int|null
     *      },
     *      completion_tokens_details?: null|array{
     *          reasoning_tokens?: int|null,
     *          audio_tokens?: int|null,
     *          accepted_prediction_tokens?: int|null,
     *          rejected_prediction_tokens?: int|null
     *      },
     *  } $usage
     */
    public function extract(array $usage): ?Usage
    {
        $promptTokens = $usage['prompt_tokens'] ?? 0;
        $completionTokens = $usage['completion_tokens'] ?? 0;
        $totalTokens = $usage['total_tokens'] ?? ($promptTokens + $completionTokens);

        // Only return if we have at least some token data
        if ($promptTokens === 0 && $completionTokens === 0 && $totalTokens === 0) {
            return null;
        }

        // Extract detailed token information
        $promptDetails = $this->extractPromptDetails($usage['prompt_tokens_details'] ?? []);
        $completionDetails = $this->extractCompletionDetails($usage['completion_tokens_details'] ?? []);

        return new Usage(
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $totalTokens,
            promptDetails: $promptDetails,
            completionDetails: $completionDetails,
            cachedTokens: ($usage['prompt_tokens_details']['cached_tokens'] ?? null),
            reasoningTokens: ($usage['completion_tokens_details']['reasoning_tokens'] ?? null),
        );
    }

    /**
     * @param array{
     *      cached_tokens?: int|null,
     *      audio_tokens?: int|null
     * } $details
     */
    private function extractPromptDetails(array $details): ?TokenDetails
    {
        if (empty($details)) {
            return null;
        }

        $tokenDetails = new TokenDetails(
            audioTokens: $details['audio_tokens'] ?? null,
            cachedTokens: $details['cached_tokens'] ?? null,
        );

        return $tokenDetails->hasData() ? $tokenDetails : null;
    }

    /**
     * @param array{
     *      reasoning_tokens?: int|null,
     *      audio_tokens?: int|null,
     *      accepted_prediction_tokens?: int|null,
     *      rejected_prediction_tokens?: int|null
     * } $details
     */
    private function extractCompletionDetails(array $details): ?TokenDetails
    {
        if (empty($details)) {
            return null;
        }

        $tokenDetails = new TokenDetails(
            audioTokens: $details['audio_tokens'] ?? null,
            reasoningTokens: $details['reasoning_tokens'] ?? null,
            acceptedPredictionTokens: $details['accepted_prediction_tokens'] ?? null,
            rejectedPredictionTokens: $details['rejected_prediction_tokens'] ?? null,
        );

        return $tokenDetails->hasData() ? $tokenDetails : null;
    }
}
