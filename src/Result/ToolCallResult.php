<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Result;

use Lingoda\AiSdk\Exception\InvalidArgumentException;

final class ToolCallResult extends BaseResult
{
    /**
     * @var ToolCall[]
     */
    private readonly array $toolCalls;

    /**
     * @param array<string, mixed> $metadata
     *
     * @throws InvalidArgumentException if no tool calls are provided
     */
    public function __construct(
        array $metadata = [],
        ToolCall ...$toolCalls,
    ) {
        if ([] === $toolCalls) {
            throw new InvalidArgumentException('Response must have at least one tool call.');
        }

        parent::__construct($metadata);
        $this->toolCalls = $toolCalls;
    }

    /**
     * @return ToolCall[]
     */
    public function getContent(): array
    {
        return $this->toolCalls;
    }
}
