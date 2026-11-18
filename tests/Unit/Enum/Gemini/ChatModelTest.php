<?php

declare(strict_types = 1);

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
        $this->assertEquals('gemini-3.1-flash-lite', ChatModel::GEMINI_3_1_FLASH_LITE->value);
        $this->assertSame(ChatModel::GEMINI_3_1_FLASH_LITE, ChatModel::from('gemini-3.1-flash-lite'));
        $this->assertSame(ChatModel::GEMINI_3_5_FLASH_LITE, ChatModel::from('gemini-3.5-flash-lite'));
        $this->assertSame(ChatModel::GEMINI_3_8_FLASH, ChatModel::from('gemini-3.8-flash'));
        $this->assertSame(ChatModel::GEMINI_4_HYDROGEN, ChatModel::from('gemini-4-hydrogen'));
        $this->assertSame(ChatModel::GEMINI_4_ARGON, ChatModel::from('gemini-4-argon'));
        $this->assertSame(ChatModel::GEMINI_4_CARBON, ChatModel::from('gemini-4-carbon'));
    }

    public function testGetDefaultModel(): void
    {
        foreach (ChatModel::cases() as $model) {
            $this->assertSame('gemini-2.5-flash', $model->getDefaultModel());
        }
    }

    public function testGetId(): void
    {
        $this->assertEquals('gemini-2.5-pro', ChatModel::GEMINI_2_5_PRO->getId());
        $this->assertEquals('gemini-2.5-flash', ChatModel::GEMINI_2_5_FLASH->getId());
        $this->assertEquals('gemini-3.1-flash-lite', ChatModel::GEMINI_3_1_FLASH_LITE->getId());
        $this->assertEquals('gemini-4-argon', ChatModel::GEMINI_4_ARGON->getId());
    }

    public function testGetMaxTokens(): void
    {
        $this->assertEquals(1000000, ChatModel::GEMINI_2_5_PRO->getMaxTokens());
        $this->assertEquals(1000000, ChatModel::GEMINI_2_5_FLASH->getMaxTokens());
        $this->assertEquals(1000000, ChatModel::GEMINI_3_1_FLASH_LITE->getMaxTokens());

        foreach (self::modelsSinceGemini35() as $model) {
            $this->assertSame(1048576, $model->getMaxTokens(), $model->value);
        }
    }

    public function testGetCapabilities(): void
    {
        $expectedCapabilities = [
            Capability::TEXT,
            Capability::TOOLS,
            Capability::VISION,
            Capability::DOCUMENT,
            Capability::MULTIMODAL,
        ];

        $this->assertEquals($expectedCapabilities, ChatModel::GEMINI_2_5_PRO->getCapabilities());
        $this->assertEquals($expectedCapabilities, ChatModel::GEMINI_2_5_FLASH->getCapabilities());
        $this->assertEquals($expectedCapabilities, ChatModel::GEMINI_3_1_FLASH_LITE->getCapabilities());

        // The models since Gemini 3.5 also think before they answer
        foreach (self::modelsSinceGemini35() as $model) {
            $this->assertEquals([...$expectedCapabilities, Capability::REASONING], $model->getCapabilities(), $model->value);
        }
    }

    public function testHasCapability(): void
    {
        foreach (ChatModel::cases() as $model) {
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

        $expectedFlashLiteOptions = [
            'temperature' => 0.7,
            'maxOutputTokens' => 65536,
            'topP' => 0.95,
            'topK' => 40,
        ];

        $this->assertEquals($expectedFlashLiteOptions, ChatModel::GEMINI_3_1_FLASH_LITE->getOptions());
    }

    public function testModelsSinceGemini35HaveNoSamplingDefaults(): void
    {
        // They ignore temperature, topP and topK, so only the output limit is preset
        foreach (self::modelsSinceGemini35() as $model) {
            $this->assertSame(['maxOutputTokens' => 65536], $model->getOptions(), $model->value);
        }
    }

    public function testGetDisplayName(): void
    {
        $this->assertEquals('Gemini 2.5 Pro', ChatModel::GEMINI_2_5_PRO->getDisplayName());
        $this->assertEquals('Gemini 2.5 Flash', ChatModel::GEMINI_2_5_FLASH->getDisplayName());
        $this->assertEquals('Gemini 3.1 Flash Lite', ChatModel::GEMINI_3_1_FLASH_LITE->getDisplayName());
        $this->assertEquals('Gemini 3.5 Flash Lite', ChatModel::GEMINI_3_5_FLASH_LITE->getDisplayName());
        $this->assertEquals('Gemini 3.8 Flash', ChatModel::GEMINI_3_8_FLASH->getDisplayName());
        $this->assertEquals('Gemini 4 Hydrogen', ChatModel::GEMINI_4_HYDROGEN->getDisplayName());
        $this->assertEquals('Gemini 4 Argon', ChatModel::GEMINI_4_ARGON->getDisplayName());
        $this->assertEquals('Gemini 4 Carbon', ChatModel::GEMINI_4_CARBON->getDisplayName());
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
        $this->assertCount(8, $cases);

        // Oldest first: a new model is appended, so the position of an existing one never moves
        $expectedCases = [
            ChatModel::GEMINI_2_5_PRO,
            ChatModel::GEMINI_2_5_FLASH,
            ChatModel::GEMINI_3_1_FLASH_LITE,
            ChatModel::GEMINI_3_5_FLASH_LITE,
            ChatModel::GEMINI_3_8_FLASH,
            ChatModel::GEMINI_4_HYDROGEN,
            ChatModel::GEMINI_4_ARGON,
            ChatModel::GEMINI_4_CARBON,
        ];

        $this->assertEquals($expectedCases, $cases);
    }

    /**
     * @return list<ChatModel>
     */
    private static function modelsSinceGemini35(): array
    {
        return [
            ChatModel::GEMINI_3_5_FLASH_LITE,
            ChatModel::GEMINI_3_8_FLASH,
            ChatModel::GEMINI_4_HYDROGEN,
            ChatModel::GEMINI_4_ARGON,
            ChatModel::GEMINI_4_CARBON,
        ];
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
