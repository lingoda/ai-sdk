<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk;

use Lingoda\AiSdk\Audio\AudioOptionsInterface;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\ModelNotFoundException;
use Lingoda\AiSdk\Exception\RuntimeException;
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\Prompt;
use Lingoda\AiSdk\Provider\ProviderCollection;
use Lingoda\AiSdk\Result\BinaryResult;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\StreamResult;
use Lingoda\AiSdk\Result\TextResult;

interface PlatformInterface
{
    /**
     * Simple ask method for common use cases.
     * Automatically resolves models and converts string input to UserPrompt.
     *
     * @param string|Prompt|Conversation $input The input to send to the AI model
     * @param string|null $model Optional model ID to use (if null, uses client's default)
     * @param array<string, mixed> $options Additional options for the request
     *
     * @throws ClientException|InvalidArgumentException|ModelNotFoundException|RuntimeException
     */
    public function ask(string|Prompt|Conversation $input, ?string $model = null, array $options = []): ResultInterface;

    /**
     * Convert text to speech using an audio-capable client (buffered response).
     *
     * @param string $input Text to convert to speech
     * @param AudioOptionsInterface $options Audio generation options (model, voice, format, speed, etc.)
     *
     * @throws InvalidArgumentException If input is empty or options provider doesn't match any client
     * @throws RuntimeException If no audio-capable clients are available
     * @throws ClientException If the audio generation request fails
     * @return BinaryResult Audio data with mime type information
     */
    public function textToSpeech(string $input, AudioOptionsInterface $options): BinaryResult;

    /**
     * Convert text to speech using an audio-capable client (streaming response).
     *
     * @param string $input Text to convert to speech
     * @param AudioOptionsInterface $options Audio generation options (model, voice, format, speed, etc.)
     *
     * @throws InvalidArgumentException If input is empty or options provider doesn't match any client
     * @throws RuntimeException If no audio-capable clients are available
     * @throws ClientException If the audio generation request fails
     * @return StreamResult Audio stream for real-time processing
     */
    public function textToSpeechStream(string $input, AudioOptionsInterface $options): StreamResult;

    /**
     * Transcribe audio file to text.
     *
     * @param string $audioFilePath Path to the audio file to transcribe
     * @param AudioOptionsInterface $options Transcription options (model, language, temperature, etc.)
     *
     * @throws InvalidArgumentException If file doesn't exist, is invalid, or options provider doesn't match any client
     * @throws RuntimeException If no audio-capable clients are available
     * @throws ClientException If the transcription request fails
     * @return TextResult Transcribed text with metadata
     */
    public function transcribeAudio(string $audioFilePath, AudioOptionsInterface $options): TextResult;

    /**
     * Translate audio file to English text.
     *
     * @param string $audioFilePath Path to the audio file to translate
     * @param AudioOptionsInterface $options Translation options (model, temperature, etc.)
     *
     * @throws InvalidArgumentException If file doesn't exist, is invalid, or options provider doesn't match any client
     * @throws RuntimeException If no audio-capable clients are available
     * @throws ClientException If the translation request fails
     * @return TextResult Translated text with metadata
     */
    public function translateAudio(string $audioFilePath, AudioOptionsInterface $options): TextResult;

    /**
     * @throws InvalidArgumentException
     */
    public function getProvider(string $name): ProviderInterface;

    /**
     * Get a collection of all available providers.
     */
    public function getAvailableProviders(): ProviderCollection;

    public function hasProvider(string $name): bool;

    /**
     * Configure the default model for a specific provider.
     */
    public function configureProviderDefaultModel(string $providerName, string $defaultModel): void;
}
