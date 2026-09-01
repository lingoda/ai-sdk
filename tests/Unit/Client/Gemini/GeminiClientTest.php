<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Client\Gemini;

use Gemini\Client as GeminiAPIClient;
use Gemini\Data\Content;
use Gemini\Data\DataFormat;
use Gemini\Data\GenerationConfig;
use Gemini\Data\Part;
use Gemini\Data\Schema;
use Gemini\Enums\DataType;
use Gemini\Enums\FinishReason;
use Gemini\Enums\ResponseMimeType;
use Gemini\Enums\Role;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\ResponseDecodeException;
use Lingoda\AiSdk\Client\Gemini\GeminiClient;
use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Provider\GeminiProvider;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\ObjectResult;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Result\ToolCall;
use Lingoda\AiSdk\Result\ToolCallResult;
use Lingoda\AiSdk\Result\Usage;
use Lingoda\AiSdk\Tests\Unit\Client\ClientTestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

final class GeminiClientTest extends ClientTestCase
{
    protected function createClient($apiClient, LoggerInterface $logger): ClientInterface
    {
        return new GeminiClient($apiClient, $logger);
    }
    
    protected function getProviderEnum(): AIProvider
    {
        return AIProvider::GEMINI;
    }
    
    protected function getApiClientClass(): string
    {
        return GeminiAPIClient::class;
    }
    
    protected function getDefaultModelId(): string
    {
        return 'gemini-1.5-flash';
    }

    public function testBuildChatPayloadWithSimpleString(): void
    {
        $payload = 'Hello world';
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertArrayNotHasKey('model', $result); // Gemini doesn't include model in payload
        self::assertArrayHasKey('contents', $result);
        self::assertCount(1, $result['contents']);
        
        $content = $result['contents'][0];
        self::assertInstanceOf(Content::class, $content);
        self::assertEquals(Role::USER, $content->role);
        self::assertCount(1, $content->parts);
        
        $part = $content->parts[0];
        self::assertInstanceOf(Part::class, $part);
        self::assertSame('Hello world', $part->text);
        
        self::assertArrayHasKey('generationConfig', $result);
        self::assertInstanceOf(GenerationConfig::class, $result['generationConfig']);
        self::assertSame(4096, $result['generationConfig']->maxOutputTokens);
        self::assertArrayNotHasKey('systemInstruction', $result); // No system instruction for simple string
    }

