<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Decision;

use Lingoda\AiSdk\Decision\Answer;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AnswerTest extends TestCase
{
    public function testNoulAnswer(): void
    {
        $answer = Answer::fromArray('on_topic', ['type' => 'noul', 'noul' => 0.91]);

        $this->assertSame('noul', $answer->type);
        $this->assertSame(0.91, $answer->probability);
        $this->assertTrue($answer->isTrue());
        $this->assertFalse($answer->isTrue(0.95));
    }

    public function testNoulAcceptsIntegerProbability(): void
    {
        $answer = Answer::fromArray('x', ['type' => 'noul', 'noul' => 1]);

        $this->assertSame(1.0, $answer->probability);
    }

    public function testChoiceAnswer(): void
    {
        $answer = Answer::fromArray('color', [
            'type' => 'choice',
            'choice' => 'green',
            'probabilities' => ['green' => 0.8, 'red' => 0.2],
            'confidence' => 0.8,
        ]);

        $this->assertSame('choice', $answer->type);
        $this->assertSame('green', $answer->choice);
        $this->assertSame(['green' => 0.8, 'red' => 0.2], $answer->probabilities);
        $this->assertSame(0.8, $answer->confidence);
        $this->assertNull($answer->probability);
    }

    public function testScoreAnswer(): void
    {
        $answer = Answer::fromArray('level', [
            'type' => 'score',
            'score' => 2.4,
            'legend' => ['low', 'mid', 'high'],
            'probabilities' => ['0' => 0.1, '1' => 0.4, '2' => 0.5],
            'confidence' => 0.5,
        ]);

        $this->assertSame(2.4, $answer->score);
        $this->assertSame(['low', 'mid', 'high'], $answer->legend);
        $this->assertSame(['0' => 0.1, '1' => 0.4, '2' => 0.5], $answer->probabilities);
        $this->assertSame(0.5, $answer->confidence);
    }

    public function testIsTrueOnChoiceAnswerThrows(): void
    {
        $answer = Answer::fromArray('color', [
            'type' => 'choice',
            'choice' => 'green',
            'probabilities' => ['green' => 1.0],
            'confidence' => 1.0,
        ]);

        $this->expectException(\LogicException::class);

        $answer->isTrue();
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function malformedAnswers(): iterable
    {
        yield 'missing type' => [['noul' => 0.5]];
        yield 'unknown type' => [['type' => 'maybe']];
        yield 'noul without number' => [['type' => 'noul', 'noul' => 'high']];
        yield 'choice without choice' => [['type' => 'choice', 'probabilities' => [], 'confidence' => 1]];
        yield 'choice without probabilities' => [['type' => 'choice', 'choice' => 'a', 'confidence' => 1]];
        yield 'choice with non-numeric probability' => [['type' => 'choice', 'choice' => 'a', 'probabilities' => ['a' => 'x'], 'confidence' => 1]];
        yield 'score without legend' => [['type' => 'score', 'score' => 1, 'probabilities' => [], 'confidence' => 1]];
        yield 'score without confidence' => [['type' => 'score', 'score' => 1, 'legend' => [], 'probabilities' => []]];
    }

    /**
     * @param array<mixed> $data
     */
    #[DataProvider('malformedAnswers')]
    public function testMalformedAnswerIsRejected(array $data): void
    {
        $this->expectException(InvalidArgumentException::class);

        Answer::fromArray('q', $data);
    }
}
