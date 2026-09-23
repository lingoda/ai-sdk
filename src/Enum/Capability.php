<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Enum;

enum Capability: string
{
    case TEXT = 'text';
    case TOOLS = 'tools';
    case VISION = 'vision';
    /** PDF input. Checked by Platform when a prompt carries a PDF attachment. */
    case DOCUMENT = 'document';
    /** Legacy declaration, not used for validation. Use VISION (images) and DOCUMENT (PDFs). */
    case MULTIMODAL = 'multimodal';
    case REASONING = 'reasoning';
    case AUDIO = 'audio';
    case STREAMING = 'streaming';
    case AUDIO_TRANSCRIPTION = 'audio_transcription';
    case AUDIO_TRANSLATION = 'audio_translation';
    case AUDIO_TIMESTAMPS = 'audio_timestamps';
}
