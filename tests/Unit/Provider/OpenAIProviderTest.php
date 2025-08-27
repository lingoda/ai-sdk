<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Provider;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Model\ConfigurableModel;
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\ProviderInterface;

final class OpenAIProviderTest extends ProviderTestCase
{
    protected function createProvider(): ProviderInterface
    {
        return new OpenAIProvider();
    }
    
    protected function getExpectedId(): string
    {
        return 'openai';
    }
    
    protected function getExpectedName(): string
    {
        return 'OpenAI';
    }
    
    protected function getExpectedModelIds(): array
    {
        return ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo'];
    }
    
    protected function getProviderEnum(): AIProvider
    {
        return AIProvider::OPENAI;
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
        $model = $this->provider->getModel('gpt-4o-mini');
        
        $this->assertInstanceOf(ConfigurableModel::class, $model);
    }
}