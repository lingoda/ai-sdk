<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Enum\OpenAI;

use Lingoda\AiSdk\Enum\Capability;

enum AudioTranscribeModel: string
{
    case WHISPER_1 = 'whisper-1';
    case GPT_4O_MINI_TRANSCRIBE = 'gpt-4o-mini-transcribe';
    case GPT_4O_TRANSCRIBE = 'gpt-4o-transcribe';

    /**
     * Get the maximum file size in bytes (25 MB for Whisper).
     */
    public function getMaxFileSize(): int
    {
        return match($this) {
            self::WHISPER_1,
            self::GPT_4O_MINI_TRANSCRIBE,
            self::GPT_4O_TRANSCRIBE => 25 * 1024 * 1024, // 25 MB
        };
    }

    /**
     * Get supported audio file formats.
     *
     * @return array<string>
     */
    public function getSupportedFormats(): array
    {
        return [
            'flac',
            'mp3',
            'mp4',
            'mpeg',
            'mpga',
            'm4a',
            'ogg',
            'wav',
            'webm',
        ];
    }

    /**
     * Get supported languages (ISO 639-1 codes).
     *
     * @return array<string>
     */
    public function getSupportedLanguages(): array
    {
        return [
            'af', 'ar', 'hy', 'az', 'be', 'bs', 'bg', 'ca', 'zh', 'hr', 'cs', 'da', 'nl',
            'en', 'et', 'fi', 'fr', 'gl', 'de', 'el', 'he', 'hi', 'hu', 'is', 'id', 'it',
            'ja', 'kn', 'kk', 'ko', 'lv', 'lt', 'mk', 'ms', 'mr', 'mi', 'ne', 'no', 'fa',
            'pl', 'pt', 'ro', 'ru', 'sr', 'sk', 'sl', 'es', 'sw', 'sv', 'tl', 'ta', 'th',
            'tr', 'uk', 'ur', 'vi', 'cy',
        ];
    }

    /**
     * Get the maximum duration in seconds.
     */
    public function getMaxDuration(): int
    {
        return match($this) {
            self::WHISPER_1,
            self::GPT_4O_MINI_TRANSCRIBE,
            self::GPT_4O_TRANSCRIBE => 3600, // 1 hour
        };
    }

    /**
     * Get the capabilities supported by this model.
     *
     * @return array<Capability>
     */
    public function getCapabilities(): array
    {
        return match($this) {
            self::WHISPER_1 => [
                Capability::AUDIO_TRANSCRIPTION,
                Capability::AUDIO_TRANSLATION,
                Capability::AUDIO_TIMESTAMPS,
            ],
            self::GPT_4O_MINI_TRANSCRIBE, self::GPT_4O_TRANSCRIBE => [
                Capability::AUDIO_TRANSCRIPTION, // Limited parameter surface
            ],
        };
    }

    /**
     * Check if the model supports a specific capability.
     */
    public function hasCapability(Capability $capability): bool
    {
        return in_array($capability, $this->getCapabilities(), true);
    }

    /**
     * Get supported response formats for the model.
     * 
     * @return array<string>
     */
    public function getSupportedResponseFormats(): array
    {
        return match($this) {
            self::WHISPER_1 => ['json', 'text', 'srt', 'verbose_json', 'vtt'],
            self::GPT_4O_MINI_TRANSCRIBE,
            self::GPT_4O_TRANSCRIBE => ['json', 'text'], // Limited parameter surface
        };
    }
}