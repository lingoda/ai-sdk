<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Decision;

use Lingoda\AiSdk\Decision\Question;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QuestionTest extends TestCase
{
    public function testNoulWithoutCriteriaHasNoCriteriaKey(): void
    {
        $question = Question::noul('Is it on topic?');

        $this->assertSame(['type' => 'noul', 'instructions' => 'Is it on topic?'], $question->toArray());
    }

    public function testNoulWithPartialCriteria(): void
    {
        $question = Question::noul(['Is it on topic?', 'Be strict'], true: 'about the lesson');

        $this->assertSame(
            '{"type":"noul","instructions":["Is it on topic?","Be strict"],"criteria":{"true":"about the lesson"}}',
            json_encode($question->toArray(), JSON_THROW_ON_ERROR)
        );
    }

    public function testChoiceWithNumericKeysEncodesAsObject(): void
    {
        $question = Question::choice('Pick one', [0 => 'a', 1 => 'b']);

        $this->assertSame(
            '{"type":"choice","instructions":"Pick one","criteria":{"0":"a","1":"b"}}',
            json_encode($question->toArray(), JSON_THROW_ON_ERROR)
        );
    }

    public function testChoiceWithoutOptionsIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Question::choice('Pick one', []);
    }

    public function testScoreCriteriaEncodeAsList(): void
    {
        $question = Question::score('Rate it', ['low', 'mid', 'high']);

        $this->assertSame(
            '{"type":"score","instructions":"Rate it","criteria":["low","mid","high"]}',
            json_encode($question->toArray(), JSON_THROW_ON_ERROR)
        );
    }

    public function testScoreNeedsAtLeastTwoLevels(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Question::score('Rate it', ['only']);
    }
}
