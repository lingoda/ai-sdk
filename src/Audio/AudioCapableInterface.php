<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Audio;

use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Result\BinaryResult;
use Lingoda\AiSdk\Result\StreamResult;
use Lingoda\AiSdk\Result\TextResult;
use Psr\Http\Message\StreamInterface;

/**
 * Interface for clients that support audio processing capabilities.
 * Provider-agnostic interface that can be implemented by any AI provider.
 */
interface AudioCapableInterface
{
    /**
     * Convert text to speech (buffered response).
     *
     * @param string $input Text to convert to speech
     * @param AudioOptionsInterface $options Audio generation options (format, voice, model, etc.)
     *
     * @throws ClientException
     */
    public function textToSpeech(string $input, AudioOptionsInterface $options): BinaryResult;

    /**
     * Convert text to speech (streaming response).
     *
     * @param string $input Text to convert to speech
     * @param AudioOptionsInterface $options Audio generation options (format, voice, model, etc.)
     *
     * @throws ClientException
     */
    public function textToSpeechStream(string $input, AudioOptionsInterface $options): StreamResult;

    /**
     * Convert speech to text (transcription).
     *
     * @param StreamInterface $audioStream Audio stream to transcribe
     * @param AudioOptionsInterface $options Transcription options (model, language, timestamps, etc.)
     *
     * @throws ClientException
     */
    public function speechToText(StreamInterface $audioStream, AudioOptionsInterface $options): TextResult;

    /**
     * Translate speech to English.
     *
     * @param StreamInterface $audioStream Audio stream to translate
     * @param AudioOptionsInterface $options Translation options (model, timestamps, etc.)
     *
     * @throws ClientException
     */
    public function translate(StreamInterface $audioStream, AudioOptionsInterface $options): TextResult;
}