<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Decision;

use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Result\BaseResult;

final class DecisionResult extends BaseResult
{
    /**
     * @param array<string, Answer> $answers Keyed by question id
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly array $answers,
        array $metadata = [],
    ) {
        parent::__construct($metadata);
    }

    /**
     * @return array<string, Answer>
     */
    public function getContent(): array
    {
        return $this->answers;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getAnswer(string $questionId): Answer
    {
        return $this->answers[$questionId]
            ?? throw new InvalidArgumentException(sprintf('No answer for question "%s".', $questionId));
    }
}
