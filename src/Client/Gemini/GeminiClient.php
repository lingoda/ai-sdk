<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Client\Gemini;

use Gemini\Client as GeminiAPIClient;
use Gemini\Data\Content;
use Gemini\Data\DataFormat;
use Gemini\Data\GenerationConfig;
use Gemini\Data\Part;
use Gemini\Data\Schema;
use Gemini\Enums\DataType;
use Gemini\Enums\ResponseMimeType;
use Gemini\Enums\Role;
use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Converter\Gemini\GeminiResultConverter;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Provider\GeminiProvider;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\ObjectResult;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\TextResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Webmozart\Assert\Assert;

final class GeminiClient implements ClientInterface
{
    private const RESPONSE_SCHEMA_KEYS = [
        'type', 'format', 'description', 'nullable', 'enum', 'maxItems', 'minItems',
        'properties', 'required', 'propertyOrdering', 'items', 'title', 'minProperties',
        'maxProperties', 'minLength', 'maxLength', 'pattern', 'example', 'anyOf',
        'default', 'minimum', 'maximum',
    ];

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
            $generationConfig = null;
            if (isset($requestData['generationConfig'])) {
                Assert::isInstanceOf($requestData['generationConfig'], GenerationConfig::class);
                $generationConfig = $requestData['generationConfig'];
                $generativeModel = $generativeModel->withGenerationConfig($generationConfig);
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

            $result = $this->getResultConverter()->convert($model, $response);

            if ($generationConfig?->responseMimeType === ResponseMimeType::APPLICATION_JSON) {
                return $this->decodeJsonResult($result);
            }

            return $result;
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
                    } elseif (is_array($content) && isset($content['parts'], $content['role']) && is_array($content['parts'])) {
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
                /** @var array{role: string, content: string} $message */
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
                    if (!is_array($message) || !isset($message['role'], $message['content'])) {
                        continue;
                    }

                    Assert::string($message['role']);
                    Assert::string($message['content']);

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
                if (!empty($payload['user']) && is_string($payload['user'])) {
                    $contents[] = new Content(
                        parts: [new Part(text: $payload['user'])],
                        role: Role::USER
                    );
                }

                if (!empty($payload['assistant']) && is_string($payload['assistant'])) {
                    $contents[] = new Content(
                        parts: [new Part(text: $payload['assistant'])],
                        role: Role::MODEL  // Gemini uses MODEL for assistant role
                    );
                }

                // Gemini uses systemInstruction separately
                if (!empty($payload['system']) && is_string($payload['system'])) {
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

        // Use responseMimeType from options or model default
        if (isset($options['response_mime_type'])) {
            $generationConfigParams['responseMimeType'] = $this->resolveResponseMimeType($options['response_mime_type']);
        } elseif (isset($modelOptions['responseMimeType'])) {
            $generationConfigParams['responseMimeType'] = $this->resolveResponseMimeType($modelOptions['responseMimeType']);
        }

        // Use responseSchema from options or model default
        if (isset($options['response_schema'])) {
            $generationConfigParams['responseSchema'] = $this->resolveResponseSchema($options['response_schema']);
        } elseif (isset($modelOptions['responseSchema'])) {
            $generationConfigParams['responseSchema'] = $this->resolveResponseSchema($modelOptions['responseSchema']);
        }

        // Gemini only honours a response schema together with a JSON response MIME type,
        // so default to JSON when a schema is configured without an explicit MIME type
        if (isset($generationConfigParams['responseSchema'])) {
            if (!isset($generationConfigParams['responseMimeType'])) {
                $generationConfigParams['responseMimeType'] = ResponseMimeType::APPLICATION_JSON;
            } elseif ($generationConfigParams['responseMimeType'] !== ResponseMimeType::APPLICATION_JSON) {
                throw new InvalidArgumentException('A response schema requires the "application/json" response MIME type.');
            }
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

    /**
     * Normalize a response MIME type option into a ResponseMimeType enum.
     *
     * @throws InvalidArgumentException
     */
    private function resolveResponseMimeType(mixed $mimeType): ResponseMimeType
    {
        if ($mimeType instanceof ResponseMimeType) {
            return $mimeType;
        }

        if (!is_string($mimeType)) {
            throw new InvalidArgumentException(sprintf(
                'Response MIME type must be a string or a %s instance, got %s.',
                ResponseMimeType::class,
                get_debug_type($mimeType),
            ));
        }

        return ResponseMimeType::tryFrom($mimeType)
            ?? throw new InvalidArgumentException(sprintf('Invalid response MIME type "%s".', $mimeType));
    }

    /**
     * @throws InvalidArgumentException
     */
    private function resolveResponseSchema(mixed $schema): Schema
    {
        if ($schema instanceof Schema) {
            return $schema;
        }

        if (!is_array($schema)) {
            throw new InvalidArgumentException(sprintf(
                'Response schema must be an array or a %s instance, got %s.',
                Schema::class,
                get_debug_type($schema),
            ));
        }

        return $this->convertSchemaArray($schema);
    }

    /**
     * Decode a JSON text result into an ObjectResult.
     *
     * Non-object roots (arrays, scalars) keep the raw text result, since ObjectResult
     * only carries objects. Malformed JSON (e.g. a response truncated by the token
     * limit) throws and surfaces as a ClientException from request().
     *
     * @throws \JsonException
     */
    private function decodeJsonResult(ResultInterface $result): ResultInterface
    {
        if (!$result instanceof TextResult) {
            return $result;
        }

        $decoded = json_decode($result->getContent(), false, 512, JSON_THROW_ON_ERROR);
        if (!is_object($decoded)) {
            return $result;
        }

        return (new ObjectResult($decoded, $result->getMetadata()))->withUsage($result->getUsage());
    }

    /**
     * Convert a Gemini REST-style schema array into a Schema object.
     *
     * @param array<array-key, mixed> $schema
     *
     * @throws InvalidArgumentException on an unsupported key or a missing or unknown "type" or "format"
     * @throws \Webmozart\Assert\InvalidArgumentException when any other schema field has an unexpected type
     */
    private function convertSchemaArray(array $schema): Schema
    {
        $unknownKeys = array_diff(array_keys($schema), self::RESPONSE_SCHEMA_KEYS);
        if ($unknownKeys !== []) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported response schema key(s) "%s". Supported keys are "%s"; pass a prebuilt %s object for anything else.',
                implode('", "', $unknownKeys),
                implode('", "', self::RESPONSE_SCHEMA_KEYS),
                Schema::class,
            ));
        }

        if (!isset($schema['type']) || !is_string($schema['type'])) {
            throw new InvalidArgumentException('Response schema must define a "type" as string.');
        }

        $type = DataType::tryFrom(mb_strtoupper($schema['type']));
        if ($type === null) {
            throw new InvalidArgumentException(sprintf('Invalid response schema type "%s".', $schema['type']));
        }

        $format = null;
        if (isset($schema['format'])) {
            Assert::string($schema['format']);
            $format = DataFormat::tryFrom($schema['format'])
                ?? throw new InvalidArgumentException(sprintf('Invalid response schema format "%s".', $schema['format']));
        }

        $items = null;
        if (isset($schema['items'])) {
            Assert::isArray($schema['items']);
            $items = $this->convertSchemaArray($schema['items']);
        }

        return new Schema(
            type: $type,
            format: $format,
            description: $this->stringField($schema, 'description'),
            nullable: $this->boolField($schema, 'nullable'),
            enum: $this->stringListField($schema, 'enum'),
            maxItems: $this->integerStringField($schema, 'maxItems'),
            minItems: $this->integerStringField($schema, 'minItems'),
            properties: $this->schemaMapField($schema, 'properties'),
            required: $this->stringListField($schema, 'required'),
            propertyOrdering: $this->stringListField($schema, 'propertyOrdering'),
            items: $items,
            title: $this->stringField($schema, 'title'),
            minProperties: $this->integerStringField($schema, 'minProperties'),
            maxProperties: $this->integerStringField($schema, 'maxProperties'),
            minLength: $this->integerStringField($schema, 'minLength'),
            maxLength: $this->integerStringField($schema, 'maxLength'),
            pattern: $this->stringField($schema, 'pattern'),
            example: $this->scalarOrArrayField($schema, 'example'),
            anyOf: $this->schemaListField($schema, 'anyOf'),
            default: $this->scalarOrArrayField($schema, 'default'),
            minimum: $this->floatField($schema, 'minimum'),
            maximum: $this->floatField($schema, 'maximum'),
        );
    }

