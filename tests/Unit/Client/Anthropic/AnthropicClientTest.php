<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Client\Anthropic;

use Anthropic\Client as AnthropicAPIClient;
use Anthropic\Resources\Messages;
use Anthropic\Responses\Messages\CreateResponse;
use Lingoda\AiSdk\Client\Anthropic\AnthropicClient;
use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Converter\Anthropic\AnthropicResultConverter;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Provider\AnthropicProvider;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Tests\Unit\Client\ClientTestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

final class AnthropicClientTest extends ClientTestCase
{
    protected function createClient(mixed $apiClient, LoggerInterface $logger): ClientInterface
    {
        return new AnthropicClient($apiClient, $logger);
    }
    
    protected function getProviderEnum(): AIProvider
    {
        return AIProvider::ANTHROPIC;
    }
    
    protected function getApiClientClass(): string
    {
        return AnthropicAPIClient::class;
    }
    
    protected function getDefaultModelId(): string
    {
        return 'claude-3-haiku-20240307';
    }

    public function testBuildChatPayloadWithSimpleString(): void
    {
        $payload = 'Hello world';
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        $expectedMessages = [
            ['role' => 'user', 'content' => 'Hello world']
        ];

        self::assertSame($this->getDefaultModelId(), $result['model']);
        self::assertSame($expectedMessages, $result['messages']);
        self::assertSame(0.7, $result['temperature']);
        self::assertSame(4096, $result['max_tokens']);
        self::assertArrayNotHasKey('system', $result); // No system prompt for simple string
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

        $expectedMessages = [
            ['role' => 'user', 'content' => 'What is PHP?'],
            ['role' => 'assistant', 'content' => 'PHP is a programming language']
        ];

        self::assertSame($this->getDefaultModelId(), $result['model']);
        self::assertSame($expectedMessages, $result['messages']);
        self::assertSame('You are a helpful assistant', $result['system']); // System in separate field
        self::assertSame(0.7, $result['temperature']);
        self::assertSame(4096, $result['max_tokens']);
    }

