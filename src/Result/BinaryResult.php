<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Result;

final class BinaryResult extends BaseResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly string $content,
        private readonly string $mimeType,
        array $metadata = [],
    ) {
        parent::__construct($metadata);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Get the content as a base64 encoded string.
     */
    public function toBase64(): string
    {
        return base64_encode($this->content);
    }
}
