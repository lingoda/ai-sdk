<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Client\Anthropic;

use Anthropic\Client as AnthropicAPIClient;
use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Converter\Anthropic\AnthropicResultConverter;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Provider\AnthropicProvider;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\ResultInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class AnthropicClient implements ClientInterface
{
    private ?AnthropicResultConverter $resultConverter = null;
    private ?AnthropicProvider $provider = null;
    
    public function __construct(
        private readonly AnthropicAPIClient $client,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function supports(ModelInterface $model): bool
    {
        return $model->getProvider()->is(AIProvider::ANTHROPIC);
    }

    public function request(ModelInterface $model, array|string $payload, array $options = []): ResultInterface
    {
        try {
            $requestPayload = $this->buildChatPayload($model, $payload, $options);
            $response = $this->client->messages()->create($requestPayload);
            
            return $this->getResultConverter()->convert($model, $response);
        } catch (\Throwable $e) {
            $this->logger->error('Anthropic request failed', [
                'exception' => $e,
                'model' => $model->getId(),
                'payload_type' => gettype($payload),
            ]);

            throw new ClientException(
                sprintf('Anthropic request failed: %s', $e->getMessage()),
                previous: $e
            );
        }
    }

    public function getProvider(): ProviderInterface
    {
        return $this->provider ??= new AnthropicProvider();
    }

    private function getResultConverter(): AnthropicResultConverter
    {
        return $this->resultConverter ??= new AnthropicResultConverter();
    }

    /**
     * Build chat payload with proper Anthropic message structure.
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
        $systemPrompt = null;
        
        if (is_string($payload)) {
            // Simple user prompt
            $messages[] = ['role' => 'user', 'content' => $payload];
        } else {
            // Check if payload is already an array of message objects (from Conversation::toArray())
            if (isset($payload[0]) && is_array($payload[0]) && isset($payload[0]['role'])) {
                // Payload is an array of message objects, separate system from user/assistant messages
                foreach ($payload as $message) {
                    if ($message['role'] === 'system') {
                        $systemPrompt = $message['content'];
                    } else {
                        $messages[] = $message;
                    }
                }
            } elseif (isset($payload['messages']) && is_array($payload['messages'])) {
                // If messages array is provided directly, use it
                $messages = $payload['messages'];
                if (!empty($payload['system'])) {
                    $systemPrompt = $payload['system'];
                }
            } else {
                // Structured payload with system/user/assistant messages
                if (!empty($payload['user'])) {
                    $messages[] = ['role' => 'user', 'content' => $payload['user']];
                }
                
                if (!empty($payload['assistant'])) {
                    $messages[] = ['role' => 'assistant', 'content' => $payload['assistant']];
                }
                
                // Anthropic uses separate system parameter, not in messages array
                if (!empty($payload['system'])) {
                    $systemPrompt = $payload['system'];
                }
            }
            
            // Throw exception if no valid messages found
            if (empty($messages)) {
                throw new InvalidArgumentException('No valid messages found in payload. Payload must contain user message.');
            }
        }

        // Start with model defaults, then merge options
        $modelOptions = $model->getOptions();
        $requestPayload = array_merge($modelOptions, $options, [
            'model' => $model->getId(),
            'messages' => $messages,
        ]);

        // Add system prompt if provided (Anthropic uses separate 'system' field)
        if ($systemPrompt) {
            $requestPayload['system'] = $systemPrompt;
        }

        // Set default max_tokens if not specified
        if (!isset($requestPayload['max_tokens'])) {
            $requestPayload['max_tokens'] = min(4096, $model->getMaxTokens());
        }

        return $requestPayload;
    }
}
