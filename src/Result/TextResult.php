<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Result;

final class TextResult extends BaseResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly string $content,
        array $metadata = [],
    ) {
        parent::__construct($metadata);
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
