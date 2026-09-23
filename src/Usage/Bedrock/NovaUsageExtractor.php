<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Usage\Bedrock;

use Lingoda\AiSdk\Result\Usage;
use Lingoda\AiSdk\Usage\UsageExtractorInterface;

/**
 * Nova on Bedrock reports camelCase usage (Converse shape). Claude on Bedrock uses AnthropicUsageExtractor.
 */
final class NovaUsageExtractor implements UsageExtractorInterface
{
    /**
     * @param array{
     *     inputTokens?: int|null,
     *     outputTokens?: int|null,
     *     totalTokens?: int|null,
     *     cacheReadInputTokenCount?: int|null,
     *     cacheWriteInputTokenCount?: int|null,
     *     ...<string, mixed>
     * } $usage
     */
    public function extract(array $usage): ?Usage
    {
        $inputTokens = $usage['inputTokens'] ?? 0;
        $outputTokens = $usage['outputTokens'] ?? 0;

        if ($inputTokens === 0 && $outputTokens === 0) {
            return null;
        }

        $cachedTokens = ($usage['cacheReadInputTokenCount'] ?? 0) + ($usage['cacheWriteInputTokenCount'] ?? 0);

        return new Usage(
            promptTokens: $inputTokens,
            completionTokens: $outputTokens,
            totalTokens: $usage['totalTokens'] ?? $inputTokens + $outputTokens,
            cachedTokens: $cachedTokens > 0 ? $cachedTokens : null,
        );
    }
}
