<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Model;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\Model\ConfigurableModel;
use Lingoda\AiSdk\ModelConfigurationInterface;
use Lingoda\AiSdk\ProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ConfigurableModelTest extends TestCase
{
    private ModelConfigurationInterface&MockObject $configuration;
    private ProviderInterface&MockObject $provider;
    private ConfigurableModel $model;

    protected function setUp(): void
    {
        $this->configuration = $this->createMock(ModelConfigurationInterface::class);
        $this->provider = $this->createMock(ProviderInterface::class);
        $this->model = new ConfigurableModel($this->configuration, $this->provider);
    }

    public function testGetId(): void
    {
        $expectedId = 'test-model-id';
        $this->configuration
            ->expects($this->once())
            ->method('getId')
            ->willReturn($expectedId);

        $this->assertEquals($expectedId, $this->model->getId());
    }

    public function testGetMaxTokens(): void
    {
        $expectedMaxTokens = 4096;
        $this->configuration
            ->expects($this->once())
            ->method('getMaxTokens')
            ->willReturn($expectedMaxTokens);

        $this->assertEquals($expectedMaxTokens, $this->model->getMaxTokens());
    }

    public function testGetProvider(): void
    {
        $this->assertSame($this->provider, $this->model->getProvider());
    }

    public function testHasCapabilityTrue(): void
    {
        $capability = Capability::TEXT;
        $this->configuration
            ->expects($this->once())
            ->method('hasCapability')
            ->with($capability)
            ->willReturn(true);

        $this->assertTrue($this->model->hasCapability($capability));
    }

    public function testHasCapabilityFalse(): void
    {
        $capability = Capability::VISION;
        $this->configuration
            ->expects($this->once())
            ->method('hasCapability')
            ->with($capability)
            ->willReturn(false);

        $this->assertFalse($this->model->hasCapability($capability));
    }

    public function testGetCapabilities(): void
    {
        $expectedCapabilities = [Capability::TEXT, Capability::TOOLS];
        $this->configuration
            ->expects($this->once())
            ->method('getCapabilities')
            ->willReturn($expectedCapabilities);

        $this->assertEquals($expectedCapabilities, $this->model->getCapabilities());
    }

    public function testGetOptions(): void
    {
        $expectedOptions = ['temperature' => 0.7, 'max_tokens' => 1000];
        $this->configuration
            ->expects($this->once())
            ->method('getOptions')
            ->willReturn($expectedOptions);

        $this->assertEquals($expectedOptions, $this->model->getOptions());
    }

    public function testGetDisplayName(): void
    {
        $expectedDisplayName = 'Test Model Display Name';
        $this->configuration
            ->expects($this->once())
            ->method('getDisplayName')
            ->willReturn($expectedDisplayName);

        $this->assertEquals($expectedDisplayName, $this->model->getDisplayName());
    }

    public function testGetConfiguration(): void
    {
        $this->assertSame($this->configuration, $this->model->getConfiguration());
    }

    public function testAllCapabilities(): void
    {
        $allCapabilities = [
            Capability::TEXT,
            Capability::TOOLS,
            Capability::VISION,
            Capability::MULTIMODAL,
            Capability::REASONING,
            Capability::AUDIO,
            Capability::STREAMING
        ];

        $this->configuration
            ->expects($this->exactly(count($allCapabilities)))
            ->method('hasCapability')
            ->willReturnCallback(function (Capability $capability) use ($allCapabilities) {
                return in_array($capability, $allCapabilities, true);
            });

        foreach ($allCapabilities as $capability) {
            $this->assertTrue($this->model->hasCapability($capability));
        }
    }

    public function testEmptyCapabilities(): void
    {
        $this->configuration
            ->expects($this->once())
            ->method('getCapabilities')
            ->willReturn([]);

        $this->assertEmpty($this->model->getCapabilities());
    }

    public function testEmptyOptions(): void
    {
        $this->configuration
            ->expects($this->once())
            ->method('getOptions')
            ->willReturn([]);

        $this->assertEmpty($this->model->getOptions());
    }
}