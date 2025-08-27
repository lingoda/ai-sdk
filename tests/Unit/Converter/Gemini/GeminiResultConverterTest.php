<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Converter\Gemini;

use Gemini\Data\Candidate;
use Gemini\Data\Content;
use Gemini\Data\UsageMetadata;
use Gemini\Enums\FinishReason;
use Gemini\Enums\Role;
use Gemini\Responses\GenerativeModel\GenerateContentResponse;
use Lingoda\AiSdk\Converter\Gemini\GeminiResultConverter;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\RuntimeException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Result\ToolCall;
use Lingoda\AiSdk\Result\ToolCallResult;
use PHPUnit\Framework\TestCase;
use stdClass;

final class GeminiResultConverterTest extends TestCase
{
    private GeminiResultConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new GeminiResultConverter();
    }

    public function testSupportsGeminiProvider(): void
    {
        $model = $this->createMockModel(AIProvider::GEMINI);
        $response = $this->createMock(GenerateContentResponse::class);

        $this->assertTrue($this->converter->supports($model, $response));
    }

    public function testDoesNotSupportWrongProvider(): void
    {
        $model = $this->createMockModel(AIProvider::OPENAI);
        $response = $this->createMock(GenerateContentResponse::class);

        $this->assertFalse($this->converter->supports($model, $response));
    }

    public function testDoesNotSupportWrongResponseType(): void
    {
        $model = $this->createMockModel(AIProvider::GEMINI);
        $response = new stdClass();

        $this->assertFalse($this->converter->supports($model, $response));
    }

    public function testConvertsTextResponse(): void
    {
        $model = $this->createMockModel(AIProvider::GEMINI);
        $response = $this->createMockTextResponse('Hello world!');

        $result = $this->converter->convert($model, $response);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world!', $result->getContent());

        $metadata = $result->getMetadata();
        $this->assertArrayHasKey('id', $metadata);
        $this->assertArrayHasKey('usage', $metadata);
    }

    public function testConvertsFunctionCallResponse(): void
    {
        $model = $this->createMockModel(AIProvider::GEMINI);
        $response = $this->createMockFunctionCallResponse();

        $result = $this->converter->convert($model, $response);

        $this->assertInstanceOf(ToolCallResult::class, $result);

        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);

        $toolCall = $toolCalls[0];
        $this->assertInstanceOf(ToolCall::class, $toolCall);
        $this->assertSame('get_weather', $toolCall->getName());
        $this->assertSame(['location' => 'Paris'], $toolCall->getArguments());
        // ID should be generated since Gemini doesn't provide one
        $this->assertStringStartsWith('gemini_', $toolCall->getId());
    }

    public function testThrowsExceptionForEmptyCandidates(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No candidates found in Gemini response');

        $model = $this->createMockModel(AIProvider::GEMINI);
        $response = $this->createMock(GenerateContentResponse::class);
        $response->candidates = [];

        $this->converter->convert($model, $response);
    }

    public function testThrowsExceptionForInvalidResponseType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected Gemini GenerateContentResponse object');

        $model = $this->createMockModel(AIProvider::GEMINI);
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

    private function createMockTextResponse(string $text): GenerateContentResponse
    {
        $response = $this->createMock(GenerateContentResponse::class);
        $response->modelVersion = 'gemini-pro';
        $response->usageMetadata = new UsageMetadata(
            promptTokenCount: 10,
            candidatesTokenCount: 5,
            totalTokenCount: 15,
        );

        $candidate = $this->createMock(Candidate::class);
        $candidate->finishReason = FinishReason::STOP;
        $candidate->index = 0;

        $content = $this->createMock(Content::class);
        $content->role = Role::MODEL;

        $part = (object) ['text' => $text];
        $content->parts = [$part];

        $candidate->content = $content;
        $response->candidates = [$candidate];

        return $response;
    }

    private function createMockFunctionCallResponse(): GenerateContentResponse
    {
        $response = $this->createMock(GenerateContentResponse::class);
        $response->modelVersion = 'gemini-pro';
        $response->usageMetadata = new UsageMetadata(
            promptTokenCount: 15,
            candidatesTokenCount: 8,
            totalTokenCount: 23,
        );

        $candidate = $this->createMock(Candidate::class);
        $candidate->finishReason = FinishReason::STOP;
        $candidate->index = 0;

        $content = $this->createMock(Content::class);
        $content->role = Role::MODEL;

        $functionCall = (object) [
            'name' => 'get_weather',
            'args' => ['location' => 'Paris']
        ];
        $part = (object) ['functionCall' => $functionCall];
        $content->parts = [$part];

        $candidate->content = $content;
        $response->candidates = [$candidate];

        return $response;
    }
}
