<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Result;

final class ObjectResult extends BaseResult
{
    /**
     * @param object|array<array-key, mixed> $content
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly object|array $content,
        array $metadata = [],
    ) {
        parent::__construct($metadata);
    }

    /**
     * @return object|array<array-key, mixed>
     */
    public function getContent(): object|array
    {
        return $this->content;
    }
}