    public function testBuildChatPayloadWithDirectMessagesArray(): void
    {
        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
                ['role' => 'assistant', 'content' => 'Hi there']
            ]
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        $expectedMessages = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there']
        ];

        self::assertSame($this->getDefaultModelId(), $result['model']);
        self::assertSame($expectedMessages, $result['messages']);
        self::assertArrayNotHasKey('system', $result); // No system field when not provided
    }

    public function testBuildChatPayloadWithSystemAndUser(): void
    {
        $payload = [
            'system' => 'You are a code reviewer',
            'user' => 'Review this PHP code'
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        $expectedMessages = [
            ['role' => 'user', 'content' => 'Review this PHP code']
        ];

        self::assertSame($this->getDefaultModelId(), $result['model']);
        self::assertSame($expectedMessages, $result['messages']);
        self::assertSame('You are a code reviewer', $result['system']); // System in separate field
    }

    public function testBuildChatPayloadWithUserAndAssistant(): void
    {
        $payload = [
            'user' => 'What is 2+2?',
            'assistant' => '2+2 equals 4'
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        $expectedMessages = [
            ['role' => 'user', 'content' => 'What is 2+2?'],
            ['role' => 'assistant', 'content' => '2+2 equals 4']
        ];

        self::assertSame($this->getDefaultModelId(), $result['model']);
        self::assertSame($expectedMessages, $result['messages']);
        self::assertArrayNotHasKey('system', $result); // No system field when not provided
    }

    public function testBuildChatPayloadWithOnlySystemMessage(): void
    {
        $payload = [
            'system' => 'You are a helpful assistant'
        ];
        $options = [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No valid messages found in payload. Payload must contain user message.');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadWithEmptyArray(): void
    {
        $payload = [];
        $options = [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No valid messages found in payload. Payload must contain user message.');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadWithEmptyString(): void
    {
        $payload = '';
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        $expectedMessages = [
            ['role' => 'user', 'content' => '']
        ];

        self::assertSame($this->getDefaultModelId(), $result['model']);
        self::assertSame($expectedMessages, $result['messages']);
        self::assertArrayNotHasKey('system', $result); // No system field for empty string
    }

    public function testBuildChatPayloadWithEmptyMessages(): void
    {
        $payload = [
            'messages' => []
        ];
        $options = [];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No valid messages found in payload. Payload must contain user message.');

        $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);
    }

    public function testBuildChatPayloadWithOptions(): void
    {
        $payload = 'Test message';
        $options = [
            'temperature' => 0.5,
            'max_tokens' => 2048,
            'top_p' => 0.9
        ];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertSame(0.5, $result['temperature']); // Options override model defaults
        self::assertSame(2048, $result['max_tokens']); // Options override default calculation
        self::assertSame(0.9, $result['top_p']);
    }

    public function testBuildChatPayloadWithCustomMaxTokensFromModel(): void
    {
        $payload = 'Test message';
        $options = [];

        // Create a model with smaller max tokens
        $model = $this->createMockModel('claude-3-haiku-20240307', ['temperature' => 0.7], 2048);

        $result = $this->invokePrivateMethod('buildChatPayload', [$model, $payload, $options]);

        self::assertSame(2048, $result['max_tokens']); // Uses model's max tokens when smaller than default 4096
    }

    public function testBuildChatPayloadWithLargeMaxTokensFromModel(): void
    {
        $payload = 'Test message';
        $options = [];

        // Model with larger max tokens
        $model = $this->createMockModel('claude-3-haiku-20240307', ['temperature' => 0.7], 16384);

        $result = $this->invokePrivateMethod('buildChatPayload', [$model, $payload, $options]);

        self::assertSame(4096, $result['max_tokens']); // Capped at 4096 even if model supports more
    }

    public function testBuildChatPayloadMergesModelOptionsFirst(): void
    {
        $payload = 'Test message';
        $options = ['temperature' => 1.0];

        // Create a model with multiple options
        $model = $this->createMockModel('claude-3-haiku-20240307', [
            'temperature' => 0.3,
            'top_p' => 0.8,
            'frequency_penalty' => 0.1
        ]);

        $result = $this->invokePrivateMethod('buildChatPayload', [$model, $payload, $options]);

        self::assertSame(1.0, $result['temperature']); // Options override model
        self::assertSame(0.8, $result['top_p']); // Model option preserved
        self::assertSame(0.1, $result['frequency_penalty']); // Model option preserved
    }

    public function testBuildChatPayloadSystemPromptInSeparateField(): void
    {
        $payload = [
            'system' => 'Be concise and accurate',
            'user' => 'Explain quantum computing',
            'assistant' => 'Quantum computing uses quantum mechanics principles.'
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        // System prompt should be in separate field, not in messages array
        self::assertSame('Be concise and accurate', $result['system']);
        
        $expectedMessages = [
            ['role' => 'user', 'content' => 'Explain quantum computing'],
            ['role' => 'assistant', 'content' => 'Quantum computing uses quantum mechanics principles.']
        ];
        
        self::assertSame($expectedMessages, $result['messages']);
        
        // Verify system is not in messages array
        foreach ($result['messages'] as $message) {
            self::assertNotEquals('system', $message['role']);
        }
    }

    public function testBuildChatPayloadWithOnlyUserMessage(): void
    {
        $payload = [
            'user' => 'Hello Anthropic'
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        $expectedMessages = [
            ['role' => 'user', 'content' => 'Hello Anthropic']
        ];

        self::assertSame($this->getDefaultModelId(), $result['model']);
        self::assertSame($expectedMessages, $result['messages']);
        self::assertArrayNotHasKey('system', $result); // No system field when not provided
    }

    public function testGetProviderReturnsAnthropicProvider(): void
    {
        $provider = $this->client->getProvider();
        
        $this->assertInstanceOf(AnthropicProvider::class, $provider);
    }
    
    public function testRequestSuccess(): void
    {
        $payload = 'Test message';
        $options = ['temperature' => 0.5];
        
        $messagesResource = $this->createMock(Messages::class);
        $response = $this->createMock(CreateResponse::class);
        $result = $this->createMock(ResultInterface::class);
        
        $this->apiClient->method('messages')->willReturn($messagesResource);
        $messagesResource->method('create')->willReturn($response);
        
        $resultConverter = $this->createMock(AnthropicResultConverter::class);
        $resultConverter->method('convert')->with($this->model, $response)->willReturn($result);
        
        // Use reflection to inject the result converter
        $reflection = new ReflectionClass($this->client);
        $property = $reflection->getProperty('resultConverter');
        $property->setAccessible(true);
        $property->setValue($this->client, $resultConverter);
        
        $actualResult = $this->client->request($this->model, $payload, $options);
        
        $this->assertSame($result, $actualResult);
    }
    
    public function testRequestFailure(): void
    {
        $payload = 'Test message';
        $options = [];
        $exception = new \Exception('API error');
        
        $messagesResource = $this->createMock(Messages::class);
        $this->apiClient->method('messages')->willReturn($messagesResource);
        $messagesResource->method('create')->willThrowException($exception);
        
        $this->logger->expects($this->once())->method('error')->with(
            'Anthropic request failed',
            $this->callback(function ($context) {
                return isset($context['exception']) && 
                       isset($context['model']) && 
                       isset($context['payload_type']);
            })
        );
        
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Anthropic request failed: API error');
        
        $this->client->request($this->model, $payload, $options);
    }
}