<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Client\Gemini;

use Gemini\Client as GeminiAPIClient;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Data\Part;
use Gemini\Enums\Role;
use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Converter\Gemini\GeminiResultConverter;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Provider\GeminiProvider;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\ResultInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Webmozart\Assert\Assert;

final class GeminiClient implements ClientInterface
{
    private ?GeminiResultConverter $resultConverter = null;
    private ?GeminiProvider $provider = null;
    
    public function __construct(
        private readonly GeminiAPIClient $client,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function supports(ModelInterface $model): bool
    {
        return $model->getProvider()->is(AIProvider::GEMINI);
    }

    public function request(ModelInterface $model, array|string $payload, array $options = []): ResultInterface
    {
        try {
            $requestData = $this->buildChatPayload($model, $payload, $options);
            
            $generativeModel = $this->client->generativeModel($model->getId());
            
            // Apply generation config if provided
            if (isset($requestData['generationConfig'])) {
                Assert::isInstanceOf($requestData['generationConfig'], GenerationConfig::class);
                $generativeModel = $generativeModel->withGenerationConfig($requestData['generationConfig']);
            }
            
            // Apply system instruction if provided
            if (isset($requestData['systemInstruction'])) {
                Assert::isInstanceOf($requestData['systemInstruction'], Content::class);
                $generativeModel = $generativeModel->withSystemInstruction($requestData['systemInstruction']);
            }

            Assert::isArray($requestData['contents']);
            // Ensure all contents are Content objects for safe unpacking
            $contentObjects = array_filter($requestData['contents'], fn ($content) => $content instanceof Content);
            $response = $generativeModel->generateContent(...$contentObjects);
            
            return $this->getResultConverter()->convert($model, $response);
        } catch (\Throwable $e) {
            $this->logger->error('Gemini request failed', [
                'exception' => $e,
                'model' => $model->getId(),
                'payload_type' => gettype($payload),
            ]);

            throw new ClientException(
                sprintf('Gemini request failed: %s', $e->getMessage()),
                previous: $e
            );
        }
    }

    public function getProvider(): ProviderInterface
    {
        return $this->provider ??= new GeminiProvider();
    }

    private function getResultConverter(): GeminiResultConverter
    {
        return $this->resultConverter ??= new GeminiResultConverter();
    }

    /**
     * Build chat payload with proper Gemini message structure.
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
        $contents = [];
        $systemInstruction = null;
        
        if (is_string($payload)) {
            // Simple user prompt
            $contents[] = new Content(
                parts: [new Part(text: $payload)],
                role: Role::USER
            );
        } else {
            // Check for contents array first (Gemini format has priority)
            if (isset($payload['contents']) && is_array($payload['contents'])) {
                // If contents array is provided directly (Gemini format), convert to Content objects if needed
                foreach ($payload['contents'] as $content) {
                    if ($content instanceof Content) {
                        $contents[] = $content;
                    } elseif (is_array($content) && isset($content['parts'], $content['role'])) {
                        // Convert array format to Content object
                        $parts = [];
                        foreach ($content['parts'] as $partData) {
                            if (is_array($partData) && isset($partData['text']) && is_string($partData['text'])) {
                                $parts[] = new Part(text: $partData['text']);
                            }
                        }
                        $role = $content['role'] === 'user' ? Role::USER : Role::MODEL;
                        $contents[] = new Content(parts: $parts, role: $role);
                    }
                }
            } elseif (isset($payload[0]) && is_array($payload[0]) && isset($payload[0]['role'])) {
                // Check if payload is already an array of message objects (from Conversation::toArray())
                foreach ($payload as $message) {
                    if ($message['role'] === 'system') {
                        $systemInstruction = new Content(
                            parts: [new Part(text: $message['content'])]
                        );
                    } else {
                        $role = $message['role'] === 'assistant' ? Role::MODEL : Role::USER; // Gemini uses MODEL for assistant
                        $contents[] = new Content(
                            parts: [new Part(text: $message['content'])],
                            role: $role
                        );
                    }
                }
            } elseif (isset($payload['messages']) && is_array($payload['messages'])) {
                // If messages array is provided directly, use it
                foreach ($payload['messages'] as $message) {
                    if (!is_array($message)) {
                        continue;
                    }
                    
                    if ($message['role'] === 'system') {
                        $systemInstruction = new Content(
                            parts: [new Part(text: $message['content'])]
                        );
                    } else {
                        $role = $message['role'] === 'assistant' ? Role::MODEL : Role::USER;
                        $contents[] = new Content(
                            parts: [new Part(text: $message['content'])],
                            role: $role
                        );
                    }
                }
            } else {
                // Structured payload with system/user/assistant messages
                if (!empty($payload['user'])) {
                    $contents[] = new Content(
                        parts: [new Part(text: $payload['user'])],
                        role: Role::USER
                    );
                }
                
                if (!empty($payload['assistant'])) {
                    $contents[] = new Content(
                        parts: [new Part(text: $payload['assistant'])],
                        role: Role::MODEL  // Gemini uses MODEL for assistant role
                    );
                }
                
                // Gemini uses systemInstruction separately
                if (!empty($payload['system'])) {
                    $systemInstruction = new Content(
                        parts: [new Part(text: $payload['system'])]
                    );
                }
            }
            
            // Throw exception if no valid contents found
            if (empty($contents)) {
                throw new InvalidArgumentException('No valid contents found in payload. Payload must contain user message.');
            }
        }

        // Start with request data
        $requestData = [
            'contents' => $contents,
        ];

        // Build generation config from model options and request options
        $modelOptions = $model->getOptions();
        $generationConfigParams = [];
        
        // Use max_tokens from options or model default
        if (isset($options['max_tokens']) && is_int($options['max_tokens'])) {
            $generationConfigParams['maxOutputTokens'] = $options['max_tokens'];
        } elseif (isset($modelOptions['maxOutputTokens']) && is_int($modelOptions['maxOutputTokens'])) {
            $generationConfigParams['maxOutputTokens'] = $modelOptions['maxOutputTokens'];
        } else {
            $generationConfigParams['maxOutputTokens'] = min(4096, $model->getMaxTokens());
        }

        // Use temperature from options or model default
        if (isset($options['temperature']) && (is_float($options['temperature']) || is_int($options['temperature']))) {
            $generationConfigParams['temperature'] = (float) $options['temperature'];
        } elseif (isset($modelOptions['temperature']) && (is_float($modelOptions['temperature']) || is_int($modelOptions['temperature']))) {
            $generationConfigParams['temperature'] = (float) $modelOptions['temperature'];
        }

        // Use topP from options or model default
        if (isset($options['top_p']) && (is_float($options['top_p']) || is_int($options['top_p']))) {
            $generationConfigParams['topP'] = (float) $options['top_p'];
        } elseif (isset($modelOptions['topP']) && (is_float($modelOptions['topP']) || is_int($modelOptions['topP']))) {
            $generationConfigParams['topP'] = (float) $modelOptions['topP'];
        }

        // Use topK from options or model default
        if (isset($options['top_k']) && is_int($options['top_k'])) {
            $generationConfigParams['topK'] = $options['top_k'];
        } elseif (isset($modelOptions['topK']) && is_int($modelOptions['topK'])) {
            $generationConfigParams['topK'] = $modelOptions['topK'];
        }

        if (count($generationConfigParams) > 0) {
            $requestData['generationConfig'] = new GenerationConfig(...$generationConfigParams);
        }

        // Add system instruction if provided
        if ($systemInstruction) {
            $requestData['systemInstruction'] = $systemInstruction;
        }

        return $requestData;
    }
}
