<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Audio\OpenAI;

use Lingoda\AiSdk\Audio\AudioOptionsInterface;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechFormat;
use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechModel;
use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechVoice;
use Lingoda\AiSdk\Enum\OpenAI\AudioTranscribeModel;

/**
 * OpenAI audio options with static factory methods for type safety.
 * Provides simple, transparent configuration for audio operations.
 */
final readonly class AudioOptions implements AudioOptionsInterface
{
    /**
     * @param array<string, mixed> $options
     */
    private function __construct(
        private array $options
    ) {
    }

    public static function textToSpeech(
        AudioSpeechModel $model = AudioSpeechModel::TTS_1,
        AudioSpeechVoice $voice = AudioSpeechVoice::ALLOY,
        AudioSpeechFormat $format = AudioSpeechFormat::MP3,
        ?float $speed = null
    ): self {
        $options = [
            'model' => $model->value,
            'voice' => $voice->value,
            'response_format' => $format->value,
        ];

        if ($speed !== null) {
            if ($speed < 0.25 || $speed > 4.0) {
                throw new \InvalidArgumentException('Speed must be between 0.25 and 4.0');
            }
            $options['speed'] = $speed;
        }

        return new self($options);
    }

    public static function speechToText(
        AudioTranscribeModel $model = AudioTranscribeModel::WHISPER_1,
        ?string $language = null,
        ?bool $timestamps = null,
        ?float $temperature = null,
        ?string $responseFormat = null
    ): self {
        $options = [
            'model' => $model->value,
        ];

        if ($language !== null) {
            $options['language'] = $language;
        }

        if ($timestamps === true) {
            $options['timestamp_granularities'] = ['word', 'segment'];
            $options['response_format'] = 'verbose_json';
        }

        if ($temperature !== null) {
            if ($temperature < 0.0 || $temperature > 1.0) {
                throw new \InvalidArgumentException('Temperature must be between 0.0 and 1.0');
            }
            $options['temperature'] = $temperature;
        }

        if ($responseFormat !== null) {
            $options['response_format'] = $responseFormat;
        }

        return new self($options);
    }

    public static function translate(
        AudioTranscribeModel $model = AudioTranscribeModel::WHISPER_1,
        ?bool $timestamps = null,
        ?float $temperature = null,
        ?string $responseFormat = null
    ): self {
        // For translation, only whisper models are supported, fallback to whisper-1 for unsupported models
        $modelValue = $model === AudioTranscribeModel::GPT_4O_MINI_TRANSCRIBE ? 'whisper-1' : $model->value;
        
        $options = [
            'model' => $modelValue,
        ];

        if ($timestamps === true) {
            $options['timestamp_granularities'] = ['word', 'segment'];
            $options['response_format'] = 'verbose_json';
        }

        if ($temperature !== null) {
            if ($temperature < 0.0 || $temperature > 1.0) {
                throw new \InvalidArgumentException('Temperature must be between 0.0 and 1.0');
            }
            $options['temperature'] = $temperature;
        }

        if ($responseFormat !== null) {
            $options['response_format'] = $responseFormat;
        }

        return new self($options);
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function getProvider(): string
    {
        return AIProvider::OPENAI->value;
    }
}