    public function testBuildChatPayloadWithStructuredMessages(): void
    {
        $payload = [
            'system' => 'You are a helpful assistant',
            'user' => 'What is PHP?',
            'assistant' => 'PHP is a programming language'
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertArrayNotHasKey('model', $result); // Gemini doesn't include model in payload
        self::assertArrayHasKey('contents', $result);
        self::assertCount(2, $result['contents']);
        
        // Check user message
        $userContent = $result['contents'][0];
        self::assertInstanceOf(Content::class, $userContent);
        self::assertEquals(Role::USER, $userContent->role);
        self::assertSame('What is PHP?', $userContent->parts[0]->text);
        
        // Check assistant message (becomes model in Gemini)
        $assistantContent = $result['contents'][1];
        self::assertInstanceOf(Content::class, $assistantContent);
        self::assertEquals(Role::MODEL, $assistantContent->role);
        self::assertSame('PHP is a programming language', $assistantContent->parts[0]->text);
        
        // Check system instruction
        self::assertArrayHasKey('systemInstruction', $result);
        self::assertInstanceOf(Content::class, $result['systemInstruction']);
        self::assertSame('You are a helpful assistant', $result['systemInstruction']->parts[0]->text);
        
        self::assertArrayHasKey('generationConfig', $result);
        self::assertInstanceOf(GenerationConfig::class, $result['generationConfig']);
        self::assertSame(4096, $result['generationConfig']->maxOutputTokens);
    }

    public function testBuildChatPayloadWithDirectContentsArray(): void
    {
        $payload = [
            'contents' => [
                [
                    'parts' => [['text' => 'Hello Gemini']],
                    'role' => 'user'
                ],
                [
                    'parts' => [['text' => 'Hello there!']],
                    'role' => 'model'
                ]
            ]
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertArrayNotHasKey('model', $result); // Gemini doesn't include model in payload
        self::assertArrayHasKey('contents', $result);
        self::assertCount(2, $result['contents']);
        
        // Check first message (user)
        $content1 = $result['contents'][0];
        self::assertInstanceOf(Content::class, $content1);
        self::assertEquals(Role::USER, $content1->role);
        self::assertSame('Hello Gemini', $content1->parts[0]->text);
        
        // Check second message (model)
        $content2 = $result['contents'][1];
        self::assertInstanceOf(Content::class, $content2);
        self::assertEquals(Role::MODEL, $content2->role);
        self::assertSame('Hello there!', $content2->parts[0]->text);
        
        self::assertArrayNotHasKey('systemInstruction', $result);
    }

    public function testBuildChatPayloadWithGenericMessagesArray(): void
    {
        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
                ['role' => 'assistant', 'content' => 'Hi there']
            ]
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertArrayNotHasKey('model', $result); // Gemini doesn't include model in payload
        self::assertArrayHasKey('contents', $result);
        self::assertCount(2, $result['contents']);
        
        // Check user message
        $userContent = $result['contents'][0];
        self::assertInstanceOf(Content::class, $userContent);
        self::assertEquals(Role::USER, $userContent->role);
        self::assertSame('Hello', $userContent->parts[0]->text);
        
        // Check assistant message (becomes model in Gemini)
        $assistantContent = $result['contents'][1];
        self::assertInstanceOf(Content::class, $assistantContent);
        self::assertEquals(Role::MODEL, $assistantContent->role);
        self::assertSame('Hi there', $assistantContent->parts[0]->text);
    }

    public function testBuildChatPayloadWithSystemAndUser(): void
    {
        $payload = [
            'system' => 'You are a code reviewer',
            'user' => 'Review this PHP code'
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertArrayNotHasKey('model', $result); // Gemini doesn't include model in payload
        self::assertArrayHasKey('contents', $result);
        self::assertCount(1, $result['contents']);
        
        // Check user message
        $userContent = $result['contents'][0];
        self::assertInstanceOf(Content::class, $userContent);
        self::assertEquals(Role::USER, $userContent->role);
        self::assertSame('Review this PHP code', $userContent->parts[0]->text);
        
        // Check system instruction
        self::assertArrayHasKey('systemInstruction', $result);
        self::assertInstanceOf(Content::class, $result['systemInstruction']);
        self::assertSame('You are a code reviewer', $result['systemInstruction']->parts[0]->text);
    }

    public function testBuildChatPayloadWithUserAndAssistant(): void
    {
        $payload = [
            'user' => 'What is 2+2?',
            'assistant' => '2+2 equals 4'
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertArrayNotHasKey('model', $result); // Gemini doesn't include model in payload
        self::assertArrayHasKey('contents', $result);
        self::assertCount(2, $result['contents']);
        
        // Check user message
        $userContent = $result['contents'][0];
        self::assertInstanceOf(Content::class, $userContent);
        self::assertEquals(Role::USER, $userContent->role);
        self::assertSame('What is 2+2?', $userContent->parts[0]->text);
        
        // Check assistant message (becomes model in Gemini)
        $assistantContent = $result['contents'][1];
        self::assertInstanceOf(Content::class, $assistantContent);
        self::assertEquals(Role::MODEL, $assistantContent->role);
        self::assertSame('2+2 equals 4', $assistantContent->parts[0]->text);
        
        self::assertArrayNotHasKey('systemInstruction', $result); // No system instruction when not provided
    }

    public function testBuildChatPayloadWithOnlySystemMessage(): void
    {
        $payload = [
            'system' => 'You are a helpful assistant'
        ];
        $options = [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No valid contents found in payload. Payload must contain user message.');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadWithEmptyArray(): void
    {
        $payload = [];
        $options = [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No valid contents found in payload. Payload must contain user message.');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadWithEmptyString(): void
    {
        $payload = '';
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertArrayNotHasKey('model', $result); // Gemini doesn't include model in payload
        self::assertArrayHasKey('contents', $result);
        self::assertCount(1, $result['contents']);
        
        $content = $result['contents'][0];
        self::assertInstanceOf(Content::class, $content);
        self::assertEquals(Role::USER, $content->role);
        self::assertSame('', $content->parts[0]->text);
        
        self::assertArrayNotHasKey('systemInstruction', $result); // No system instruction for empty string
    }

    public function testBuildChatPayloadWithEmptyContents(): void
    {
        $payload = [
            'contents' => []
        ];
        $options = [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No valid contents found in payload. Payload must contain user message.');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadWithEmptyMessages(): void
    {
        $payload = [
            'messages' => []
        ];
        $options = [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No valid contents found in payload. Payload must contain user message.');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadWithOptions(): void
    {
        $payload = 'Test message';
        $options = [
            'temperature' => 0.5,
            'max_tokens' => 2048,  // Gemini uses max_tokens, not maxOutputTokens
            'top_p' => 0.9
        ];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertInstanceOf(GenerationConfig::class, $result['generationConfig']);
        self::assertSame(0.5, $result['generationConfig']->temperature); // Options set temperature
        self::assertSame(2048, $result['generationConfig']->maxOutputTokens); // Options override default calculation
        self::assertSame(0.9, $result['generationConfig']->topP); // topP is in generationConfig
    }

    public function testBuildChatPayloadWithCustomMaxTokensFromModel(): void
    {
        $payload = 'Test message';
        $options = [];

        // Create a new mock for this specific test to avoid conflicts
        $model = $this->createMock(ModelInterface::class);
        $model->method('getId')->willReturn('gemini-2.0-flash-exp');
        $model->method('getOptions')->willReturn(['temperature' => 0.7]);
        $model->method('getMaxTokens')->willReturn(2048);

        $result = $this->invokePrivateMethod('buildChatPayload', [$model, $payload, $options]);

        self::assertInstanceOf(GenerationConfig::class, $result['generationConfig']);
        self::assertSame(2048, $result['generationConfig']->maxOutputTokens); // Uses model's max tokens when smaller than default 4096
        self::assertSame(0.7, $result['generationConfig']->temperature);
    }

    public function testBuildChatPayloadWithLargeMaxTokensFromModel(): void
    {
        $payload = 'Test message';
        $options = [];

        // Model with larger max tokens
        $this->model->method('getMaxTokens')->willReturn(16384);

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertInstanceOf(GenerationConfig::class, $result['generationConfig']);
        self::assertSame(4096, $result['generationConfig']->maxOutputTokens); // Capped at 4096 even if model supports more
    }

    public function testBuildChatPayloadMergesModelOptionsFirst(): void
    {
        $payload = 'Test message';
        $options = ['temperature' => 1.0];

        // Create a new mock for this specific test to avoid conflicts
        $model = $this->createMock(ModelInterface::class);
        $model->method('getId')->willReturn('gemini-2.0-flash-exp');
        $model->method('getOptions')->willReturn([
            'temperature' => 0.3,
            'topP' => 0.8,
            'frequencyPenalty' => 0.1
        ]);
        $model->method('getMaxTokens')->willReturn(8192);

        $result = $this->invokePrivateMethod('buildChatPayload', [$model, $payload, $options]);

        self::assertInstanceOf(GenerationConfig::class, $result['generationConfig']);
        self::assertSame(1.0, $result['generationConfig']->temperature); // Options override model
        self::assertSame(0.8, $result['generationConfig']->topP); // Model option in generationConfig
        self::assertSame(4096, $result['generationConfig']->maxOutputTokens); // Capped at 4096
    }

    public function testBuildChatPayloadRoleConversionInMessages(): void
    {
        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
                ['role' => 'assistant', 'content' => 'Hi'],
                ['role' => 'user', 'content' => 'How are you?'],
                ['role' => 'system', 'content' => 'Be helpful'] // system in messages should be converted
            ]
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertArrayHasKey('contents', $result);
        self::assertCount(3, $result['contents']);
        
        // Check first message (user)
        $content1 = $result['contents'][0];
        self::assertInstanceOf(Content::class, $content1);
        self::assertEquals(Role::USER, $content1->role);
        self::assertSame('Hello', $content1->parts[0]->text);
        
        // Check second message (assistant -> model)
        $content2 = $result['contents'][1];
        self::assertInstanceOf(Content::class, $content2);
        self::assertEquals(Role::MODEL, $content2->role);
        self::assertSame('Hi', $content2->parts[0]->text);
        
        // Check third message (user)
        $content3 = $result['contents'][2];
        self::assertInstanceOf(Content::class, $content3);
        self::assertEquals(Role::USER, $content3->role);
        self::assertSame('How are you?', $content3->parts[0]->text);
        
        // System message should become systemInstruction
        self::assertArrayHasKey('systemInstruction', $result);
        self::assertInstanceOf(Content::class, $result['systemInstruction']);
        self::assertSame('Be helpful', $result['systemInstruction']->parts[0]->text);
    }

    public function testBuildChatPayloadIgnoresInvalidMessageFormat(): void
    {
        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 'Valid message'],
                'invalid message', // non-array message should be ignored
                ['role' => 'assistant', 'content' => 'Another valid message']
            ]
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertArrayHasKey('contents', $result);
        self::assertCount(2, $result['contents']);
        
        // Check first valid message (user)
        $content1 = $result['contents'][0];
        self::assertInstanceOf(Content::class, $content1);
        self::assertEquals(Role::USER, $content1->role);
        self::assertSame('Valid message', $content1->parts[0]->text);
        
        // Check second valid message (assistant -> model)
        $content2 = $result['contents'][1];
        self::assertInstanceOf(Content::class, $content2);
        self::assertEquals(Role::MODEL, $content2->role);
        self::assertSame('Another valid message', $content2->parts[0]->text);
    }

    public function testBuildChatPayloadWithOnlyUserMessage(): void
    {
        $payload = [
            'user' => 'Hello Gemini'
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertArrayNotHasKey('model', $result); // Gemini doesn't include model in payload
        self::assertArrayHasKey('contents', $result);
        self::assertCount(1, $result['contents']);
        
        $content = $result['contents'][0];
        self::assertInstanceOf(Content::class, $content);
        self::assertEquals(Role::USER, $content->role);
        self::assertSame('Hello Gemini', $content->parts[0]->text);
        
        self::assertArrayNotHasKey('systemInstruction', $result); // No system instruction when not provided
    }

    public function testBuildChatPayloadContentsPreferredOverMessages(): void
    {
        $payload = [
            'contents' => [
                [
                    'parts' => [['text' => 'From contents array']],
                    'role' => 'user'
                ]
            ],
            'messages' => [
                ['role' => 'user', 'content' => 'From messages array']
            ]
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        // Should use contents array, not messages array
        self::assertArrayHasKey('contents', $result);
        self::assertCount(1, $result['contents']);
        
        $content = $result['contents'][0];
        self::assertInstanceOf(Content::class, $content);
        self::assertEquals(Role::USER, $content->role);
        self::assertSame('From contents array', $content->parts[0]->text); // Should use contents, not messages
    }

    public function testBuildChatPayloadWithResponseMimeTypeAndSchema(): void
    {
        $payload = 'Test message';
        $schema = [
            'type' => 'OBJECT',
            'description' => 'Result envelope',
            'properties' => [
                'status' => [
                    'type' => 'STRING',
                    'enum' => ['ok', 'error'],
                ],
                'tags' => [
                    'type' => 'ARRAY',
                    'nullable' => true, // false would be dropped by Schema::toArray()'s array_filter
                    'items' => ['type' => 'STRING'],
                ],
            ],
            'required' => ['status'],
        ];
        $options = [
            'response_mime_type' => 'application/json',
            'response_schema' => $schema,
        ];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertInstanceOf(GenerationConfig::class, $result['generationConfig']);
        self::assertSame(ResponseMimeType::APPLICATION_JSON, $result['generationConfig']->responseMimeType);
        self::assertInstanceOf(Schema::class, $result['generationConfig']->responseSchema);
        self::assertEquals($schema, $result['generationConfig']->responseSchema->toArray());
    }

    public function testBuildChatPayloadWithPrebuiltResponseSchemaAndMimeType(): void
    {
        $payload = 'Test message';
        $schema = new Schema(
            type: DataType::OBJECT,
            properties: ['result' => new Schema(type: DataType::STRING)],
            required: ['result'],
        );
        $options = [
            'response_mime_type' => ResponseMimeType::APPLICATION_JSON,
            'response_schema' => $schema,
        ];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertInstanceOf(GenerationConfig::class, $result['generationConfig']);
        self::assertSame(ResponseMimeType::APPLICATION_JSON, $result['generationConfig']->responseMimeType);
        self::assertSame($schema, $result['generationConfig']->responseSchema); // Passed through unchanged
    }

    public function testBuildChatPayloadWithResponseOptionsFromModel(): void
    {
        $payload = 'Test message';
        $options = [];

        // Create a new mock for this specific test to avoid conflicts
        $model = $this->createMock(ModelInterface::class);
        $model->method('getId')->willReturn('gemini-2.0-flash-exp');
        $model->method('getOptions')->willReturn([
            'responseMimeType' => 'application/json',
            'responseSchema' => ['type' => 'OBJECT', 'properties' => ['result' => ['type' => 'STRING']]],
        ]);
        $model->method('getMaxTokens')->willReturn(8192);

        $result = $this->invokePrivateMethod('buildChatPayload', [$model, $payload, $options]);

        self::assertInstanceOf(GenerationConfig::class, $result['generationConfig']);
        self::assertSame(ResponseMimeType::APPLICATION_JSON, $result['generationConfig']->responseMimeType);
        self::assertInstanceOf(Schema::class, $result['generationConfig']->responseSchema);
        self::assertEquals(
            ['type' => 'OBJECT', 'properties' => ['result' => ['type' => 'STRING']]],
            $result['generationConfig']->responseSchema->toArray()
        );
    }

    public function testBuildChatPayloadWithInvalidResponseSchemaType(): void
    {
        $payload = 'Test message';
        $options = [
            'response_schema' => ['type' => 'INVALID_TYPE'],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid response schema type "INVALID_TYPE".');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadWithMissingResponseSchemaType(): void
    {
        $payload = 'Test message';
        $options = [
            'response_schema' => ['properties' => ['result' => ['type' => 'STRING']]],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Response schema must define a "type" as string.');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadWithInvalidResponseMimeType(): void
    {
        $payload = 'Test message';
        $options = [
            'response_mime_type' => 'application/xml',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid response MIME type "application/xml".');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadWithResponseSchemaWithoutMimeTypeDefaultsToJson(): void
    {
        $payload = 'Test message';
        $options = [
            'response_schema' => ['type' => 'OBJECT', 'properties' => ['result' => ['type' => 'STRING']]],
        ];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertInstanceOf(GenerationConfig::class, $result['generationConfig']);
        self::assertSame(ResponseMimeType::APPLICATION_JSON, $result['generationConfig']->responseMimeType);
        self::assertInstanceOf(Schema::class, $result['generationConfig']->responseSchema);
    }

    public function testBuildChatPayloadWithResponseSchemaAndNonJsonMimeTypeThrows(): void
    {
        $payload = 'Test message';
        $options = [
            'response_mime_type' => 'text/plain',
            'response_schema' => ['type' => 'OBJECT', 'properties' => ['result' => ['type' => 'STRING']]],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A response schema requires the "application/json" response MIME type.');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadWithNonArrayResponseSchemaThrows(): void
    {
        $payload = 'Test message';
        $options = [
            'response_schema' => json_encode(['type' => 'OBJECT', 'properties' => ['result' => ['type' => 'STRING']]]),
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Response schema must be an array or a Gemini\Data\Schema instance, got string.');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadWithNonStringResponseMimeTypeThrows(): void
    {
        $payload = 'Test message';
        $options = [
            'response_mime_type' => 123,
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Response MIME type must be a string or a Gemini\Enums\ResponseMimeType instance, got int.');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadWithUnsupportedResponseSchemaKeyThrows(): void
    {
        $payload = 'Test message';
        $options = [
            'response_schema' => ['type' => 'OBJECT', 'additionalProperties' => false],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported response schema key(s) "additionalProperties".');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadWithInvalidResponseSchemaFormatThrows(): void
    {
        $payload = 'Test message';
        $options = [
            'response_schema' => ['type' => 'STRING', 'format' => 'uuid'],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid response schema format "uuid".');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadMapsAllResponseSchemaFields(): void
    {
        $payload = 'Test message';
        $options = [
            'response_schema' => [
                'type' => 'OBJECT',
                'title' => 'Envelope',
                'description' => 'Result envelope',
                'nullable' => true,
                'minProperties' => 1,
                'maxProperties' => 5,
                'propertyOrdering' => ['createdAt', 'score', 'tags', 'status'],
                'required' => ['status'],
                'properties' => [
                    'createdAt' => ['type' => 'STRING', 'format' => 'date-time'],
                    'score' => ['type' => 'NUMBER', 'minimum' => 1.5, 'maximum' => 9.5, 'example' => 4.2, 'default' => 2.5],
                    'tags' => [
                        'type' => 'ARRAY',
                        'minItems' => 1,
                        'maxItems' => '5',
                        'items' => ['type' => 'STRING', 'minLength' => 2, 'maxLength' => 10, 'pattern' => '^[a-z]+$'],
                    ],
                    'status' => [
                        'type' => 'STRING',
                        'enum' => ['ok', 'error'],
                    ],
                    'value' => [
                        'type' => 'STRING',
                        'anyOf' => [
                            ['type' => 'STRING'],
                            ['type' => 'INTEGER'],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertInstanceOf(GenerationConfig::class, $result['generationConfig']);
        $schema = $result['generationConfig']->responseSchema;
        self::assertInstanceOf(Schema::class, $schema);

        self::assertSame(DataType::OBJECT, $schema->type);
        self::assertSame('Envelope', $schema->title);
        self::assertSame('Result envelope', $schema->description);
        self::assertTrue($schema->nullable);
        self::assertSame('1', $schema->minProperties);
        self::assertSame('5', $schema->maxProperties);
        self::assertSame(['createdAt', 'score', 'tags', 'status'], $schema->propertyOrdering);
        self::assertSame(['status'], $schema->required);

        self::assertSame(DataFormat::DATETIME, $schema->properties['createdAt']->format);

        $score = $schema->properties['score'];
        self::assertSame(1.5, $score->minimum);
        self::assertSame(9.5, $score->maximum);
        self::assertSame(4.2, $score->example);
        self::assertSame(2.5, $score->default);

        $tags = $schema->properties['tags'];
        self::assertSame('1', $tags->minItems);
        self::assertSame('5', $tags->maxItems);
        self::assertSame('2', $tags->items->minLength);
        self::assertSame('10', $tags->items->maxLength);
        self::assertSame('^[a-z]+$', $tags->items->pattern);

        self::assertSame(['ok', 'error'], $schema->properties['status']->enum);

        $anyOf = $schema->properties['value']->anyOf;
        self::assertCount(2, $anyOf);
        self::assertSame(DataType::STRING, $anyOf[0]->type);
        self::assertSame(DataType::INTEGER, $anyOf[1]->type);
    }

    public function testBuildChatPayloadWithLowercaseResponseSchemaTypes(): void
    {
        $payload = 'Test message';
        $options = [
            'response_mime_type' => 'application/json',
            'response_schema' => [
                'type' => 'object',
                'properties' => ['result' => ['type' => 'string']],
                'required' => ['result'],
            ],
        ];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertInstanceOf(GenerationConfig::class, $result['generationConfig']);
        self::assertInstanceOf(Schema::class, $result['generationConfig']->responseSchema);
        self::assertSame(DataType::OBJECT, $result['generationConfig']->responseSchema->type);
        self::assertSame(DataType::STRING, $result['generationConfig']->responseSchema->properties['result']->type);
        self::assertEquals(
            ['type' => 'OBJECT', 'properties' => ['result' => ['type' => 'STRING']], 'required' => ['result']],
            $result['generationConfig']->responseSchema->toArray() // Normalized to uppercase type names
        );
    }

    public function testBuildChatPayloadWithoutResponseOptionsKeepsDefaults(): void
    {
        $payload = 'Test message';
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertInstanceOf(GenerationConfig::class, $result['generationConfig']);
        self::assertSame(ResponseMimeType::TEXT_PLAIN, $result['generationConfig']->responseMimeType); // Constructor default
        self::assertNull($result['generationConfig']->responseSchema);
    }

    public function testGetProviderReturnsGeminiProvider(): void
    {
        $provider = $this->client->getProvider();
        
        $this->assertInstanceOf(GeminiProvider::class, $provider);
    }
    
    public function testRequestSuccessSimple(): void
    {
        $payload = 'Test message';
        $options = [];
        
        // Create a model that already has maxOutputTokens to avoid generation config path
        $model = $this->createMockModel('gemini-1.5-flash', ['maxOutputTokens' => 1000], 4096);
        
        $generativeModel = $this->createMock(\Gemini\Resources\GenerativeModel::class);
        $response = $this->createMock(\Gemini\Responses\GenerativeModel\GenerateContentResponse::class);
        $result = $this->createMock(ResultInterface::class);
        
        // Setup expectations for method calls
        $this->apiClient->method('generativeModel')->with('gemini-1.5-flash')->willReturn($generativeModel);
        $generativeModel->method('withGenerationConfig')->willReturnSelf();
        $generativeModel->method('generateContent')->willReturn($response);
        
        $resultConverter = $this->createMock(\Lingoda\AiSdk\Converter\Gemini\GeminiResultConverter::class);
        $resultConverter->expects($this->once())
            ->method('convert')
            ->with(
                $this->equalTo($model),
                $this->identicalTo($response)
            )
            ->willReturn($result);
        
        // Use reflection to inject the result converter
        $reflection = new ReflectionClass($this->client);
        $property = $reflection->getProperty('resultConverter');
        $property->setAccessible(true);
        $property->setValue($this->client, $resultConverter);
        
        $actualResult = $this->client->request($model, $payload, $options);

        $this->assertSame($result, $actualResult);
    }

    public function testRequestReturnsObjectResultWhenJsonRequested(): void
    {
        $model = $this->createMockModel('gemini-1.5-flash', [], 4096);
        $options = [
            'response_schema' => ['type' => 'OBJECT', 'properties' => ['status' => ['type' => 'STRING']]],
        ];
        $usage = new Usage(promptTokens: 1, completionTokens: 2, totalTokens: 3);
        $textResult = (new TextResult('{"status":"ok"}', ['id' => 'gemini_test']))->withUsage($usage);
        $this->setupRequestReturning($textResult);

        $actualResult = $this->client->request($model, 'Test message', $options);

        self::assertInstanceOf(ObjectResult::class, $actualResult);
        $content = $actualResult->getContent();
        self::assertInstanceOf(\stdClass::class, $content);
        self::assertSame('ok', $content->status);
        self::assertSame(['id' => 'gemini_test'], $actualResult->getMetadata());
        self::assertSame($usage, $actualResult->getUsage());
    }

    public function testRequestThrowsOnMalformedJson(): void
    {
        $model = $this->createMockModel('gemini-1.5-flash', [], 4096);
        $options = [
            'response_schema' => ['type' => 'OBJECT', 'properties' => ['status' => ['type' => 'STRING']]],
        ];
        $textResult = new TextResult('{"status": truncated', [
            'id' => 'gemini_test',
            'finish_reason' => FinishReason::MAX_TOKENS,
        ]);
        $this->setupRequestReturning($textResult);

        $this->logger->expects($this->once())->method('error')->with(
            'Gemini returned malformed JSON',
            $this->callback(
                static fn (array $context): bool => $context['finish_reason'] === 'MAX_TOKENS' && isset($context['error'])
            )
        );

        try {
            $this->client->request($model, 'Test message', $options);
            self::fail('Expected ' . ResponseDecodeException::class . ' to be thrown.');
        } catch (ResponseDecodeException $e) {
            self::assertStringContainsString('Failed to decode Gemini JSON response (finish reason: MAX_TOKENS)', $e->getMessage());
            self::assertSame($textResult, $e->getResult());
            self::assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    public function testRequestKeepsToolCallResultWhenJsonRequested(): void
    {
        $model = $this->createMockModel('gemini-1.5-flash', [], 4096);
        $options = [
            'response_schema' => ['type' => 'OBJECT', 'properties' => ['status' => ['type' => 'STRING']]],
        ];
        $toolCallResult = new ToolCallResult(['id' => 'gemini_test'], new ToolCall('call_1', 'lookup', ['q' => 'test']));
        $this->setupRequestReturning($toolCallResult);

        $actualResult = $this->client->request($model, 'Test message', $options);

        self::assertSame($toolCallResult, $actualResult);
    }

    public function testRequestReturnsObjectResultForJsonArrayRoot(): void
    {
        $model = $this->createMockModel('gemini-1.5-flash', [], 4096);
        $options = [
            'response_schema' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
        ];
        $usage = new Usage(promptTokens: 1, completionTokens: 2, totalTokens: 3);
        $textResult = (new TextResult('["a", "b"]', ['id' => 'gemini_test']))->withUsage($usage);
        $this->setupRequestReturning($textResult);

        $actualResult = $this->client->request($model, 'Test message', $options);

        self::assertInstanceOf(ObjectResult::class, $actualResult);
        self::assertSame(['a', 'b'], $actualResult->getContent());
        self::assertSame(['id' => 'gemini_test'], $actualResult->getMetadata());
        self::assertSame($usage, $actualResult->getUsage());
    }

    public function testRequestThrowsWhenScalarRootViolatesContainerSchema(): void
    {
        $model = $this->createMockModel('gemini-1.5-flash', [], 4096);
        $options = [
            'response_schema' => ['type' => 'OBJECT', 'properties' => ['status' => ['type' => 'STRING']]],
        ];
        $textResult = new TextResult('"ok"', ['id' => 'gemini_test']);
        $this->setupRequestReturning($textResult);

        $this->logger->expects($this->once())->method('error')->with(
            'Gemini returned JSON that does not match the response schema root type',
            ['expected_root' => 'OBJECT', 'actual_root' => 'string']
        );

        try {
            $this->client->request($model, 'Test message', $options);
            self::fail('Expected ' . ResponseDecodeException::class . ' to be thrown.');
        } catch (ResponseDecodeException $e) {
            self::assertSame('Gemini JSON response has a string root, but the response schema requested an OBJECT root.', $e->getMessage());
            self::assertSame($textResult, $e->getResult());
        }
    }

    public function testRequestKeepsTextResultForJsonScalarRoot(): void
    {
        $model = $this->createMockModel('gemini-1.5-flash', [], 4096);
        $options = [
            'response_schema' => ['type' => 'STRING', 'enum' => ['ok', 'error']],
        ];
        $textResult = new TextResult('"ok"', ['id' => 'gemini_test']);
        $this->setupRequestReturning($textResult);

        $actualResult = $this->client->request($model, 'Test message', $options);

        self::assertSame($textResult, $actualResult);
    }

    /**
     * Set up the API client and result converter mocks so request() yields the given result.
     */
    private function setupRequestReturning(ResultInterface $result): void
    {
        $generativeModel = $this->createMock(\Gemini\Resources\GenerativeModel::class);
        $response = $this->createMock(\Gemini\Responses\GenerativeModel\GenerateContentResponse::class);

        $this->apiClient->method('generativeModel')->willReturn($generativeModel);
        $generativeModel->method('withGenerationConfig')->willReturnSelf();
        $generativeModel->method('generateContent')->willReturn($response);

        $resultConverter = $this->createMock(\Lingoda\AiSdk\Converter\Gemini\GeminiResultConverter::class);
        $resultConverter->method('convert')->willReturn($result);
        $this->setPrivateProperty('resultConverter', $resultConverter);
    }

    public function testRequestFailure(): void
    {
        $payload = 'Test message';
        $options = [];
        $exception = new \Exception('API error');
        
        $this->apiClient->method('generativeModel')->willThrowException($exception);
        
        $this->logger->expects($this->once())->method('error')->with(
            'Gemini request failed',
            $this->callback(function ($context) {
                return isset($context['exception']) && 
                       isset($context['model']) && 
                       isset($context['payload_type']);
            })
        );
        
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Gemini request failed: API error');
        
        $this->client->request($this->model, $payload, $options);
    }
    
    public function testConstructorWithDefaultLogger(): void
    {
        $apiClient = $this->createMock(GeminiAPIClient::class);
        $client = new GeminiClient($apiClient);
        
        $this->assertInstanceOf(GeminiClient::class, $client);
    }

    public function testSupports(): void
    {
        $geminiProvider = $this->createMock(ProviderInterface::class);
        $geminiProvider->method('is')->with(AIProvider::GEMINI)->willReturn(true);
        
        $geminiModel = $this->createMock(ModelInterface::class);
        $geminiModel->method('getProvider')->willReturn($geminiProvider);
        
        $this->assertTrue($this->client->supports($geminiModel));
        
        $nonGeminiProvider = $this->createMock(ProviderInterface::class);
        $nonGeminiProvider->method('is')->with(AIProvider::GEMINI)->willReturn(false);
        
        $nonGeminiModel = $this->createMock(ModelInterface::class);
        $nonGeminiModel->method('getProvider')->willReturn($nonGeminiProvider);
        
        $this->assertFalse($this->client->supports($nonGeminiModel));
    }
    

    public function testGetResultConverter(): void
    {
        // Test that the result converter is properly lazy-loaded
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod('getResultConverter');
        $method->setAccessible(true);
        
        $converter1 = $method->invoke($this->client);
        $converter2 = $method->invoke($this->client);
        
        $this->assertInstanceOf(\Lingoda\AiSdk\Converter\Gemini\GeminiResultConverter::class, $converter1);
        $this->assertSame($converter1, $converter2, 'Result converter should be lazy loaded');
    }

}