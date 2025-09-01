<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Usage\Gemini;

use Lingoda\AiSdk\Result\Usage;
use Lingoda\AiSdk\Usage\UsageExtractorInterface;

final class GeminiUsageExtractor implements UsageExtractorInterface
{
    /**
     * @param array{
     *      prompt_token_count?: int|null,
     *      candidates_token_count?: int|null,
     *      total_token_count?: int|null,
     *      cached_content_token_count?: int|null,
     *      tool_use_prompt_token_count?: int|null,
     *      thoughts_token_count?: int|null
     *  } $usage
     */
    public function extract(array $usage): ?Usage
    {
        $promptTokens = $usage['prompt_token_count'] ?? 0;
        $completionTokens = $usage['candidates_token_count'] ?? 0;
        $totalTokens = $usage['total_token_count'] ?? ($promptTokens + $completionTokens);

        // Only return if we have at least some token data
        if ($promptTokens === 0 && $completionTokens === 0 && $totalTokens === 0) {
            return null;
        }

        return new Usage(
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $totalTokens,
            cachedTokens: $usage['cached_content_token_count'] ?? null,
            toolUseTokens: $usage['tool_use_prompt_token_count'] ?? null,
            thoughtsTokens: $usage['thoughts_token_count'] ?? null,
        );
    }
}
