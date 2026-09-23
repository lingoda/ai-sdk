<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Provider;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Enum\Bedrock\ChatModel;
use Lingoda\AiSdk\Model\ConfigurableModel;
use Lingoda\AiSdk\Provider\BedrockProvider;
use Lingoda\AiSdk\ProviderInterface;

final class BedrockProviderTest extends ProviderTestCase
{
    protected function createProvider(): ProviderInterface
    {
        return new BedrockProvider();
    }

    protected function getExpectedId(): string
    {
        return 'bedrock';
    }

    protected function getExpectedName(): string
    {
        return 'AWS Bedrock';
    }

    protected function getExpectedModelIds(): array
    {
        return [
            'amazon.nova-2-lite-v1:0',
            'anthropic.claude-haiku-4-5-20251001-v1:0',
        ];
    }

    protected function getProviderEnum(): AIProvider
    {
        return AIProvider::BEDROCK;
    }

    public function testGetModelReturnsConfigurableModel(): void
    {
        $this->assertInstanceOf(ConfigurableModel::class, $this->provider->getModel(ChatModel::NOVA_2_LITE->value));
    }

    public function testDefaultModelIsNova(): void
    {
        $this->assertSame(ChatModel::NOVA_2_LITE->value, $this->provider->getDefaultModel());
    }

    public function testCustomDefaultModel(): void
    {
        $provider = new BedrockProvider(ChatModel::CLAUDE_HAIKU_45->value);

        $this->assertSame(ChatModel::CLAUDE_HAIKU_45->value, $provider->getDefaultModel());
    }
}
