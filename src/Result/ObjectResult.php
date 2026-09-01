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

    /**
     * Get the content normalized to an array, converting nested objects recursively.
     *
     * @throws \JsonException when the content is not JSON-serializable (e.g. closures)
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        /** @var array<array-key, mixed> */
        return (array) json_decode(
            json_encode($this->content, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }
}