    /**
     * @param array<array-key, mixed> $schema
     */
    private function stringField(array $schema, string $key): ?string
    {
        if (!isset($schema[$key])) {
            return null;
        }

        $value = $schema[$key];
        Assert::string($value);

        return $value;
    }

    /**
     * @param array<array-key, mixed> $schema
     */
    private function boolField(array $schema, string $key): ?bool
    {
        if (!isset($schema[$key])) {
            return null;
        }

        $value = $schema[$key];
        Assert::boolean($value);

        return $value;
    }

    /**
     * @param array<array-key, mixed> $schema
     *
     * @return list<string>|null
     */
    private function stringListField(array $schema, string $key): ?array
    {
        if (!isset($schema[$key])) {
            return null;
        }

        $value = $schema[$key];
        Assert::isArray($value);
        Assert::allString($value);

        return array_values($value);
    }

    /**
     * @param array<array-key, mixed> $schema
     */
    private function integerStringField(array $schema, string $key): ?string
    {
        if (!isset($schema[$key])) {
            return null;
        }

        $value = $schema[$key];
        Assert::integerish($value);

        return (string) $value;
    }

    /**
     * @param array<array-key, mixed> $schema
     */
    private function floatField(array $schema, string $key): ?float
    {
        if (!isset($schema[$key])) {
            return null;
        }

        $value = $schema[$key];
        Assert::numeric($value);

        return (float) $value;
    }

