<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Provider;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Model\ConfigurableModel;
use Lingoda\AiSdk\Provider\GeminiProvider;
use Lingoda\AiSdk\ProviderInterface;

final class GeminiProviderTest extends ProviderTestCase
{
    protected function createProvider(): ProviderInterface
    {
        return new GeminiProvider();
    }
    
    protected function getExpectedId(): string
    {
        return 'gemini';
    }
    
    protected function getExpectedName(): string
    {
        return 'Google Gemini';
    }
    
    protected function getExpectedModelIds(): array
    {
        return [
            'gemini-2.5-pro',
            'gemini-2.5-flash',
        ];
    }
    
    protected function getProviderEnum(): AIProvider
    {
        return AIProvider::GEMINI;
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
        $model = $this->provider->getModel('gemini-2.5-flash');
        
        $this->assertInstanceOf(ConfigurableModel::class, $model);
    }
}