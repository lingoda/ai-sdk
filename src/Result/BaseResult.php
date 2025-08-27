<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Result;

abstract class BaseResult implements ResultInterface
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly array $metadata = [],
    ) {
    }

    /**
     * Get additional metadata associated with the result.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
