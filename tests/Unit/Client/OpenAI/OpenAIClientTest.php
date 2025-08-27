<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Client\OpenAI;

use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Audio\OpenAI\AudioOptions;
use Lingoda\AiSdk\Client\OpenAI\OpenAIClient;
use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Converter\OpenAI\OpenAIResultConverter;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Enum\OpenAI\AudioTranscribeModel;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\Result\BinaryResult;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Tests\Unit\Client\ClientTestCase;
use OpenAI\Client as OpenAIAPIClient;
use OpenAI\Resources\Audio;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Audio\TranscriptionResponse;
use OpenAI\Responses\Audio\TranslationResponse;
use OpenAI\Responses\Chat\CreateResponse;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;

final class OpenAIClientTest extends ClientTestCase
{
    protected function createClient(mixed $apiClient, LoggerInterface $logger): ClientInterface
    {
        return new OpenAIClient($apiClient, $logger);
    }
    
    protected function getProviderEnum(): AIProvider
    {
        return AIProvider::OPENAI;
    }
    
    protected function getApiClientClass(): string
    {
        return OpenAIAPIClient::class;
    }
    
    protected function getDefaultModelId(): string
    {
        return 'gpt-4';
    }

    public function testBuildChatPayloadWithSimpleString(): void
    {
        $payload = 'Hello world';
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        $expectedMessages = [
            ['role' => 'user', 'content' => 'Hello world']
        ];

        self::assertSame('gpt-4', $result['model']);
        self::assertSame($expectedMessages, $result['messages']);
        self::assertSame(0.7, $result['temperature']);
        self::assertSame(4096, $result['max_tokens']);
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
            ['role' => 'system', 'content' => 'You are a helpful assistant'],
            ['role' => 'user', 'content' => 'What is PHP?'],
            ['role' => 'assistant', 'content' => 'PHP is a programming language']
        ];

