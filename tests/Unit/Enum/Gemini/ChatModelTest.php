<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Enum\Gemini;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\Enum\Gemini\ChatModel;
use PHPUnit\Framework\TestCase;

final class ChatModelTest extends TestCase
{
    public function testModelValues(): void
    {
        $this->assertEquals('gemini-2.5-pro', ChatModel::GEMINI_2_5_PRO->value);
        $this->assertEquals('gemini-2.5-flash', ChatModel::GEMINI_2_5_FLASH->value);
    }

    public function testGetId(): void
    {
        $this->assertEquals('gemini-2.5-pro', ChatModel::GEMINI_2_5_PRO->getId());
        $this->assertEquals('gemini-2.5-flash', ChatModel::GEMINI_2_5_FLASH->getId());
    }

    public function testGetMaxTokens(): void
    {
        $this->assertEquals(1000000, ChatModel::GEMINI_2_5_PRO->getMaxTokens());
        $this->assertEquals(1000000, ChatModel::GEMINI_2_5_FLASH->getMaxTokens());
    }

    public function testGetCapabilities(): void
    {
        $expectedCapabilities = [
            Capability::TEXT,
            Capability::TOOLS,
            Capability::VISION,
            Capability::MULTIMODAL,
        ];

        $this->assertEquals($expectedCapabilities, ChatModel::GEMINI_2_5_PRO->getCapabilities());
        $this->assertEquals($expectedCapabilities, ChatModel::GEMINI_2_5_FLASH->getCapabilities());
    }

    public function testHasCapability(): void
    {
        $models = [
            ChatModel::GEMINI_2_5_PRO,
            ChatModel::GEMINI_2_5_FLASH,
        ];

        foreach ($models as $model) {
            $this->assertTrue($model->hasCapability(Capability::TEXT));
            $this->assertTrue($model->hasCapability(Capability::TOOLS));
            $this->assertTrue($model->hasCapability(Capability::VISION));
            $this->assertTrue($model->hasCapability(Capability::MULTIMODAL));
        }
    }

    public function testGetOptions(): void
    {
        $expectedOptions = [
            'temperature' => 0.7,
            'maxOutputTokens' => 1000000,
            'topP' => 0.95,
            'topK' => 40,
        ];

        $this->assertEquals($expectedOptions, ChatModel::GEMINI_2_5_PRO->getOptions());
        $this->assertEquals($expectedOptions, ChatModel::GEMINI_2_5_FLASH->getOptions());
    }

    public function testGetDisplayName(): void
    {
        $this->assertEquals('Gemini 2.5 Pro', ChatModel::GEMINI_2_5_PRO->getDisplayName());
        $this->assertEquals('Gemini 2.5 Flash', ChatModel::GEMINI_2_5_FLASH->getDisplayName());
    }

    public function testImplementsModelConfigurationInterface(): void
    {
        $model = ChatModel::GEMINI_2_5_PRO;
        
        $this->assertIsString($model->getId());
        $this->assertIsInt($model->getMaxTokens());
        $this->assertIsArray($model->getCapabilities());
        $this->assertIsArray($model->getOptions());
        $this->assertIsString($model->getDisplayName());
        $this->assertIsBool($model->hasCapability(Capability::TEXT));
    }

    public function testAllEnumCases(): void
    {
        $cases = ChatModel::cases();
        $this->assertCount(2, $cases);

        $expectedCases = [
            ChatModel::GEMINI_2_5_PRO,
            ChatModel::GEMINI_2_5_FLASH,
        ];

        $this->assertEquals($expectedCases, $cases);
    }

    public function testModelDifferences(): void
    {
        // Both models in this version have the same capabilities and limits
        $pro = ChatModel::GEMINI_2_5_PRO;
        $flash = ChatModel::GEMINI_2_5_FLASH;

        // Test that they have the same capabilities
        $this->assertEquals($pro->getCapabilities(), $flash->getCapabilities());
        $this->assertEquals($pro->getMaxTokens(), $flash->getMaxTokens());
        $this->assertEquals($pro->getOptions(), $flash->getOptions());

        // But different IDs and display names
        $this->assertNotEquals($pro->getId(), $flash->getId());
        $this->assertNotEquals($pro->getDisplayName(), $flash->getDisplayName());
        
        $this->assertEquals('Gemini 2.5 Pro', $pro->getDisplayName());
        $this->assertEquals('Gemini 2.5 Flash', $flash->getDisplayName());
    }
}