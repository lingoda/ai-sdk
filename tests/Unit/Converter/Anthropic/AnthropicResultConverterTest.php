<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Converter\Anthropic;

use Lingoda\AiSdk\Converter\Anthropic\AnthropicResultConverter;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;
use PHPUnit\Framework\TestCase;

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