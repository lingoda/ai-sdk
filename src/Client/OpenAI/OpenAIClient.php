<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Client\OpenAI;

use Lingoda\AiSdk\Audio\AudioCapableInterface;
use Lingoda\AiSdk\Audio\AudioOptionsInterface;
use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Converter\OpenAI\OpenAIResultConverter;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechFormat;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\BinaryResult;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\StreamResult;
use Lingoda\AiSdk\Result\TextResult;
use OpenAI\Client as OpenAIAPIClient;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class OpenAIClient implements ClientInterface, AudioCapableInterface
{
    private ?OpenAIResultConverter $resultConverter = null;
    private ?OpenAIProvider $provider = null;
    
    public function __construct(
        private readonly OpenAIAPIClient $client,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function supports(ModelInterface $model): bool
    {
        return $model->getProvider()->is(AIProvider::OPENAI);
    }

    public function request(ModelInterface $model, array|string $payload, array $options = []): ResultInterface
    {
        try {
            $requestPayload = $this->buildChatPayload($model, $payload, $options);
            $response = $this->client->chat()->create($requestPayload);
            
            return $this->getResultConverter()->convert($model, $response);
        } catch (\Throwable $e) {
            $this->logger->error('OpenAI chat request failed', [
                'exception' => $e,
                'model' => $model->getId(),
                'payload_type' => gettype($payload),
            ]);

            throw new ClientException(
                sprintf('OpenAI request failed: %s', $e->getMessage()),
                previous: $e
            );
        }
    }

    public function getProvider(): ProviderInterface
    {
        return $this->provider ??= new OpenAIProvider();
    }

    public function textToSpeech(string $input, AudioOptionsInterface $options): BinaryResult
    {
        try {
            $payload = array_merge($options->toArray(), ['input' => $input]);
            $response = $this->client->audio()->speech($payload);
            
            $responseFormat = $payload['response_format'] ?? AudioSpeechFormat::MP3->value;
            $format = is_string($responseFormat) ?
                (AudioSpeechFormat::tryFrom($responseFormat) ?? AudioSpeechFormat::MP3) :
                AudioSpeechFormat::MP3;
            
            return new BinaryResult($response, $format->getMimeType());
        } catch (\Throwable $e) {
            $this->logger->error('OpenAI text-to-speech request failed', [
                'exception' => $e,
                'input_length' => mb_strlen($input),
            ]);

            throw new ClientException(
                sprintf('OpenAI text-to-speech request failed: %s', $e->getMessage()),
                previous: $e
            );
        }
    }

    public function textToSpeechStream(string $input, AudioOptionsInterface $options): StreamResult
    {
        try {
            $payload = array_merge($options->toArray(), ['input' => $input]);
            $streamResponse = $this->client->audio()->speechStreamed($payload);

            $responseFormat = $payload['response_format'] ?? AudioSpeechFormat::MP3->value;
            $format = is_string($responseFormat) ?
                (AudioSpeechFormat::tryFrom($responseFormat) ?? AudioSpeechFormat::MP3) :
                AudioSpeechFormat::MP3;

            // Convert OpenAI's SpeechStreamResponse to our StreamResult
            // The OpenAI response implements an iterator, we need to get the underlying stream
            $reflection = new \ReflectionClass($streamResponse);
            $property = $reflection->getProperty('response');
            $property->setAccessible(true);
            /** @var \Psr\Http\Message\ResponseInterface $response */
            $response = $property->getValue($streamResponse);

            return new StreamResult($response->getBody(), $format->getMimeType());
        } catch (\Throwable $e) {
            $this->logger->error('OpenAI text-to-speech streaming request failed', [
                'exception' => $e,
                'input_length' => mb_strlen($input),
            ]);

            throw new ClientException(
                sprintf('OpenAI text-to-speech streaming request failed: %s', $e->getMessage()),
                previous: $e
            );
        }
    }

    public function speechToText(StreamInterface $audioStream, AudioOptionsInterface $options): TextResult
    {
        try {
            $payload = array_merge($options->toArray(), ['file' => $audioStream]);
            $response = $this->client->audio()->transcribe($payload);
            
            return new TextResult($response->text);
        } catch (\Throwable $e) {
            $this->logger->error('OpenAI speech-to-text request failed', [
                'exception' => $e,
            ]);

            throw new ClientException(
                sprintf('OpenAI speech-to-text request failed: %s', $e->getMessage()),
                previous: $e
            );
        }
    }

    public function translate(StreamInterface $audioStream, AudioOptionsInterface $options): TextResult
    {
        try {
            $payload = array_merge($options->toArray(), ['file' => $audioStream]);
            $response = $this->client->audio()->translate($payload);
            
            return new TextResult($response->text);
        } catch (\Throwable $e) {
            $this->logger->error('OpenAI speech translation request failed', [
                'exception' => $e,
            ]);

            throw new ClientException(
                sprintf('OpenAI speech translation request failed: %s', $e->getMessage()),
                previous: $e
            );
        }
    }

    private function getResultConverter(): OpenAIResultConverter
    {
        return $this->resultConverter ??= new OpenAIResultConverter();
    }

    /**
     * Build chat payload with proper message structure.
     *
     * @param array<string, mixed>|array<int, array{role: string, content: string}>|string $payload
     * @param array<string, mixed> $options
     *
     * @throws InvalidArgumentException
     *
     * @return array<string, mixed>
     */
    private function buildChatPayload(ModelInterface $model, array|string $payload, array $options): array
    {
        $messages = [];

        if (is_string($payload)) {
            // Simple user prompt
            $messages[] = ['role' => 'user', 'content' => $payload];
        } else {
            // Check if payload is already an array of message objects (from Conversation::toArray())
            if (isset($payload[0]) && is_array($payload[0]) && isset($payload[0]['role'])) {
                // Payload is an array of message objects, use directly
                $messages = $payload;
            } elseif (isset($payload['messages']) && is_array($payload['messages'])) {
                // If messages array is provided directly, use it
                $messages = $payload['messages'];
            } else {
                // Structured payload with system/user/assistant messages
                if (!empty($payload['system'])) {
                    $messages[] = ['role' => 'system', 'content' => $payload['system']];
                }

                if (!empty($payload['user'])) {
                    $messages[] = ['role' => 'user', 'content' => $payload['user']];
                }

                if (!empty($payload['assistant'])) {
                    $messages[] = ['role' => 'assistant', 'content' => $payload['assistant']];
                }
            }

            // Throw exception if no valid messages found (must have at least user or assistant message)
            $hasValidMessage = false;
            foreach ($messages as $message) {
                if (is_array($message) && isset($message['role']) && in_array($message['role'], ['user', 'assistant'], true)) {
                    $hasValidMessage = true;
                    break;
                }
            }
            if (!$hasValidMessage) {
                throw new InvalidArgumentException('No valid messages found in payload. Payload must contain user message.');
            }
        }

        // Start with model defaults, then merge options
        $modelOptions = $model->getOptions();
        $requestPayload = array_merge($modelOptions, $options, [
            'model' => $model->getId(),
            'messages' => $messages,
        ]);

        // Set default max_tokens if not specified
        if (!isset($requestPayload['max_tokens'])) {
            $requestPayload['max_tokens'] = min(4096, $model->getMaxTokens());
        }

        return $requestPayload;
    }
}
