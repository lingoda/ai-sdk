<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Client\Bedrock;

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use AsyncAws\Core\Credentials\Credentials;
use Lingoda\AiSdk\Client\Bedrock\BedrockClient;
use Lingoda\AiSdk\Client\Bedrock\BedrockClientFactory;
use Lingoda\AiSdk\Enum\Bedrock\ChatModel;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\UnsupportedCapabilityException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Prompt\AssistantPrompt;
use Lingoda\AiSdk\Prompt\Attachment;
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Provider\BedrockProvider;
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Result\ToolCallResult;
use Lingoda\AiSdk\Tests\Unit\Security\TestLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[Group('bedrock')]
final class BedrockClientTest extends TestCase
{
    private const string PDF = "%PDF-1.4 SECRET-VOUCHER-MARKER";

    /** @var list<array{url: string, body: array<string, mixed>}> */
    private array $requests = [];

    /** @var list<MockResponse> */
    private array $responses = [];

    private TestLogger $logger;

    protected function setUp(): void
    {
        $this->requests = [];
        $this->responses = [];
        $this->logger = new TestLogger();
    }

    public function testHaikuSendsDocumentBeforeTextInAnthropicShape(): void
    {
        $this->responses[] = $this->claudeResponse('{"a": 1}');

        $result = $this->client()->request($this->model(ChatModel::CLAUDE_HAIKU_45), $this->pdfConversation()->toRequestArray());

        self::assertInstanceOf(TextResult::class, $result);
        self::assertSame('{"a": 1}', $result->getContent());

        $request = $this->requests[0];
        self::assertStringContainsString('/model/eu.anthropic.claude-haiku-4-5-20251001-v1:0/invoke', $request['url']);
        self::assertSame('bedrock-2023-05-31', $request['body']['anthropic_version']);
        self::assertSame(4096, $request['body']['max_tokens']);
        self::assertSame([['type' => 'text', 'text' => 'You extract voucher fields.']], $request['body']['system']);
        self::assertSame([
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode(self::PDF)]],
                    ['type' => 'text', 'text' => 'Extract the fields.'],
                ],
            ],
        ], $request['body']['messages']);
        self::assertArrayNotHasKey('model', $request['body']);
    }

    public function testNovaSendsDocumentBlockInConverseShape(): void
    {
        $this->responses[] = $this->novaResponse('ok');

        $this->client()->request($this->model(ChatModel::NOVA_2_LITE), $this->pdfConversation()->toRequestArray());

        $request = $this->requests[0];
        self::assertStringContainsString('/model/eu.amazon.nova-2-lite-v1:0/invoke', $request['url']);
        self::assertSame([['text' => 'You extract voucher fields.']], $request['body']['system']);
        self::assertSame(['maxTokens' => 4096], $request['body']['inferenceConfig']);
        self::assertSame([
            [
                'role' => 'user',
                'content' => [
                    ['document' => ['format' => 'pdf', 'name' => 'document-1', 'source' => ['bytes' => base64_encode(self::PDF)]]],
                    ['text' => 'Extract the fields.'],
                ],
            ],
        ], $request['body']['messages']);
    }

    /**
     * A shared Symfony AI Contract caches the normalizer per PHP type: once Nova normalized a UserMessage,
     * later Claude calls got the Nova shape. Nova first is the order that exposes it; images, because upstream Nova
     * rejects PDFs outright.
     */
    public function testModelFamiliesDoNotShareNormalizersInOneWorker(): void
    {
        $this->responses = [$this->novaResponse('a'), $this->claudeResponse('b'), $this->novaResponse('c'), $this->claudeResponse('d')];
        $client = $this->client();
        $payload = Conversation::fromUser(UserPrompt::create('Describe'))
            ->withAttachments(Attachment::fromBytes('PNG-BYTES', 'image/png'))
            ->toRequestArray()
        ;

        $client->request($this->model(ChatModel::NOVA_2_LITE), $payload);
        $client->request($this->model(ChatModel::CLAUDE_HAIKU_45), $payload);
        $client->request($this->model(ChatModel::NOVA_2_LITE), $payload);
        $client->request($this->model(ChatModel::CLAUDE_HAIKU_45), $payload);

        self::assertCount(4, $this->requests);
        foreach ([0, 2] as $nova) {
            self::assertArrayHasKey('image', $this->firstUserPart($this->requests[$nova]));
        }
        foreach ([1, 3] as $claude) {
            self::assertSame('image', $this->firstUserPart($this->requests[$claude])['type'] ?? null);
        }
    }

    public function testImageAttachmentsBecomeImageBlocks(): void
    {
        $this->responses = [$this->claudeResponse('a'), $this->novaResponse('b')];
        $client = $this->client();
        $payload = Conversation::fromUser(UserPrompt::create('Describe'))
            ->withAttachments(Attachment::fromBytes('PNG-BYTES', 'image/png'))
            ->toRequestArray()
        ;

        $client->request($this->model(ChatModel::CLAUDE_HAIKU_45), $payload);
        $client->request($this->model(ChatModel::NOVA_2_LITE), $payload);

        self::assertSame(
            ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => base64_encode('PNG-BYTES')]],
            $this->firstUserPart($this->requests[0])
        );
        self::assertSame(
            ['image' => ['format' => 'png', 'source' => ['bytes' => base64_encode('PNG-BYTES')]]],
            $this->firstUserPart($this->requests[1])
        );
    }

    public function testTextAttachmentIsSentAsTextOnBothModels(): void
    {
        $this->responses = [$this->claudeResponse('a'), $this->novaResponse('b')];
        $client = $this->client();
        $payload = Conversation::fromUser(UserPrompt::create('Sum it'))
            ->withAttachments(Attachment::fromBytes("a,b\n1,2", 'text/csv'))
            ->toRequestArray()
        ;
        $rendered = "<document-aeedab1ee7a1 name=\"document-1\" type=\"text/csv\">\na,b\n1,2\n</document-aeedab1ee7a1>";

        $client->request($this->model(ChatModel::CLAUDE_HAIKU_45), $payload);
        $client->request($this->model(ChatModel::NOVA_2_LITE), $payload);

        self::assertSame(
            [['role' => 'user', 'content' => [['type' => 'text', 'text' => $rendered], ['type' => 'text', 'text' => 'Sum it']]]],
            $this->requests[0]['body']['messages']
        );
        self::assertSame(
            [['role' => 'user', 'content' => [['text' => $rendered], ['text' => 'Sum it']]]],
            $this->requests[1]['body']['messages']
        );
    }

    public function testNovaSendsDocxAsDocxDocumentBlock(): void
    {
        $this->responses[] = $this->novaResponse('ok');
        $payload = Conversation::fromUser(UserPrompt::create('Summarize'))
            ->withAttachments(Attachment::fromBytes('PK docx', Attachment::DOCX))
            ->toRequestArray()
        ;

        $this->client()->request($this->model(ChatModel::NOVA_2_LITE), $payload);

        self::assertSame(
            ['document' => ['format' => 'docx', 'name' => 'document-1', 'source' => ['bytes' => base64_encode('PK docx')]]],
            $this->firstUserPart($this->requests[0])
        );
    }

    public function testClaudeRejectsDocxBeforeAnyHttpRequest(): void
    {
        $payload = Conversation::fromUser(UserPrompt::create('Summarize'))
            ->withAttachments(Attachment::fromBytes('PK docx', Attachment::DOCX))
            ->toRequestArray()
        ;

        try {
            $this->client()->request($this->model(ChatModel::CLAUDE_HAIKU_45), $payload);
            self::fail('Expected UnsupportedCapabilityException');
        } catch (UnsupportedCapabilityException $e) {
            self::assertSame(
                sprintf('Bedrock model "%s" does not accept "%s" attachments.', ChatModel::CLAUDE_HAIKU_45->value, Attachment::DOCX),
                $e->getMessage()
            );
        }

        self::assertSame([], $this->requests);
    }

    public function testUsageAndMetadataAreSet(): void
    {
        $this->responses = [$this->claudeResponse('a'), $this->novaResponse('b')];
        $client = $this->client();

        $claude = $client->request($this->model(ChatModel::CLAUDE_HAIKU_45), 'Hello');
        $nova = $client->request($this->model(ChatModel::NOVA_2_LITE), 'Hello');

        self::assertSame('eu.anthropic.claude-haiku-4-5-20251001-v1:0', $claude->getMetadata()['model']);
        self::assertSame('bedrock', $claude->getMetadata()['provider']);
        self::assertSame('end_turn', $claude->getMetadata()['stop_reason']);
        self::assertSame(['prompt_tokens' => 1200, 'completion_tokens' => 30, 'total_tokens' => 1230], $claude->getUsage()?->toLangfuse());

        self::assertSame('eu.amazon.nova-2-lite-v1:0', $nova->getMetadata()['model']);
        self::assertSame('end_turn', $nova->getMetadata()['stop_reason']);
        self::assertSame(['prompt_tokens' => 900, 'completion_tokens' => 20, 'total_tokens' => 920], $nova->getUsage()?->toLangfuse());
    }

    public function testUnsupportedOptionsAreDroppedAndCallerOptionsWin(): void
    {
        $this->responses = [$this->claudeResponse('a'), $this->novaResponse('b')];
        $client = $this->client();
        $options = ['temperature' => 0, 'max_tokens' => 1500, 'top_p' => 0.9, 'response_mime_type' => 'application/json'];

        $client->request($this->model(ChatModel::CLAUDE_HAIKU_45), 'Hello', $options);
        $client->request($this->model(ChatModel::NOVA_2_LITE), 'Hello', $options);

        $claudeBody = $this->requests[0]['body'];
        self::assertSame(0, $claudeBody['temperature']);
        self::assertSame(1500, $claudeBody['max_tokens']);
        self::assertArrayNotHasKey('top_p', $claudeBody);
        self::assertArrayNotHasKey('response_mime_type', $claudeBody);

        self::assertSame(['temperature' => 0, 'maxTokens' => 1500], $this->requests[1]['body']['inferenceConfig']);
    }

    public function testEmptySystemPromptIsNotSent(): void
    {
        $this->responses[] = $this->novaResponse('b');

        $this->client()->request($this->model(ChatModel::NOVA_2_LITE), [
            ['role' => 'system', 'content' => '  '],
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        self::assertArrayNotHasKey('system', $this->requests[0]['body']);
    }

    public function testAwsErrorBecomesClientExceptionWithoutPayloadOrPrevious(): void
    {
        $this->responses[] = new MockResponse('{"message":"The provided document is malformed."}', [
            'http_code' => 400,
            'response_headers' => ['x-amzn-ErrorType' => 'ValidationException:http://internal', 'x-amzn-RequestId' => 'req-123'],
        ]);

        try {
            $this->client()->request($this->model(ChatModel::NOVA_2_LITE), $this->pdfConversation()->toRequestArray());
            self::fail('Expected ClientException');
        } catch (ClientException $e) {
            self::assertNull($e->getPrevious());
            self::assertSame(400, $e->getCode());
            self::assertStringContainsString('ValidationException', $e->getMessage());
            self::assertStringContainsString('The provided document is malformed.', $e->getMessage());
            self::assertStringNotContainsString('SECRET-VOUCHER-MARKER', $e->getMessage());
            self::assertStringNotContainsString(base64_encode(self::PDF), $e->getMessage());
        }

        $record = $this->errorRecord();
        self::assertNotNull($record);
        self::assertSame('ValidationException', $record['context']['aws_code']);
        self::assertSame('req-123', $record['context']['request_id']);
        self::assertArrayNotHasKey('exception', $record['context']);
        $logged = (string) json_encode($this->logger->records);
        self::assertStringNotContainsString(base64_encode(self::PDF), $logged);
        self::assertStringNotContainsString('SECRET-VOUCHER-MARKER', $logged);
    }

    public function testUnreadableResponseBecomesClientException(): void
    {
        $this->responses[] = new MockResponse('{"output": {}}', ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);

        $this->expectException(ClientException::class);
        $this->client()->request($this->model(ChatModel::NOVA_2_LITE), 'Hello');
    }

    public function testNonBedrockModelIsRejectedBeforeAnyCall(): void
    {
        $model = (new OpenAIProvider())->getModel('gpt-4o-mini');

        try {
            $this->client()->request($model, 'Hello');
            self::fail('Expected UnsupportedCapabilityException');
        } catch (UnsupportedCapabilityException) {
        }

        self::assertSame([], $this->requests);
    }

    public function testNovaRejectsAssistantPromptBeforeUserBeforeAnyCall(): void
    {
        $payload = Conversation::fromUser(UserPrompt::create('What about now?'))
            ->withAssistantPrompt(AssistantPrompt::create('I helped before'))
            ->toRequestArray()
        ;

        try {
            $this->client()->request($this->model(ChatModel::NOVA_2_LITE), $payload);
            self::fail('Expected UnsupportedCapabilityException');
        } catch (UnsupportedCapabilityException $e) {
            self::assertStringContainsString('start with the user message', $e->getMessage());
        }

        self::assertSame([], $this->requests);
    }

    public function testClaudeAcceptsAssistantPromptBeforeUser(): void
    {
        $this->responses[] = $this->claudeResponse('ok');
        $payload = Conversation::fromUser(UserPrompt::create('What about now?'))
            ->withAssistantPrompt(AssistantPrompt::create('I helped before'))
            ->toRequestArray()
        ;

        $this->client()->request($this->model(ChatModel::CLAUDE_HAIKU_45), $payload);

        self::assertSame(['assistant', 'user'], array_column($this->requests[0]['body']['messages'], 'role'));
    }

    public function testPayloadWithoutUserMessageIsRejected(): void
    {
        $this->expectException(ClientException::class);
        $this->client()->request($this->model(ChatModel::NOVA_2_LITE), [['role' => 'system', 'content' => 'x']]);
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function malformedPayloads(): iterable
    {
        yield 'message without content' => [[['role' => 'user']]];
        yield 'unknown role' => [[['role' => 'tool', 'content' => 'x'], ['role' => 'user', 'content' => 'x']]];
        yield 'attachments not a list' => [[['role' => 'user', 'content' => 'x', 'attachments' => 'nope']]];
        yield 'attachment not an Attachment' => [[['role' => 'user', 'content' => 'x', 'attachments' => ['nope']]]];
    }

    /**
     * @param array<mixed> $payload
     */
    #[DataProvider('malformedPayloads')]
    public function testMalformedPayloadIsRejectedBeforeAnyCall(array $payload): void
    {
        try {
            $this->client()->request($this->model(ChatModel::NOVA_2_LITE), $payload);
            self::fail('Expected ClientException');
        } catch (ClientException) {
        }

        self::assertSame([], $this->requests);
    }

    public function testClaudeMultiPartTextIsJoined(): void
    {
        $this->responses[] = $this->claudeBody(['content' => [['type' => 'text', 'text' => 'Hello '], ['type' => 'text', 'text' => 'world']], 'stop_reason' => 'end_turn']);

        $result = $this->client()->request($this->model(ChatModel::CLAUDE_HAIKU_45), 'Hi');

        self::assertInstanceOf(TextResult::class, $result);
        self::assertStringContainsString('Hello', $result->getContent());
        self::assertStringContainsString('world', $result->getContent());
    }

    public function testClaudeToolUseBecomesToolCallResult(): void
    {
        $this->responses[] = $this->claudeBody([
            'content' => [['type' => 'tool_use', 'id' => 'call_1', 'name' => 'lookup', 'input' => ['q' => 'x']]],
            'stop_reason' => 'tool_use',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]);

        $result = $this->client()->request($this->model(ChatModel::CLAUDE_HAIKU_45), 'Hi');

        self::assertInstanceOf(ToolCallResult::class, $result);
        $call = $result->getContent()[0];
        self::assertSame(['call_1', 'lookup', ['q' => 'x']], [$call->getId(), $call->getName(), $call->getArguments()]);
        self::assertSame('tool_use', $result->getMetadata()['stop_reason']);
    }

    public function testThinkingOnlyResponseReportsTheStopReason(): void
    {
        $this->responses[] = $this->claudeBody(['content' => [['type' => 'thinking', 'thinking' => 'hmm', 'signature' => 's']], 'stop_reason' => 'max_tokens']);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('No text in the Bedrock response (stop_reason: max_tokens)');

        $this->client()->request($this->model(ChatModel::CLAUDE_HAIKU_45), 'Hi');
    }

    public function testRefusalIsReportedAsSuch(): void
    {
        $this->responses[] = $this->claudeBody(['content' => [], 'stop_reason' => 'refusal']);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('The model refused the request (stop_reason: refusal)');

        $this->client()->request($this->model(ChatModel::CLAUDE_HAIKU_45), 'Hi');
    }

    public function testMissingUsageLeavesUsageNull(): void
    {
        $this->responses[] = $this->claudeBody(['content' => [['type' => 'text', 'text' => 'ok']]]);

        self::assertNull($this->client()->request($this->model(ChatModel::CLAUDE_HAIKU_45), 'Hi')->getUsage());
    }

    public function testNovaDocumentNamesFollowAttachmentPositions(): void
    {
        $this->responses[] = $this->novaResponse('ok');
        $payload = Conversation::fromUser(UserPrompt::create('Read'))
            ->withAttachments(Attachment::fromBytes('a,b', 'text/csv'), Attachment::fromBytes('%PDF-1.4 x', 'application/pdf'))
            ->toRequestArray()
        ;

        $this->client()->request($this->model(ChatModel::NOVA_2_LITE), $payload);

        /** @var list<array{content: list<array<string, mixed>>}> $messages */
        $messages = $this->requests[0]['body']['messages'];
        $parts = $messages[0]['content'];
        self::assertStringContainsString('name="document-1"', (string) ($parts[0]['text'] ?? ''));
        self::assertSame('document-2', $parts[1]['document']['name'] ?? null);
    }

    public function testRegionOutsideEuAndUsIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BedrockClientFactory::createClient($this->runtime('ap-northeast-1'));
    }

    public function testDefaultRegionFallbackIsRejected(): void
    {
        if (getenv('AWS_REGION') !== false || getenv('AWS_DEFAULT_REGION') !== false) {
            self::markTestSkipped('AWS_REGION is set in this environment, so no fallback happens.');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('region explicitly');

        // No ~/.aws/config either: a region there counts as configured
        $configuration = ['sharedConfigFile' => '/nonexistent/config', 'sharedCredentialsFile' => '/nonexistent/credentials'];
        BedrockClientFactory::createClient(new BedrockRuntimeClient($configuration, new Credentials('AKIDTEST', 'SECRETTEST'), new MockHttpClient()));
    }

    public function testRegionPrefixFollowsTheConfiguredRegion(): void
    {
        $this->responses[] = $this->novaResponse('ok');

        $result = BedrockClientFactory::createClient($this->runtime('us-east-2'))
            ->request($this->model(ChatModel::NOVA_2_LITE), 'Hello')
        ;

        self::assertSame('us.amazon.nova-2-lite-v1:0', $result->getMetadata()['model']);
        self::assertStringContainsString('/model/us.amazon.nova-2-lite-v1:0/invoke', $this->requests[0]['url']);
    }

    public function testClaudeResponseFormatBecomesOutputConfig(): void
    {
        $this->responses[] = $this->claudeResponse('{}');
        $schema = ['type' => 'object', 'properties' => ['a' => ['type' => 'string']], 'required' => ['a']];

        $this->client()->request($this->model(ChatModel::CLAUDE_HAIKU_45), 'Hello', [
            'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'x', 'schema' => $schema]],
        ]);

        $body = $this->requests[0]['body'];
        self::assertArrayNotHasKey('response_format', $body);
        self::assertSame('json_schema', $body['output_config']['format']['type'] ?? null);
    }

    /**
     * @return iterable<string, array{ChatModel, array<string, mixed>}>
     */
    public static function meaningfulOptions(): iterable
    {
        yield 'response_format on Nova' => [ChatModel::NOVA_2_LITE, ['response_format' => ['type' => 'json_object']]];
        yield 'tools on Nova' => [ChatModel::NOVA_2_LITE, ['tools' => [['name' => 'x']]]];
        yield 'tools on Claude' => [ChatModel::CLAUDE_HAIKU_45, ['tools' => [['name' => 'x']]]];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('meaningfulOptions')]
    public function testOptionsThatChangeTheResultAreRejectedNotDropped(ChatModel $chatModel, array $options): void
    {
        try {
            $this->client()->request($this->model($chatModel), 'Hello', $options);
            self::fail('Expected UnsupportedCapabilityException');
        } catch (UnsupportedCapabilityException) {
        }

        self::assertSame([], $this->requests);
    }

    public function testThrottlingKeepsTheHttpStatusAsCode(): void
    {
        $this->responses[] = new MockResponse('{"message":"Too many requests"}', [
            'http_code' => 429,
            'response_headers' => ['x-amzn-ErrorType' => 'ThrottlingException'],
        ]);

        try {
            $this->client()->request($this->model(ChatModel::NOVA_2_LITE), 'Hello');
            self::fail('Expected ClientException');
        } catch (ClientException $e) {
            self::assertSame(429, $e->getCode());
        }

        self::assertSame(429, $this->errorRecord()['context']['http_status'] ?? null);
        self::assertStringContainsString('.php:', (string) ($this->errorRecord()['context']['origin'] ?? ''));
    }

    public function testSupportsOnlyBedrockModels(): void
    {
        $client = $this->client();

        self::assertTrue($client->supports($this->model(ChatModel::NOVA_2_LITE)));
        self::assertFalse($client->supports((new OpenAIProvider())->getModel('gpt-4o-mini')));
        self::assertSame('bedrock', $client->getProvider()->getId());
    }

    /**
     * @return array{level: string, message: string, context: array<string, mixed>}|null
     */
    private function errorRecord(): ?array
    {
        foreach ($this->logger->records as $record) {
            if ($record['level'] === 'error') {
                return $record;
            }
        }

        return null;
    }

    private function client(): BedrockClient
    {
        return BedrockClientFactory::createClient($this->runtime('eu-west-1'), $this->logger);
    }

    private function runtime(string $region): BedrockRuntimeClient
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $body = $options['body'] ?? '';
            if (is_callable($body)) {
                $chunks = '';
                while ('' !== $chunk = $body(8192)) {
                    $chunks .= $chunk;
                }
                $body = $chunks;
            }
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode(is_string($body) ? $body : '', true, 512, JSON_THROW_ON_ERROR);
            $this->requests[] = ['url' => rawurldecode($url), 'body' => $decoded];

            return array_shift($this->responses) ?? throw new \LogicException('No mock response left');
        });

        // Static credentials: otherwise the default chain probes IMDS/STS through the mock client
        return new BedrockRuntimeClient(['region' => $region], new Credentials('AKIDTEST', 'SECRETTEST'), $http);
    }

    private function model(ChatModel $chatModel): ModelInterface
    {
        return (new BedrockProvider())->getModel($chatModel->value);
    }

    private function pdfConversation(): Conversation
    {
        return Conversation::withSystem(UserPrompt::create('Extract the fields.'), SystemPrompt::create('You extract voucher fields.'))
            ->withAttachments(Attachment::fromBytes(self::PDF, 'application/pdf'))
        ;
    }

    private function claudeResponse(string $text): MockResponse
    {
        return new MockResponse((string) json_encode([
            'id' => 'msg_1',
            'type' => 'message',
            'role' => 'assistant',
            'content' => [['type' => 'text', 'text' => $text]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 1200, 'output_tokens' => 30],
        ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function claudeBody(array $body): MockResponse
    {
        return new MockResponse((string) json_encode(['id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', ...$body]), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }

    private function novaResponse(string $text): MockResponse
    {
        return new MockResponse((string) json_encode([
            'output' => ['message' => ['role' => 'assistant', 'content' => [['text' => $text]]]],
            'stopReason' => 'end_turn',
            'usage' => ['inputTokens' => 900, 'outputTokens' => 20, 'totalTokens' => 920],
        ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
    }

    /**
     * @param array{url: string, body: array<string, mixed>} $request
     *
     * @return array<string, mixed>
     */
    private function firstUserPart(array $request): array
    {
        /** @var list<array{content: list<array<string, mixed>>}> $messages */
        $messages = $request['body']['messages'];

        return $messages[0]['content'][0];
    }

    public function testFailureExceptionFramesDoNotHoldTheCaughtException(): void
    {
        if (ini_get('zend.exception_ignore_args') === '1') {
            self::markTestSkipped('Trace arguments are not recorded with zend.exception_ignore_args=On.');
        }

        $this->responses[] = new MockResponse('{"message":"bad"}', ['http_code' => 400, 'response_headers' => ['x-amzn-ErrorType' => 'ValidationException']]);

        try {
            $this->client()->request($this->model(ChatModel::NOVA_2_LITE), 'Hello');
            self::fail('Expected ClientException');
        } catch (ClientException $e) {
            self::assertFalse(self::traceHoldsThrowable($e));
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
