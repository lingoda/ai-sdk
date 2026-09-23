<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Client\OpenAI;

use Lingoda\AiSdk\Audio\AudioOptionsInterface;
use Lingoda\AiSdk\Audio\OpenAI\AudioOptions;
use Lingoda\AiSdk\Client\OpenAI\OpenAIClient;
use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Converter\OpenAI\OpenAIResultConverter;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Enum\OpenAI\AudioSpeechFormat;
use Lingoda\AiSdk\Enum\OpenAI\AudioTranscribeModel;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\UnsupportedCapabilityException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Prompt\Attachment;
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\Result\BinaryResult;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\StreamResult;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Tests\Unit\Client\ClientTestCase;
use Nyholm\Psr7\Response;
use OpenAI\Client as OpenAIAPIClient;
use OpenAI\Resources\Audio;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Audio\SpeechStreamResponse;
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
            $this->callback(
                fn ($context) => isset($context['exception']) &&
                       isset($context['model'], $context['payload_type'])
            )
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
            $this->callback(fn ($context) => isset($context['exception']) &&
                       isset($context['input_length']))
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('OpenAI text-to-speech request failed: TTS error');

        $this->client->textToSpeech($input, $options);
    }

    public function testTextToSpeechFallsBackToMp3ForNonStringFormat(): void
    {
        $options = $this->createMock(AudioOptionsInterface::class);
        $options->method('toArray')->willReturn(['model' => 'tts-1', 'voice' => 'alloy', 'response_format' => 123]);

        $audioResource = $this->createMock(Audio::class);
        $this->apiClient->method('audio')->willReturn($audioResource);
        $audioResource->method('speech')->willReturn('binary audio data');

        $result = $this->client->textToSpeech('Hello world', $options);

        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testTextToSpeechStreamSuccess(): void
    {
        $options = AudioOptions::textToSpeech(format: AudioSpeechFormat::OPUS);

        $audioResource = $this->createMock(Audio::class);
        $this->apiClient->method('audio')->willReturn($audioResource);
        $audioResource->expects($this->once())
            ->method('speechStreamed')
            ->with($this->callback(fn (array $payload) => $payload['input'] === 'Hello world'))
            ->willReturn(new SpeechStreamResponse(new Response(200, [], 'streamed audio')))
        ;

        $result = $this->openAIClient()->textToSpeechStream('Hello world', $options);

        $this->assertInstanceOf(StreamResult::class, $result);
        $this->assertSame('audio/opus', $result->getMimeType());
        $this->assertSame('streamed audio', (string) $result->getContent());
    }

    public function testTextToSpeechStreamFallsBackToMp3ForNonStringFormat(): void
    {
        $options = $this->createMock(AudioOptionsInterface::class);
        $options->method('toArray')->willReturn(['model' => 'tts-1', 'voice' => 'alloy', 'response_format' => 123]);

        $audioResource = $this->createMock(Audio::class);
        $this->apiClient->method('audio')->willReturn($audioResource);
        $audioResource->method('speechStreamed')->willReturn(new SpeechStreamResponse(new Response(200, [], 'x')));

        $result = $this->openAIClient()->textToSpeechStream('Hello world', $options);

        $this->assertSame('audio/mpeg', $result->getMimeType());
    }

    public function testTextToSpeechStreamFailure(): void
    {
        $options = AudioOptions::textToSpeech();

        $audioResource = $this->createMock(Audio::class);
        $this->apiClient->method('audio')->willReturn($audioResource);
        $audioResource->method('speechStreamed')->willThrowException(new \Exception('Stream error'));

        $this->logger->expects($this->once())->method('error')->with(
            'OpenAI text-to-speech streaming request failed',
            $this->callback(fn ($context) => isset($context['exception']) && $context['input_length'] === 11)
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('OpenAI text-to-speech streaming request failed: Stream error');

        $this->openAIClient()->textToSpeechStream('Hello world', $options);
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
            $this->callback(fn ($context) => isset($context['exception']))
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
            $this->callback(fn ($context) => isset($context['exception']))
        );

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('OpenAI speech translation request failed: Translation error');

        $this->client->translate($audioStream, $options);
    }

    public function testRequestSendsAttachmentsAsContentBlocksBeforeText(): void
    {
        $captured = null;
        $chatResource = $this->createMock(Chat::class);
        $response = $this->createMock(CreateResponse::class);
        $result = $this->createMock(ResultInterface::class);

        $this->apiClient->method('chat')->willReturn($chatResource);
        $chatResource->expects($this->once())->method('create')->willReturnCallback(
            function (array $parameters) use (&$captured, $response): CreateResponse {
                $captured = $parameters;

                return $response;
            }
        );
        $this->injectResultConverter($response, $result);

        $this->assertSame($result, $this->client->request($this->model, $this->attachmentPayload()));

        $this->assertIsArray($captured);
        $this->assertSame([
            ['role' => 'system', 'content' => 'sys'],
            ['role' => 'user', 'content' => [
                ['type' => 'file', 'file' => [
                    'filename' => 'document-1.pdf',
                    'file_data' => 'data:application/pdf;base64,' . base64_encode('%PDF-1.4 x'),
                ]],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . base64_encode('PNGBYTES')]],
                ['type' => 'text', 'text' => 'Extract'],
            ]],
        ], $captured['messages']);
    }

    public function testRequestExpandsAttachmentsInLegacyMessagesPayload(): void
    {
        $captured = null;
        $chatResource = $this->createMock(Chat::class);
        $response = $this->createMock(CreateResponse::class);

        $this->apiClient->method('chat')->willReturn($chatResource);
        $chatResource->method('create')->willReturnCallback(
            function (array $parameters) use (&$captured, $response): CreateResponse {
                $captured = $parameters;

                return $response;
            }
        );
        $this->injectResultConverter($response, $this->createMock(ResultInterface::class));

        $this->client->request($this->model, ['messages' => $this->attachmentPayload()]);

        $this->assertIsArray($captured);
        $this->assertArrayNotHasKey('attachments', $captured['messages'][1]);
        $this->assertSame('file', $captured['messages'][1]['content'][0]['type']);
        $this->assertSame(['type' => 'text', 'text' => 'Extract'], $captured['messages'][1]['content'][2]);
    }

    public function testRequestNamesPdfsByPosition(): void
    {
        $captured = null;
        $chatResource = $this->createMock(Chat::class);
        $response = $this->createMock(CreateResponse::class);

        $this->apiClient->method('chat')->willReturn($chatResource);
        $chatResource->method('create')->willReturnCallback(
            function (array $parameters) use (&$captured, $response): CreateResponse {
                $captured = $parameters;

                return $response;
            }
        );
        $this->injectResultConverter($response, $this->createMock(ResultInterface::class));

        $payload = UserPrompt::create('Compare')
            ->withAttachments(Attachment::fromBytes('PNGBYTES', 'image/png'), Attachment::fromBytes('%PDF-1.4 a', 'application/pdf'), Attachment::fromBytes('%PDF-1.4 b', 'application/pdf'))
        ;
        $this->client->request($this->model, Conversation::fromUser($payload)->toRequestArray());

        $this->assertIsArray($captured);
        $blocks = $captured['messages'][0]['content'];
        $this->assertSame('document-2.pdf', $blocks[1]['file']['filename']);
        $this->assertSame('document-3.pdf', $blocks[2]['file']['filename']);
    }

    public function testRequestRejectsNonAttachmentBeforeCallingApi(): void
    {
        $this->apiClient->expects($this->never())->method('chat');

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Attachments must be a list of Attachment objects.');

        $this->client->request($this->model, [['role' => 'user', 'content' => 'x', 'attachments' => ['not-an-attachment']]]);
    }

    public function testRequestSendsTextAttachmentAsDelimitedTextPart(): void
    {
        $captured = null;
        $chatResource = $this->createMock(Chat::class);
        $response = $this->createMock(CreateResponse::class);

        $this->apiClient->method('chat')->willReturn($chatResource);
        $chatResource->expects($this->once())->method('create')->willReturnCallback(
            function (array $parameters) use (&$captured, $response): CreateResponse {
                $captured = $parameters;

                return $response;
            }
        );
        $this->injectResultConverter($response, $this->createMock(ResultInterface::class));

        $payload = Conversation::fromUser(UserPrompt::create('Sum it'))
            ->withAttachments(Attachment::fromBytes('PNGBYTES', 'image/png'), Attachment::fromBytes("a,b\n1,2", 'text/csv'))
            ->toRequestArray()
        ;
        $this->client->request($this->model, $payload);

        $this->assertIsArray($captured);
        $this->assertSame([
            ['role' => 'user', 'content' => [
                ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . base64_encode('PNGBYTES')]],
                ['type' => 'text', 'text' => "<document-aeedab1ee7a1 name=\"document-2\" type=\"text/csv\">\na,b\n1,2\n</document-aeedab1ee7a1>"],
                ['type' => 'text', 'text' => 'Sum it'],
            ]],
        ], $captured['messages']);
    }

    public function testRequestRejectsDocxBeforeCallingApi(): void
    {
        $this->apiClient->expects($this->never())->method('chat');

        $payload = Conversation::fromUser(UserPrompt::create('Summarize'))
            ->withAttachments(Attachment::fromBytes('PK docx', Attachment::DOCX))
            ->toRequestArray()
        ;

        try {
            $this->client->request($this->model, $payload);
            $this->fail('Expected UnsupportedCapabilityException');
        } catch (UnsupportedCapabilityException $e) {
            $this->assertNotInstanceOf(ClientException::class, $e);
            $this->assertSame(sprintf('OpenAI model "gpt-4" does not accept "%s" attachments.', Attachment::DOCX), $e->getMessage());
        }
    }

    private function openAIClient(): OpenAIClient
    {
        self::assertInstanceOf(OpenAIClient::class, $this->client);

        return $this->client;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attachmentPayload(): array
    {
        return Conversation::withSystem(UserPrompt::create('Extract'), SystemPrompt::create('sys'))
            ->withAttachments(Attachment::fromBytes('%PDF-1.4 x', 'application/pdf'), Attachment::fromBytes('PNGBYTES', 'image/png'))
            ->toRequestArray()
        ;
    }

    private function injectResultConverter(CreateResponse $response, ResultInterface $result): void
    {
        $resultConverter = $this->createMock(OpenAIResultConverter::class);
        $resultConverter->method('convert')->with($this->model, $response)->willReturn($result);
        (new ReflectionClass($this->client))->getProperty('resultConverter')->setValue($this->client, $resultConverter);
    }

    public function testFailedAttachmentRequestKeepsTheDocumentOutOfLogAndException(): void
    {
        $chat = $this->createMock(Chat::class);
        $this->apiClient->method('chat')->willReturn($chat);
        $chat->method('create')->willThrowException(new \RuntimeException('Upstream 500'));
        $this->logger->expects($this->once())->method('error')->with(
            'OpenAI request with attachments failed',
            $this->callback(fn (array $context): bool => !isset($context['exception']) && $context['exception_class'] === \RuntimeException::class)
        );

        try {
            $this->client->request($this->model, Conversation::fromUser(UserPrompt::create('Extract'))->withAttachments(Attachment::fromBytes('%PDF-1.4 SECRET', 'application/pdf'))->toRequestArray());
            $this->fail('Expected ClientException');
        } catch (ClientException $e) {
            $this->assertNull($e->getPrevious());
            $this->assertStringContainsString('Upstream 500', $e->getMessage());
        }
    }

    public function testFailedTextRequestStillChainsThePreviousException(): void
    {
        $chat = $this->createMock(Chat::class);
        $this->apiClient->method('chat')->willReturn($chat);
        $chat->method('create')->willThrowException($cause = new \RuntimeException('Upstream 500'));

        try {
            $this->client->request($this->model, 'Hello');
            $this->fail('Expected ClientException');
        } catch (ClientException $e) {
            $this->assertSame($cause, $e->getPrevious());
        }
    }

    public function testAttachmentFailureTraceDoesNotCarryTheDocument(): void
    {
        if (ini_get('zend.exception_ignore_args') === '1') {
            $this->markTestSkipped('Trace arguments are not recorded with zend.exception_ignore_args=On.');
        }

        $chat = $this->createMock(Chat::class);
        $this->apiClient->method('chat')->willReturn($chat);
        $chat->method('create')->willThrowException(new \RuntimeException('Upstream 500'));

        try {
            $this->client->request($this->model, Conversation::fromUser(UserPrompt::create('Extract'))->withAttachments(Attachment::fromBytes('%PDF-1.4 TRACE-MARKER', 'application/pdf'))->toRequestArray());
            $this->fail('Expected ClientException');
        } catch (ClientException $e) {
            // Only scalar strings reach Sentry frame vars here; SDK objects are rendered as class names
            $strings = [];
            $args = array_map(static fn (array $frame): array => $frame['args'] ?? [], $e->getTrace());
            array_walk_recursive($args, static function (mixed $value) use (&$strings): void {
                if (is_string($value)) {
                    $strings[] = $value;
                }
            });
            $this->assertStringNotContainsString(base64_encode('%PDF-1.4 TRACE-MARKER'), implode("\n", $strings));
        }
    }

    public function testAttachmentFailureFramesDoNotHoldTheCaughtException(): void
    {
        if (ini_get('zend.exception_ignore_args') === '1') {
            $this->markTestSkipped('Trace arguments are not recorded with zend.exception_ignore_args=On.');
        }

        $chat = $this->createMock(Chat::class);
        $this->apiClient->method('chat')->willReturn($chat);
        $chat->method('create')->willThrowException(new \RuntimeException('Upstream 500'));

        try {
            $this->client->request($this->model, Conversation::fromUser(UserPrompt::create('Extract'))->withAttachments(Attachment::fromBytes('%PDF-1.4 x', 'application/pdf'))->toRequestArray());
            $this->fail('Expected ClientException');
        } catch (ClientException $e) {
            $this->assertFalse(self::traceHoldsThrowable($e));
        }
    }

    private static function traceHoldsThrowable(\Throwable $e): bool
    {
        $found = false;
        $args = array_map(static fn (array $frame): array => $frame['args'] ?? [], $e->getTrace());
        array_walk_recursive($args, static function (mixed $value) use (&$found): void {
            $found = $found || $value instanceof \Throwable;
        });

        return $found;
    }
}
