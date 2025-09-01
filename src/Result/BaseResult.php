<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Result;

abstract class BaseResult implements ResultInterface
{
    private ?Usage $usage = null;

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

    public function getUsage(): ?Usage
    {
        return $this->usage;
    }

    public function withUsage(?Usage $usage): static
    {
        $new = clone $this;
        $new->usage = $usage;

        return $new;
    }
}
