<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Usage;

use Lingoda\AiSdk\Result\Usage;

/**
 * Interface for extracting and normalizing usage data from AI provider responses.
 * Creates Usage DTO with comprehensive token information.
 */
interface UsageExtractorInterface
{
    /**
     * Extract usage data from provider response and create Usage DTO.
     *
     * @param array<string, mixed> $usage Raw usage data from provider response
     */
    public function extract(array $usage): ?Usage;
}