    /**
     * @param array<array-key, mixed> $schema
     *
     * @throws InvalidArgumentException
     *
     * @return array<string, Schema>|null
     */
    private function schemaMapField(array $schema, string $key): ?array
    {
        if (!isset($schema[$key])) {
            return null;
        }

        $value = $schema[$key];
        Assert::isArray($value);

        $map = [];
        foreach ($value as $name => $subSchema) {
            Assert::string($name);
            Assert::isArray($subSchema);
            $map[$name] = $this->convertSchemaArray($subSchema);
        }

        return $map;
    }

    /**
     * @param array<array-key, mixed> $schema
     *
     * @throws InvalidArgumentException
     *
     * @return list<Schema>|null
     */
    private function schemaListField(array $schema, string $key): ?array
    {
        if (!isset($schema[$key])) {
            return null;
        }

        $value = $schema[$key];
        Assert::isArray($value);

        $list = [];
        foreach ($value as $subSchema) {
            Assert::isArray($subSchema);
            $list[] = $this->convertSchemaArray($subSchema);
        }

        return $list;
    }

    /**
     * @param array<array-key, mixed> $schema
     *
     * @throws InvalidArgumentException
     *
     * @return int|float|string|bool|array<array-key, mixed>|null
     */
    private function scalarOrArrayField(array $schema, string $key): int|float|string|bool|array|null
    {
        if (!isset($schema[$key])) {
            return null;
        }

        $value = $schema[$key];
        if (!is_scalar($value) && !is_array($value)) {
            throw new InvalidArgumentException(sprintf('Response schema "%s" must be a scalar or an array.', $key));
        }

        return $value;
    }
}
