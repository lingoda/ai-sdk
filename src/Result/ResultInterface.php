<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Result;

interface ResultInterface
{
    /**
     * Get the content of the result.
     */
    public function getContent(): mixed;

    /**
     * Get additional metadata associated with the result.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    /**
     * Get token usage information for this result.
     */
    public function getUsage(): ?Usage;

    public function withUsage(?Usage $usage): static;
}
