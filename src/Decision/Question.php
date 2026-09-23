<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Decision;

use Lingoda\AiSdk\Exception\InvalidArgumentException;

/**
 * A question for a decision model: noul (probability that a statement is true), choice or score.
 */
final readonly class Question
{
    /**
     * @param 'noul'|'choice'|'score' $type
     * @param string|list<string> $instructions
     * @param array<int|string, string>|list<string>|null $criteria
     */
    private function __construct(
        public string $type,
        public string|array $instructions,
        public ?array $criteria = null,
    ) {
    }

    /**
     * @param string|list<string> $instructions
     * @param string|null $true What "true" means, optional
     * @param string|null $false What "false" means, optional
     */
    public static function noul(string|array $instructions, ?string $true = null, ?string $false = null): self
    {
        $criteria = array_filter(['true' => $true, 'false' => $false], static fn (?string $value): bool => $value !== null);

        return new self('noul', $instructions, $criteria !== [] ? $criteria : null);
    }

    /**
     * @param string|list<string> $instructions
     * @param array<int|string, string> $options Option key => description
     *
     * @throws InvalidArgumentException
     */
    public static function choice(string|array $instructions, array $options): self
    {
        if ($options === []) {
            throw new InvalidArgumentException('A choice question requires at least one option.');
        }

        return new self('choice', $instructions, $options);
    }

    /**
     * @param string|list<string> $instructions
     * @param list<string> $levels Ordered from lowest to highest
     *
     * @throws InvalidArgumentException
     */
    public static function score(string|array $instructions, array $levels): self
    {
        if (count($levels) < 2) {
            throw new InvalidArgumentException('A score question requires at least two levels.');
        }

        return new self('score', $instructions, $levels);
    }

    /**
     * Request shape. Noul and choice criteria are maps and must stay JSON objects even with numeric keys.
     *
     * @return array{type: string, instructions: string|list<string>, criteria?: object|list<string>}
     */
    public function toArray(): array
    {
        $question = ['type' => $this->type, 'instructions' => $this->instructions];

        if ($this->criteria !== null) {
            $question['criteria'] = $this->type === 'score' ? array_values($this->criteria) : (object) $this->criteria;
        }

        return $question;
    }
}
