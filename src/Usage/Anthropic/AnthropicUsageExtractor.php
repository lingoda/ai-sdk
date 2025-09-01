<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Usage\Anthropic;

use Lingoda\AiSdk\Result\Usage;
use Lingoda\AiSdk\Usage\UsageExtractorInterface;

final class AnthropicUsageExtractor implements UsageExtractorInterface
{
    /**
     * @param array{
     *     input_tokens?: int|null,
     *     cache_creation_input_tokens?: int|null,
     *     cache_read_input_tokens?: int|null,
     *     output_tokens?: int|null
     * } $usage
     */
    public function extract(array $usage): ?Usage
    {
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        // Calculate total since Anthropic doesn't provide it
        $totalTokens = $inputTokens + $outputTokens;

        // Only return if we have at least some token data
        if ($inputTokens === 0 && $outputTokens === 0) {
            return null;
        }

        // Calculate total cached tokens
        $cachedTokens = ($usage['cache_creation_input_tokens'] ?? 0) + ($usage['cache_read_input_tokens'] ?? 0);
        $cachedTokens = $cachedTokens > 0 ? $cachedTokens : null;

        return new Usage(
            promptTokens: $inputTokens,
            completionTokens: $outputTokens,
            totalTokens: $totalTokens,
            cachedTokens: $cachedTokens,
        );
    }
}
