<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Provider;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Model\ConfigurableModel;
use Lingoda\AiSdk\Provider\AnthropicProvider;
use Lingoda\AiSdk\ProviderInterface;

final class AnthropicProviderTest extends ProviderTestCase
{
    protected function createProvider(): ProviderInterface
    {
        return new AnthropicProvider();
    }
    
    protected function getExpectedId(): string
    {
        return 'anthropic';
    }
    
    protected function getExpectedName(): string
    {
        return 'Anthropic';
    }
    
    protected function getExpectedModelIds(): array
    {
        return [
            'claude-opus-4-1-20250805',
            'claude-opus-4-20250514',
            'claude-sonnet-4-20250514',
            'claude-3-7-sonnet-20250219',
            'claude-3-5-haiku-20241022',
            'claude-3-haiku-20240307',
        ];
    }
    
    protected function getProviderEnum(): AIProvider
    {
        return AIProvider::ANTHROPIC;
    }

    public function testGetModelsReturnsConfigurableModelInstances(): void
    {
        $models = $this->provider->getModels();
        
        foreach ($models as $model) {
            $this->assertInstanceOf(ConfigurableModel::class, $model);
        }
    }

    public function testGetModelReturnsConfigurableModel(): void
    {
        $model = $this->provider->getModel('claude-sonnet-4-20250514');
        
        $this->assertInstanceOf(ConfigurableModel::class, $model);
    }
}