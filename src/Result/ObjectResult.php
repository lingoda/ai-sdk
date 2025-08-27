<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Result;

final class ObjectResult extends BaseResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly object $content,
        array $metadata = [],
    ) {
        parent::__construct($metadata);
    }

    public function getContent(): object
    {
        return $this->content;
    }
}