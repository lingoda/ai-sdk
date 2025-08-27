<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Enum;

enum Capability: string
{
    case TEXT = 'text';
    case TOOLS = 'tools';
    case VISION = 'vision';
    case MULTIMODAL = 'multimodal';
    case REASONING = 'reasoning';
    case AUDIO = 'audio';
    case STREAMING = 'streaming';
    case AUDIO_TRANSCRIPTION = 'audio_transcription';
    case AUDIO_TRANSLATION = 'audio_translation';
    case AUDIO_TIMESTAMPS = 'audio_timestamps';
}
