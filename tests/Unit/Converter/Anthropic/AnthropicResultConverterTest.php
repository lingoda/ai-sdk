<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Converter\Anthropic;

use Anthropic\Responses\Messages\CreateResponse;
use Lingoda\AiSdk\Converter\Anthropic\AnthropicResultConverter;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Result\ToolCall;
use Lingoda\AiSdk\Result\ToolCallResult;
use PHPUnit\Framework\TestCase;
use stdClass;

final class AnthropicResultConverterTest extends TestCase
{
    private AnthropicResultConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new AnthropicResultConverter();
    }

    public function testSupportsAnthropicProvider(): void
    {
        $model = $this->createMockModel(AIProvider::ANTHROPIC);

        $this->assertTrue($model->getProvider()->is(AIProvider::ANTHROPIC));
        $this->assertFalse($model->getProvider()->is(AIProvider::OPENAI));
    }

    public function testDoesNotSupportWrongProvider(): void
    {
        $model = $this->createMockModel(AIProvider::GEMINI);
        $response = new stdClass();

        $this->assertFalse($this->converter->supports($model, $response));
    }

    public function testDoesNotSupportWrongResponseType(): void
    {
        $model = $this->createMockModel(AIProvider::ANTHROPIC);
        $response = new stdClass();

        $this->assertFalse($this->converter->supports($model, $response));
    }

    public function testConvertsTextResponse(): void
    {
        $model = $this->createMockModel(AIProvider::ANTHROPIC);
        $response = $this->createMockTextResponse('Hello world!');

        $result = $this->converter->convert($model, $response);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world!', $result->getContent());

        $metadata = $result->getMetadata();
        $this->assertSame('msg_01ABC123', $metadata['id']);
        $this->assertSame('message', $metadata['type']);
        $this->assertSame('claude-3-opus-20240229', $metadata['model']);
        $this->assertSame('assistant', $metadata['role']);
        $this->assertSame('end_turn', $metadata['stop_reason']);
    }

    public function testConvertsToolCallResponse(): void
    {
        $model = $this->createMockModel(AIProvider::ANTHROPIC);
        $response = $this->createMockToolCallResponse();

        $result = $this->converter->convert($model, $response);

        $this->assertInstanceOf(ToolCallResult::class, $result);

        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);

        $toolCall = $toolCalls[0];
        $this->assertInstanceOf(ToolCall::class, $toolCall);
        $this->assertSame('toolu_01A2B3C4D5E6F', $toolCall->getId());
        $this->assertSame('get_weather', $toolCall->getName());
        $this->assertSame(['location' => 'Paris', 'unit' => 'celsius'], $toolCall->getArguments());
    }

    public function testConvertsMixedContentResponse(): void
    {
        $model = $this->createMockModel(AIProvider::ANTHROPIC);
        $response = $this->createMockMixedContentResponse();

        $result = $this->converter->convert($model, $response);

        // Should return ToolCallResult when tool calls are present
        $this->assertInstanceOf(ToolCallResult::class, $result);

        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('get_weather', $toolCalls[0]->getName());
    }

    public function testHandlesEmptyContent(): void
    {
        $model = $this->createMockModel(AIProvider::ANTHROPIC);
        $response = $this->createMockEmptyContentResponse();

        $result = $this->converter->convert($model, $response);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('', $result->getContent());
    }

    public function testThrowsExceptionForInvalidResponseType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected Anthropic CreateResponse object');

        $model = $this->createMockModel(AIProvider::ANTHROPIC);
        $response = new stdClass();

        // @phpstan-ignore-next-line - Testing error handling with wrong type
        $this->converter->convert($model, $response);
    }

    private function createMockModel(AIProvider $provider): ModelInterface
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider->method('getId')->willReturn($provider->value);
        $mockProvider->method('is')->willReturnCallback(fn (AIProvider $p) => $p === $provider);

        $model = $this->createMock(ModelInterface::class);
        $model->method('getProvider')->willReturn($mockProvider);
        $model->method('getId')->willReturn('test-model');

        return $model;
    }

    private function createMockTextResponse(string $text): CreateResponse
    {
        $response = $this->createMock(CreateResponse::class);
        $response->method('toArray')->willReturn([
            'id' => 'msg_01ABC123',
            'type' => 'message',
            'model' => 'claude-3-opus-20240229',
            'role' => 'assistant',
            'stop_reason' => 'end_turn',
            'content' => [
                ['type' => 'text', 'text' => $text]
            ],
            'usage' => [
                'input_tokens' => 20,
                'output_tokens' => 15
            ]
        ]);

        return $response;
    }

    private function createMockToolCallResponse(): CreateResponse
    {
        $response = $this->createMock(CreateResponse::class);
        $response->method('toArray')->willReturn([
            'id' => 'msg_01XYZ789',
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_01A2B3C4D5E6F',
                    'name' => 'get_weather',
                    'input' => ['location' => 'Paris', 'unit' => 'celsius']
                ]
            ]
        ]);

        return $response;
    }

    private function createMockMixedContentResponse(): CreateResponse
    {
        $response = $this->createMock(CreateResponse::class);
        $response->method('toArray')->willReturn([
            'id' => 'msg_mixed',
            'content' => [
                ['type' => 'text', 'text' => 'I\'ll check the weather for you.'],
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_weather',
                    'name' => 'get_weather',
                    'input' => ['location' => 'London']
                ],
                ['type' => 'text', 'text' => ' Let me get that information.']
            ]
        ]);

        return $response;
    }

    private function createMockEmptyContentResponse(): CreateResponse
    {
        $response = $this->createMock(CreateResponse::class);
        $response->method('toArray')->willReturn([
            'id' => 'msg_empty',
            'content' => []
        ]);

        return $response;
    }
}
