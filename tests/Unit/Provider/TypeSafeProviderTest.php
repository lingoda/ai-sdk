<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Provider;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Enum\TypeSafe\DecisionModel;
use Lingoda\AiSdk\Model\ConfigurableModel;
use Lingoda\AiSdk\Provider\TypeSafeProvider;
use Lingoda\AiSdk\ProviderInterface;

final class TypeSafeProviderTest extends ProviderTestCase
{
    protected function createProvider(): ProviderInterface
    {
        return new TypeSafeProvider();
    }

    protected function getExpectedId(): string
    {
        return 'typesafe';
    }

    protected function getExpectedName(): string
    {
        return 'TypeSafe';
    }

    protected function getExpectedModelIds(): array
    {
        return ['jev-1.13.0', 'jev-latest', 'jev-preview'];
    }

    protected function getProviderEnum(): AIProvider
    {
        return AIProvider::TYPESAFE;
    }

    public function testGetModelReturnsConfigurableModel(): void
    {
        $this->assertInstanceOf(ConfigurableModel::class, $this->provider->getModel(DecisionModel::JEV_1_13_0->value));
    }

    public function testDefaultModelIsPinnedJev(): void
    {
        $this->assertSame(DecisionModel::JEV_1_13_0->value, $this->provider->getDefaultModel());
    }

    public function testCustomDefaultModel(): void
    {
        $provider = new TypeSafeProvider(DecisionModel::JEV_PREVIEW->value);

        $this->assertSame(DecisionModel::JEV_PREVIEW->value, $provider->getDefaultModel());
    }

    public function testRateLimitsFromEnum(): void
    {
        $this->assertSame(
            ['requests_per_minute' => 1080, 'tokens_per_minute' => 13500000],
            AIProvider::TYPESAFE->getDefaultRateLimits()
        );
    }
}
