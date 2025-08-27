<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Converter\Gemini;

use Lingoda\AiSdk\Converter\Gemini\GeminiResultConverter;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Result\ToolCallResult;
use PHPUnit\Framework\TestCase;

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
        
        $this->assertTrue($model->getProvider()->is(AIProvider::GEMINI));
        $this->assertFalse($model->getProvider()->is(AIProvider::OPENAI));
    }

    private function createMockModel(AIProvider $provider): ModelInterface
    {
        $mockProvider = $this->createMock(ProviderInterface::class);
        $mockProvider->method('getId')->willReturn($provider->value);
        $mockProvider->method('is')->willReturnCallback(function(AIProvider $p) use ($provider) {
            return $p === $provider;
        });

        $model = $this->createMock(ModelInterface::class);
        $model->method('getProvider')->willReturn($mockProvider);
        $model->method('getId')->willReturn('test-model');

        return $model;
    }
}