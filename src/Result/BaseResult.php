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

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