        self::assertSame('gpt-4', $result['model']);
        self::assertSame($expectedMessages, $result['messages']);
        self::assertSame(0.7, $result['temperature']);
        self::assertSame(4096, $result['max_tokens']);
    }

    public function testBuildChatPayloadWithDirectMessagesArray(): void
    {
        $payload = [
            'messages' => [
                ['role' => 'system', 'content' => 'You are helpful'],
                ['role' => 'user', 'content' => 'Hello'],
                ['role' => 'assistant', 'content' => 'Hi there']
            ]
        ];
        $options = [];

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        $expectedMessages = [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there']
        ];

        self::assertSame('gpt-4', $result['model']);
        self::assertSame($expectedMessages, $result['messages']);
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
            ['role' => 'system', 'content' => 'You are a code reviewer'],
            ['role' => 'user', 'content' => 'Review this PHP code']
        ];

        self::assertSame('gpt-4', $result['model']);
        self::assertSame($expectedMessages, $result['messages']);
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

        self::assertSame('gpt-4', $result['model']);
        self::assertSame($expectedMessages, $result['messages']);
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

        self::assertSame('gpt-4', $result['model']);
        self::assertSame($expectedMessages, $result['messages']);
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

        // Create a new mock for this specific test to avoid conflicts
        $model = $this->createMock(ModelInterface::class);
        $model->method('getId')->willReturn('gpt-4o-mini');
        $model->method('getOptions')->willReturn(['temperature' => 0.7]);
        $model->method('getMaxTokens')->willReturn(2048);

        $result = $this->invokePrivateMethod('buildChatPayload', [$model, $payload, $options]);

        self::assertSame(2048, $result['max_tokens']); // Uses model's max tokens when smaller than default 4096
    }

    public function testBuildChatPayloadWithLargeMaxTokensFromModel(): void
    {
        $payload = 'Test message';
        $options = [];

        // Model with larger max tokens
        $this->model->method('getMaxTokens')->willReturn(16384);

        $result = $this->invokePrivateMethod('buildChatPayload', [$this->model, $payload, $options]);

        self::assertSame(4096, $result['max_tokens']); // Capped at 4096 even if model supports more
    }

    public function testBuildChatPayloadMergesModelOptionsFirst(): void
    {
        $payload = 'Test message';
        $options = ['temperature' => 1.0];

        // Create a new mock for this specific test to avoid conflicts
        $model = $this->createMock(ModelInterface::class);
        $model->method('getId')->willReturn('gpt-4o-mini');
        $model->method('getOptions')->willReturn([
            'temperature' => 0.3,
            'top_p' => 0.8,
            'frequency_penalty' => 0.1
        ]);
        $model->method('getMaxTokens')->willReturn(8192);

        $result = $this->invokePrivateMethod('buildChatPayload', [$model, $payload, $options]);

        self::assertSame(1.0, $result['temperature']); // Options override model
        self::assertSame(0.8, $result['top_p']); // Model option preserved
        self::assertSame(0.1, $result['frequency_penalty']); // Model option preserved
    }

    public function testRequestSuccess(): void
    {
        $payload = 'Test message';
        $options = ['temperature' => 0.5];
        
        $chatResource = $this->createMock(Chat::class);
        $response = $this->createMock(CreateResponse::class);
        $result = $this->createMock(ResultInterface::class);
        
        $this->apiClient->method('chat')->willReturn($chatResource);
        $chatResource->method('create')->willReturn($response);
        
        $resultConverter = $this->createMock(OpenAIResultConverter::class);
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
        
        $chatResource = $this->createMock(Chat::class);
        $this->apiClient->method('chat')->willReturn($chatResource);
        $chatResource->method('create')->willThrowException($exception);
        
        $this->logger->expects($this->once())->method('error')->with(
            'OpenAI chat request failed',
            $this->callback(function ($context) {
                return isset($context['exception']) && 
                       isset($context['model']) && 
                       isset($context['payload_type']);
            })
        );
        
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('OpenAI request failed: API error');
        
        $this->client->request($this->model, $payload, $options);
    }
    
    public function testGetProviderReturnsOpenAIProvider(): void
    {
        $provider = $this->client->getProvider();
        
        $this->assertInstanceOf(OpenAIProvider::class, $provider);
    }
    
    public function testTextToSpeechSuccess(): void
    {
        $input = 'Hello world';
        $options = AudioOptions::textToSpeech();
        $audioData = 'binary audio data';
        
        $audioResource = $this->createMock(Audio::class);
        
        $this->apiClient->method('audio')->willReturn($audioResource);
        $audioResource->method('speech')->willReturn($audioData);
        
        $result = $this->client->textToSpeech($input, $options);
        
        $this->assertInstanceOf(BinaryResult::class, $result);
    }
    
    public function testTextToSpeechFailure(): void
    {
        $input = 'Hello world';
        $options = AudioOptions::textToSpeech();
        $exception = new \Exception('TTS error');
        
        $audioResource = $this->createMock(Audio::class);
        $this->apiClient->method('audio')->willReturn($audioResource);
        $audioResource->method('speech')->willThrowException($exception);
        
        $this->logger->expects($this->once())->method('error')->with(
            'OpenAI text-to-speech request failed',
            $this->callback(function ($context) {
                return isset($context['exception']) && 
                       isset($context['input_length']);
            })
        );
        
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('OpenAI text-to-speech request failed: TTS error');
        
        $this->client->textToSpeech($input, $options);
    }
    
    public function testSpeechToTextSuccess(): void
    {
        $audioStream = $this->createMock(StreamInterface::class);
        $options = AudioOptions::speechToText(
            AudioTranscribeModel::WHISPER_1,
            'en'
        );
        
        $audioResource = $this->createMock(Audio::class);
        $response = $this->createMock(TranscriptionResponse::class);
        $response->text = 'Transcribed text';
        
        $this->apiClient->method('audio')->willReturn($audioResource);
        $audioResource->method('transcribe')->willReturn($response);
        
        $result = $this->client->speechToText($audioStream, $options);
        
        $this->assertInstanceOf(TextResult::class, $result);
    }
    
    public function testSpeechToTextFailure(): void
    {
        $audioStream = $this->createMock(StreamInterface::class);
        $options = AudioOptions::speechToText();
        $exception = new \Exception('STT error');
        
        $audioResource = $this->createMock(Audio::class);
        $this->apiClient->method('audio')->willReturn($audioResource);
        $audioResource->method('transcribe')->willThrowException($exception);
        
        $this->logger->expects($this->once())->method('error')->with(
            'OpenAI speech-to-text request failed',
            $this->callback(function ($context) {
                return isset($context['exception']);
            })
        );
        
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('OpenAI speech-to-text request failed: STT error');
        
        $this->client->speechToText($audioStream, $options);
    }
    
    public function testTranslateSuccess(): void
    {
        $audioStream = $this->createMock(StreamInterface::class);
        $options = AudioOptions::translate();
        
        $audioResource = $this->createMock(Audio::class);
        $response = $this->createMock(TranslationResponse::class);
        $response->text = 'Translated text';
        
        $this->apiClient->method('audio')->willReturn($audioResource);
        $audioResource->method('translate')->willReturn($response);
        
        $result = $this->client->translate($audioStream, $options);
        
        $this->assertInstanceOf(TextResult::class, $result);
    }
    
    public function testTranslateFailure(): void
    {
        $audioStream = $this->createMock(StreamInterface::class);
        $options = AudioOptions::translate();
        $exception = new \Exception('Translation error');
        
        $audioResource = $this->createMock(Audio::class);
        $this->apiClient->method('audio')->willReturn($audioResource);
        $audioResource->method('translate')->willThrowException($exception);
        
        $this->logger->expects($this->once())->method('error')->with(
            'OpenAI speech translation request failed',
            $this->callback(function ($context) {
                return isset($context['exception']);
            })
        );
        
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('OpenAI speech translation request failed: Translation error');
        
        $this->client->translate($audioStream, $options);
    }

}