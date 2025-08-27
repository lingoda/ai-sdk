<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Client\Gemini;

use Gemini\Client as GeminiAPIClient;
use Gemini\Data\Content;
use Gemini\Data\GenerationConfig;
use Gemini\Data\Part;
use Gemini\Enums\Role;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Client\Gemini\GeminiClient;
use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Provider\GeminiProvider;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\ResultInterface;
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