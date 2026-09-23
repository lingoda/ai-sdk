<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Decision;

use Lingoda\AiSdk\Exception\InvalidArgumentException;

/**
 * Answer to one Question. Which fields are set depends on the type:
 * noul: probability; choice: choice, probabilities, confidence; score: score, legend, probabilities, confidence.
 */
final readonly class Answer
{
    /**
     * @param 'noul'|'choice'|'score' $type
     * @param array<string, float> $probabilities
     * @param array<mixed> $legend
     */
    private function __construct(
        public string $type,
        public ?float $probability = null,
        public ?string $choice = null,
        public ?float $score = null,
        public array $probabilities = [],
        public array $legend = [],
        public ?float $confidence = null,
    ) {
    }

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidArgumentException when the answer does not have the shape its type requires
     */
    public static function fromArray(string $id, array $data): self
    {
        $type = $data['type'] ?? null;

        return match ($type) {
            'noul' => new self('noul', probability: self::float($id, $data, 'noul')),
            'choice' => new self(
                'choice',
                choice: is_string($data['choice'] ?? null)
                    ? $data['choice']
                    : throw new InvalidArgumentException(sprintf('Answer "%s" of type "choice" has no string "choice".', $id)),
                probabilities: self::probabilities($id, $data),
                confidence: self::float($id, $data, 'confidence'),
            ),
            'score' => new self(
                'score',
                score: self::float($id, $data, 'score'),
                probabilities: self::probabilities($id, $data),
                legend: is_array($data['legend'] ?? null)
                    ? $data['legend']
                    : throw new InvalidArgumentException(sprintf('Answer "%s" of type "score" has no "legend" array.', $id)),
                confidence: self::float($id, $data, 'confidence'),
            ),
            default => throw new InvalidArgumentException(sprintf(
                'Answer "%s" has an unsupported type "%s".',
                $id,
                is_string($type) ? $type : get_debug_type($type)
            )),
        };
    }

    /**
     * For noul answers: whether the statement holds at the given probability threshold.
     */
    public function isTrue(float $threshold = 0.5): bool
    {
        if ($this->probability === null) {
            throw new \LogicException(sprintf('isTrue() is only defined for noul answers, this is a "%s" answer.', $this->type));
        }

        return $this->probability >= $threshold;
    }

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidArgumentException
     */
    private static function float(string $id, array $data, string $key): float
    {
        $value = $data[$key] ?? null;
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException(sprintf('Answer "%s" has no numeric "%s".', $id, $key));
        }

        return (float) $value;
    }

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidArgumentException
     *
     * @return array<string, float>
     */
    private static function probabilities(string $id, array $data): array
    {
        $raw = $data['probabilities'] ?? null;
        if (!is_array($raw)) {
            throw new InvalidArgumentException(sprintf('Answer "%s" has no "probabilities" map.', $id));
        }

        $probabilities = [];
        foreach ($raw as $key => $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException(sprintf('Answer "%s" has a non-numeric probability for "%s".', $id, $key));
            }
            $probabilities[(string) $key] = (float) $value;
        }

        return $probabilities;
    }
}
