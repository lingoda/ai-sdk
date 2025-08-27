<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Converter\OpenAI;

use Lingoda\AiSdk\Converter\OpenAI\OpenAIResultConverter;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Result\ToolCall;
use Lingoda\AiSdk\Result\ToolCallResult;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Responses\Chat\CreateResponseChoice;
use OpenAI\Responses\Chat\CreateResponseMessage;
use OpenAI\Responses\Chat\CreateResponseToolCall;
use OpenAI\Responses\Chat\CreateResponseToolCallFunction;
use OpenAI\Responses\Chat\CreateResponseUsage;
use OpenAI\Responses\Chat\CreateResponseUsageCompletionTokensDetails;
use OpenAI\Responses\Chat\CreateResponseUsagePromptTokensDetails;
use PHPUnit\Framework\TestCase;
use stdClass;

final class OpenAIResultConverterTest extends TestCase
{
    private OpenAIResultConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new OpenAIResultConverter();
    }

    public function testSupportsOpenAIProvider(): void
    {
        $model = $this->createMockModel(AIProvider::OPENAI);
        $response = $this->createMock(CreateResponse::class);

        $this->assertTrue($this->converter->supports($model, $response));
    }

    public function testDoesNotSupportWrongProvider(): void
    {
        $model = $this->createMockModel(AIProvider::ANTHROPIC);
        $response = $this->createMock(CreateResponse::class);

        $this->assertFalse($this->converter->supports($model, $response));
    }

    public function testDoesNotSupportWrongResponseType(): void
    {
        $model = $this->createMockModel(AIProvider::OPENAI);
        $response = new stdClass();

        $this->assertFalse($this->converter->supports($model, $response));
    }

    public function testConvertsTextResponse(): void
    {
        $model = $this->createMockModel(AIProvider::OPENAI);
        $response = $this->createMockTextResponse('Hello world!');

        $result = $this->converter->convert($model, $response);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world!', $result->getContent());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('id', $metadata);
        $this->assertArrayHasKey('usage', $metadata);
    }

    public function testConvertsToolCallResponse(): void
    {
        $model = $this->createMockModel(AIProvider::OPENAI);
        $response = $this->createMockToolCallResponse([
            [
                'id' => 'call_abc123',
                'function' => [
                    'name' => 'get_weather',
                    'arguments' => '{"location": "Paris"}'
                ]
            ]
        ]);

        $result = $this->converter->convert($model, $response);

        $this->assertInstanceOf(ToolCallResult::class, $result);

        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);

        $toolCall = $toolCalls[0];
        $this->assertInstanceOf(ToolCall::class, $toolCall);
        $this->assertSame('call_abc123', $toolCall->getId());
        $this->assertSame('get_weather', $toolCall->getName());
        $this->assertSame(['location' => 'Paris'], $toolCall->getArguments());
    }

    public function testThrowsExceptionForInvalidResponseType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected OpenAI CreateResponse object');

        $model = $this->createMockModel(AIProvider::OPENAI);
        $response = new stdClass();

        // @phpstan-ignore-next-line - Testing error handling with wrong type
        $this->converter->convert($model, $response);
    }

    private function createMockModel(AIProvider $provider): ModelInterface
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider->method('is')->willReturnCallback(fn (AIProvider $p) => $p === $provider);

        $model = $this->createMock(ModelInterface::class);
        $model->method('getProvider')->willReturn($mockProvider);
        $model->method('getId')->willReturn('test-model');

        return $model;
    }

    private function createMockTextResponse(string $content): CreateResponse
    {
        $response = $this->createMock(CreateResponse::class);
        $response->id = 'chat-123';
        $response->model = 'gpt-4';
        $response->created = 1234567890;
        $response->systemFingerprint = 'fp_test';

        $choice = $this->createMock(CreateResponseChoice::class);
        $choice->finishReason = 'stop';

        $message = $this->createMock(CreateResponseMessage::class);
        $message->content = $content;
        $message->toolCalls = [];
        $choice->message = $message;
        $response->choices = [$choice];

        $usage = $this->createMock(CreateResponseUsage::class);
        $usage->promptTokens = 10;
        $usage->completionTokens = 5;
        $usage->totalTokens = 15;

        $promptTokensDetails = $this->createMock(CreateResponseUsagePromptTokensDetails::class);
        $promptTokensDetails->cachedTokens = 0;
        $promptTokensDetails->audioTokens = 0;
        $usage->promptTokensDetails = $promptTokensDetails;

        $completionTokensDetails = $this->createMock(CreateResponseUsageCompletionTokensDetails::class);
        $completionTokensDetails->reasoningTokens = 0;
        $completionTokensDetails->audioTokens = 0;
        $completionTokensDetails->acceptedPredictionTokens = 0;
        $completionTokensDetails->rejectedPredictionTokens = 0;
        $usage->completionTokensDetails = $completionTokensDetails;

        $response->usage = $usage;

        return $response;
    }

    /**
     * @param array<int, array{id: string, function: array{name: string, arguments: string}}> $toolCallsData
     */
    private function createMockToolCallResponse(array $toolCallsData): CreateResponse
    {
        $response = $this->createMock(CreateResponse::class);
        $response->id = 'chat-123';
        $response->model = 'gpt-4';
        $response->created = 1234567890;
        $response->systemFingerprint = 'fp_test';

        $choice = $this->createMock(CreateResponseChoice::class);
        $choice->finishReason = 'tool_calls';

        $message = $this->createMock(CreateResponseMessage::class);
        $message->content = null;

        $toolCalls = [];
        foreach ($toolCallsData as $toolCallData) {
            $toolCall = $this->createMock(CreateResponseToolCall::class);
            $toolCall->id = $toolCallData['id'];

            $function = $this->createMock(CreateResponseToolCallFunction::class);
            $function->name = $toolCallData['function']['name'];
            $function->arguments = $toolCallData['function']['arguments'];
            $toolCall->function = $function;
            $toolCalls[] = $toolCall;
        }

        $message->toolCalls = $toolCalls;
        $choice->message = $message;
        $response->choices = [$choice];

        $usage = $this->createMock(CreateResponseUsage::class);
        $usage->promptTokens = 10;
        $usage->completionTokens = 5;
        $usage->totalTokens = 15;

        $promptTokensDetails = $this->createMock(CreateResponseUsagePromptTokensDetails::class);
        $promptTokensDetails->cachedTokens = 0;
        $promptTokensDetails->audioTokens = 0;
        $usage->promptTokensDetails = $promptTokensDetails;

        $completionTokensDetails = $this->createMock(CreateResponseUsageCompletionTokensDetails::class);
        $completionTokensDetails->reasoningTokens = 0;
        $completionTokensDetails->audioTokens = 0;
        $completionTokensDetails->acceptedPredictionTokens = 0;
        $completionTokensDetails->rejectedPredictionTokens = 0;
        $usage->completionTokensDetails = $completionTokensDetails;

        $response->usage = $usage;

        return $response;
    }
}
