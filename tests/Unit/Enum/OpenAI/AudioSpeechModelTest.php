<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Enum\OpenAI;

use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechFormat;
use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechModel;
use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechVoice;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AudioSpeechModelTest extends TestCase
{
    /**
     * @return iterable<string, array{AudioSpeechModel}>
     */
    public static function models(): iterable
    {
        foreach (AudioSpeechModel::cases() as $model) {
            yield $model->value => [$model];
        }
    }

    #[DataProvider('models')]
    public function testLimitsFormatsAndVoices(AudioSpeechModel $model): void
    {
        $this->assertSame(4096, $model->getMaxCharacters());
        $this->assertSame(AudioSpeechFormat::cases(), $model->getSupportedFormats());
        $this->assertSame(
            [AudioSpeechVoice::ALLOY, AudioSpeechVoice::ECHO, AudioSpeechVoice::FABLE, AudioSpeechVoice::ONYX, AudioSpeechVoice::NOVA, AudioSpeechVoice::SHIMMER],
            $model->getSupportedVoices()
        );
    }
}
