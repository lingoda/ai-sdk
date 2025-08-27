<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Enum\OpenAI;

enum AudioSpeechModel: string
{
    case TTS_1 = 'tts-1';
    case TTS_1_HD = 'tts-1-hd';

    /**
     * Get the maximum characters per request.
     */
    public function getMaxCharacters(): int
    {
        return match ($this) {
            self::TTS_1, self::TTS_1_HD => 4096,
        };
    }

    /**
     * Get the supported audio formats for this model.
     *
     * @return array<AudioSpeechFormat>
     */
    public function getSupportedFormats(): array
    {
        return [
            AudioSpeechFormat::MP3,
            AudioSpeechFormat::OPUS,
            AudioSpeechFormat::AAC,
            AudioSpeechFormat::FLAC,
            AudioSpeechFormat::WAV,
            AudioSpeechFormat::PCM,
        ];
    }

    /**
     * Get the supported voices for this model.
     *
     * @return array<AudioSpeechVoice>
     */
    public function getSupportedVoices(): array
    {
        return [
            AudioSpeechVoice::ALLOY,
            AudioSpeechVoice::ECHO,
            AudioSpeechVoice::FABLE,
            AudioSpeechVoice::ONYX,
            AudioSpeechVoice::NOVA,
            AudioSpeechVoice::SHIMMER,
        ];
    }
}
