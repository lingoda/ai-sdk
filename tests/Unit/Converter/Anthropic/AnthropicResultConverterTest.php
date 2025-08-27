<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Converter\Anthropic;

use Lingoda\AiSdk\Converter\Anthropic\AnthropicResultConverter;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;
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
}
