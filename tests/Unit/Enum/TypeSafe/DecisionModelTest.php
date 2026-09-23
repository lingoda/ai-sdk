<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Enum\TypeSafe;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\Enum\TypeSafe\DecisionModel;
use PHPUnit\Framework\TestCase;

final class DecisionModelTest extends TestCase
{
    public function testModelIds(): void
    {
        $this->assertSame('jev-1.13.0', DecisionModel::JEV_1_13_0->getId());
        $this->assertSame('jev-latest', DecisionModel::JEV_LATEST->getId());
        $this->assertSame('jev-preview', DecisionModel::JEV_PREVIEW->getId());
        $this->assertCount(3, DecisionModel::cases());
    }

    public function testDisplayNames(): void
    {
        $this->assertSame('Jev 1.13.0', DecisionModel::JEV_1_13_0->getDisplayName());
        $this->assertSame('Jev (latest)', DecisionModel::JEV_LATEST->getDisplayName());
        $this->assertSame('Jev (preview)', DecisionModel::JEV_PREVIEW->getDisplayName());
    }

    public function testCapabilitiesAreTextOnly(): void
    {
        foreach (DecisionModel::cases() as $model) {
            $this->assertSame([Capability::TEXT], $model->getCapabilities());
            $this->assertTrue($model->hasCapability(Capability::TEXT));
            $this->assertFalse($model->hasCapability(Capability::VISION));
            $this->assertFalse($model->hasCapability(Capability::DOCUMENT));
            $this->assertFalse($model->hasCapability(Capability::TOOLS));
        }
    }

    public function testLimitsOptionsAndDefault(): void
    {
        foreach (DecisionModel::cases() as $model) {
            $this->assertSame(64000, $model->getMaxTokens());
            $this->assertSame([], $model->getOptions());
            $this->assertSame(DecisionModel::JEV_1_13_0->value, $model->getDefaultModel());
        }
    }
}
