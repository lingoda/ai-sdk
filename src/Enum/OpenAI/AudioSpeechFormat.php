<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Enum\OpenAI;

enum AudioSpeechFormat: string
{
    case MP3 = 'mp3';
    case OPUS = 'opus';
    case AAC = 'aac';
    case FLAC = 'flac';
    case WAV = 'wav';
    case PCM = 'pcm';

    /**
     * Get the MIME type for this audio format.
     */
    public function getMimeType(): string
    {
        return match ($this) {
            self::MP3 => 'audio/mpeg',
            self::OPUS => 'audio/opus',
            self::AAC => 'audio/aac',
            self::FLAC => 'audio/flac',
            self::WAV => 'audio/wav',
            self::PCM => 'audio/pcm',
        };
    }
}
