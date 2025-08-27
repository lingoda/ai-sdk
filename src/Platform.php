<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk;

use Lingoda\AiSdk\Audio\AudioCapableInterface;
use Lingoda\AiSdk\Audio\AudioOptionsInterface;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\ModelNotFoundException;
use Lingoda\AiSdk\Exception\RuntimeException;
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\Prompt;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Result\BinaryResult;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\StreamResult;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Security\DataSanitizer;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class Platform implements PlatformInterface
{
    private ?DataSanitizer $sanitizer;
    private LoggerInterface $logger;
    
    /**
     * @param iterable<ClientInterface> $clients
     * @param bool $enableSanitization Enable automatic sanitization of sensitive data
     * @param DataSanitizer|null $sanitizer Custom sanitizer instance (if null, default will be created when enabled)
     * @param LoggerInterface|null $logger Logger for sanitization events
     * @param string|null $defaultProvider Default provider ID to use when no model is specified
     */
    public function __construct(
        private iterable $clients,
        private bool $enableSanitization = true,
        ?DataSanitizer $sanitizer = null,
        ?LoggerInterface $logger = null,
        private ?string $defaultProvider = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        
        if ($this->enableSanitization) {
            $this->sanitizer = $sanitizer ?? DataSanitizer::createDefault($this->logger);
        } else {
            $this->sanitizer = null;
        }
    }

    public function ask(
        string|Prompt|Conversation $input,
        ?string $model = null,
        array $options = []
    ): ResultInterface {
        $resolvedModel = $this->resolveModel($model);
        
        // Normalize input to UserPrompt or Conversation
        $normalizedInput = $this->normalizeInput($input);

        return $this->invoke($resolvedModel, $normalizedInput, $options);
    }

    public function getProvider(string $name): ProviderInterface
    {
        $clientsArray = is_array($this->clients) ? $this->clients : iterator_to_array($this->clients);

        foreach ($clientsArray as $client) {
            $provider = $client->getProvider();
            if ($provider->getId() === $name) {
                return $provider;
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Provider "%s" not found. Available providers: %s',
            $name,
            implode(', ', $this->getAvailableProviders())
        ));
    }

    public function getAvailableProviders(): array
    {
        $providers = [];
        $clientsArray = is_array($this->clients) ? $this->clients : iterator_to_array($this->clients);

        foreach ($clientsArray as $client) {
            $providers[] = $client->getProvider()->getId();
        }

        return array_unique($providers);
    }

    public function hasProvider(string $name): bool
    {
        try {
            $this->getProvider($name);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

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
    public function textToSpeech(string $input, AudioOptionsInterface $options): BinaryResult
    {
        $input = trim($input);
        if ($input === '') {
            throw new InvalidArgumentException('Text input cannot be empty for text-to-speech conversion.');
        }

        return $this->findAudioCapableClient($options)
            ->textToSpeech($input, $options)
        ;
    }

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
    public function textToSpeechStream(string $input, AudioOptionsInterface $options): StreamResult
    {
        $input = trim($input);
        if ($input === '') {
            throw new InvalidArgumentException('Text input cannot be empty for text-to-speech conversion.');
        }

        return $this->findAudioCapableClient($options)
            ->textToSpeechStream($input, $options)
        ;
    }

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
    public function transcribeAudio(string $audioFilePath, AudioOptionsInterface $options): TextResult
    {
        $audioStream = $this->createAudioStream($audioFilePath);

        return $this->findAudioCapableClient($options)
            ->speechToText($audioStream, $options)
        ;
    }

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
    public function translateAudio(string $audioFilePath, AudioOptionsInterface $options): TextResult
    {
        $audioStream = $this->createAudioStream($audioFilePath);

        return $this->findAudioCapableClient($options)
            ->translate($audioStream, $options)
        ;
    }

    public function configureProviderDefaultModel(string $providerName, string $defaultModel): void
    {
        $clients = is_array($this->clients) ? $this->clients : iterator_to_array($this->clients);

        foreach ($clients as $client) {
            $provider = $client->getProvider();
            if ($provider->getId() === $providerName) {
                $provider->setDefaultModel($defaultModel);
                break;
            }
        }
    }

    /**
     * @param array<string, mixed> $options Additional options for the request
     *
     * @throws RuntimeException|ClientException
     */
    private function invoke(ModelInterface $model, Prompt|Conversation $input, array $options = []): ResultInterface
    {
        $client = $this->findClientForModel($model);

        // Convert Prompt to Conversation if needed
        if ($input instanceof Conversation) {
            $conversation = $input;
        } elseif ($input instanceof UserPrompt) {
            $conversation = Conversation::fromUser($input);
        } else {
            // For other Prompt types, create UserPrompt from content
            $conversation = Conversation::fromUser(UserPrompt::create($input->getContent()));
        }

        // Sanitize conversation if enabled
        if ($this->enableSanitization && $this->sanitizer !== null) {
            $originalConversation = $conversation;
            $conversation = $conversation->sanitize($this->sanitizer);

            // Log sanitization if content was changed
            if (!$conversation->equals($originalConversation)) {
                $this->logger->info('Sensitive data sanitized in user prompt before API request', [
                    'provider' => $model->getProvider()->getId(),
                    'model' => $model->getId(),
                    'sanitization_applied' => true,
                    'conversation_hash' => $conversation->hash(),
                ]);
            }
        }

        // Convert conversation to appropriate payload format
        $payload = $conversation->toArray();

        return $client->request($model, $payload, $options);
    }

    /**
     * Resolve model based on model ID or default selection.
     *
     * @throws InvalidArgumentException if no model can be resolved
     * @throws ModelNotFoundException if the specified model is not found
     */
    private function resolveModel(?string $modelId): ModelInterface
    {
        $clients = is_array($this->clients) ? $this->clients : iterator_to_array($this->clients);
        
        if ($modelId === null) {
            // No model specified - use default behavior
            if (count($clients) === 1) {
                // Single client - use its default model
                $client = reset($clients);
                $defaultModelId = $client->getProvider()->getDefaultModel();

                return $client->getProvider()->getModel($defaultModelId);
            }
            
            // Multiple clients - try to use the default provider if configured
            if ($this->defaultProvider !== null) {
                foreach ($clients as $client) {
                    $provider = $client->getProvider();
                    if ($provider->getId() === $this->defaultProvider) {
                        $defaultModelId = $provider->getDefaultModel();

                        return $provider->getModel($defaultModelId);
                    }
                }
                
                throw new InvalidArgumentException(
                    "Default provider '{$this->defaultProvider}' not found in configured clients. " .
                    "Available providers: " . implode(', ', $this->getAvailableProviders())
                );
            }
            
            throw new InvalidArgumentException(
                'Multiple providers configured. Must specify model parameter or configure the default provider. Available models: ' .
                implode(', ', $this->getAvailableModels())
            );
        }

        // Model specified - find the client that supports it
        foreach ($clients as $client) {
            try {
                $model = $client->getProvider()->getModel($modelId);
                if ($client->supports($model)) {
                    return $model;
                }
            } catch (\Exception) {
                // This client doesn't support the model, continue to next
                continue;
            }
        }

        throw new ModelNotFoundException(sprintf(
            'Model "%s" not available in configured providers. Available models: %s',
            $modelId,
            implode(', ', $this->getAvailableModels())
        ));
    }

    /**
     * Normalize input to UserPrompt or Conversation.
     */
    private function normalizeInput(string|Prompt|Conversation $input): UserPrompt|Conversation
    {
        if (is_string($input)) {
            return UserPrompt::create($input);
        }
        
        if ($input instanceof Conversation) {
            return $input;
        }
        
        if ($input instanceof UserPrompt) {
            return $input;
        }
        
        // For other Prompt types (SystemPrompt, AssistantPrompt), wrap in Conversation
        return Conversation::fromUser(UserPrompt::create($input->getContent()));
    }

    /**
     * Get a list of available models from all configured clients.
     *
     * @return array<string>
     */
    private function getAvailableModels(): array
    {
        $models = [];
        $clients = is_array($this->clients) ? $this->clients : iterator_to_array($this->clients);
        
        foreach ($clients as $client) {
            $provider = $client->getProvider();
            $models = array_merge($models, $provider->getAvailableModels());
        }
        
        return array_unique($models);
    }

    /**
     * @throws RuntimeException
     */
    private function findClientForModel(ModelInterface $model): ClientInterface
    {
        foreach ($this->clients as $client) {
            if ($client->supports($model)) {
                return $client;
            }
        }

        throw new RuntimeException(sprintf(
            'No client found that supports model "%s" from provider "%s"',
            $model->getId(),
            $model->getProvider()->getId(),
        ));
    }

    /**
     * Find the first audio-capable client from configured clients that supports the given options.
     *
     * @throws RuntimeException If no audio-capable clients are available
     * @throws InvalidArgumentException If no client supports the audio options provider
     */
    private function findAudioCapableClient(AudioOptionsInterface $options): AudioCapableInterface
    {
        $audioClients = [];

        foreach ($this->clients as $client) {
            if ($client instanceof AudioCapableInterface) {
                $audioClients[] = $client;

                // Check if this client supports the options provider
                if ($client->getProvider()->getId() === $options->getProvider()) {
                    return $client;
                }
            }
        }

        if (empty($audioClients)) {
            throw new RuntimeException(sprintf(
                'No audio-capable clients found. Audio processing requires a client that implements %s. ' .
                'Available providers: %s. Currently, only OpenAI supports audio processing.',
                AudioCapableInterface::class,
                implode(', ', $this->getAvailableProviders())
            ));
        }
        
        // We have audio clients but none support these options
        $availableAudioProviders = array_map(
            static fn (AudioCapableInterface $client) => $client->getProvider()->getId(),
            $audioClients
        );
        
        throw new InvalidArgumentException(sprintf(
            'Audio options provider mismatch. Available audio providers: %s, but options are for "%s"',
            implode(', ', array_unique($availableAudioProviders)),
            $options->getProvider()
        ));
    }

    /**
     * Create a stream from an audio file path with validation.
     *
     * @throws InvalidArgumentException If the file doesn't exist, is not readable, or exceeds size limits
     */
    private function createAudioStream(string $audioFilePath): StreamInterface
    {
        if (!file_exists($audioFilePath)) {
            throw new InvalidArgumentException(sprintf(
                'Audio file not found: %s',
                $audioFilePath
            ));
        }

        if (!is_readable($audioFilePath)) {
            throw new InvalidArgumentException(sprintf(
                'Audio file is not readable: %s',
                $audioFilePath
            ));
        }

        $fileSize = filesize($audioFilePath);
        if ($fileSize === false) {
            throw new InvalidArgumentException(sprintf(
                'Could not determine file size for: %s',
                $audioFilePath
            ));
        }

        // OpenAI has a 25MB limit for audio files
        $maxSize = 25 * 1024 * 1024; // 25MB
        if ($fileSize > $maxSize) {
            throw new InvalidArgumentException(sprintf(
                'Audio file too large: %s (%.2f MB). Maximum size is 25 MB.',
                $audioFilePath,
                $fileSize / 1024 / 1024
            ));
        }

        $resource = fopen($audioFilePath, 'rb');
        if ($resource === false) {
            throw new InvalidArgumentException(sprintf(
                'Could not open audio file: %s',
                $audioFilePath
            ));
        }

        return Stream::create($resource);
    }
}
