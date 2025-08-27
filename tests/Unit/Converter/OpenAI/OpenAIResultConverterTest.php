<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Converter\OpenAI;

use Lingoda\AiSdk\Converter\OpenAI\OpenAIResultConverter;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Result\ToolCallResult;
use OpenAI\Responses\Chat\CreateResponse;
use PHPUnit\Framework\TestCase;

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
        
        $this->assertTrue($model->getProvider()->is(AIProvider::OPENAI));
        $this->assertFalse($model->getProvider()->is(AIProvider::ANTHROPIC));
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