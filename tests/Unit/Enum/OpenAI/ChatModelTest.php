<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Enum\OpenAI;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\Enum\OpenAI\ChatModel;
use PHPUnit\Framework\TestCase;

final class ChatModelTest extends TestCase
{
    public function testModelValues(): void
    {
        $this->assertEquals('gpt-4o', ChatModel::GPT_4O->value);
        $this->assertEquals('gpt-4o-mini', ChatModel::GPT_4O_MINI->value);
        $this->assertEquals('gpt-4.1', ChatModel::GPT_41->value);
    }

    public function testGetId(): void
    {
        $this->assertEquals('gpt-5', ChatModel::GPT_5->getId());
        $this->assertEquals('gpt-4.1', ChatModel::GPT_41->getId());
        $this->assertEquals('gpt-4o', ChatModel::GPT_4O->getId());
        $this->assertEquals('gpt-4o-mini', ChatModel::GPT_4O_MINI->getId());
        $this->assertEquals('gpt-4-turbo', ChatModel::GPT_4_TURBO->getId());
    }

    public function testMaxTokens(): void
    {
        $this->assertEquals(1000000, ChatModel::GPT_41->getMaxTokens());
        $this->assertEquals(128000, ChatModel::GPT_4O->getMaxTokens());
        $this->assertEquals(128000, ChatModel::GPT_4O_MINI->getMaxTokens());
    }

    public function testCapabilities(): void
    {
        $gpt41Capabilities = ChatModel::GPT_41->getCapabilities();
        $this->assertContains(Capability::TEXT, $gpt41Capabilities);
        $this->assertContains(Capability::TOOLS, $gpt41Capabilities);
        $this->assertContains(Capability::VISION, $gpt41Capabilities);
        
        $gpt41MiniCapabilities = ChatModel::GPT_41_MINI->getCapabilities();
        $this->assertContains(Capability::TEXT, $gpt41MiniCapabilities);
        $this->assertContains(Capability::TOOLS, $gpt41MiniCapabilities);
        $this->assertContains(Capability::VISION, $gpt41MiniCapabilities);
        
        $gpt41NanoCapabilities = ChatModel::GPT_41_NANO->getCapabilities();
        $this->assertContains(Capability::TEXT, $gpt41NanoCapabilities);
        $this->assertContains(Capability::TOOLS, $gpt41NanoCapabilities);
        $this->assertNotContains(Capability::VISION, $gpt41NanoCapabilities);
    }

    public function testHasCapability(): void
    {
        $this->assertTrue(ChatModel::GPT_41->hasCapability(Capability::VISION));
        $this->assertTrue(ChatModel::GPT_4O->hasCapability(Capability::VISION));
        $this->assertTrue(ChatModel::GPT_41_MINI->hasCapability(Capability::VISION));
        $this->assertFalse(ChatModel::GPT_41_NANO->hasCapability(Capability::VISION));

        $this->assertTrue(ChatModel::GPT_41->hasCapability(Capability::TOOLS));
        $this->assertTrue(ChatModel::GPT_41_MINI->hasCapability(Capability::TOOLS));
        $this->assertTrue(ChatModel::GPT_41_NANO->hasCapability(Capability::TOOLS));
    }

    public function testOptions(): void
    {
        $options = ChatModel::GPT_41->getOptions();
        $this->assertArrayHasKey('temperature', $options);
        $this->assertArrayHasKey('max_tokens', $options);
        $this->assertArrayHasKey('top_p', $options);
        
        $this->assertEquals(1.0, $options['temperature']);
        $this->assertEquals(32768, $options['max_tokens']);
        $this->assertEquals(1.0, $options['top_p']);
    }

    public function testDisplayNames(): void
    {
        $this->assertEquals('GPT-4.1', ChatModel::GPT_41->getDisplayName());
        $this->assertEquals('GPT-4.1 Mini', ChatModel::GPT_41_MINI->getDisplayName());
        $this->assertEquals('GPT-4o Mini', ChatModel::GPT_4O_MINI->getDisplayName());
        $this->assertEquals('GPT-4o Mini (2024-07-18)', ChatModel::GPT_4O_MINI_20240718->getDisplayName());
        $this->assertEquals('GPT-4 Turbo', ChatModel::GPT_4_TURBO->getDisplayName());
    }

    public function testImplementsModelConfigurationInterface(): void
    {
        $model = ChatModel::GPT_4O;
        
        $this->assertIsString($model->getId());
        $this->assertIsInt($model->getMaxTokens());
        $this->assertIsArray($model->getCapabilities());
        $this->assertIsArray($model->getOptions());
        $this->assertIsString($model->getDisplayName());
        $this->assertIsBool($model->hasCapability(Capability::TEXT));
    }

    public function testDifferentModelSeries(): void
    {
        // Test GPT-5 series
        $gpt5 = ChatModel::GPT_5;
        $this->assertEquals('gpt-5', $gpt5->getId());
        $this->assertEquals(2000000, $gpt5->getMaxTokens());
        $this->assertTrue($gpt5->hasCapability(Capability::REASONING));
        
        // Test GPT-4.1 nano (different capabilities)
        $gpt41nano = ChatModel::GPT_41_NANO;
        $this->assertEquals('gpt-4.1-nano', $gpt41nano->getId());
        $this->assertEquals(1000000, $gpt41nano->getMaxTokens());
        $this->assertFalse($gpt41nano->hasCapability(Capability::VISION));
        $this->assertTrue($gpt41nano->hasCapability(Capability::TOOLS));
        
        // Test GPT-4 Turbo (legacy model)
        $gpt4turbo = ChatModel::GPT_4_TURBO;
        $this->assertEquals('gpt-4-turbo', $gpt4turbo->getId());
        $this->assertEquals(128000, $gpt4turbo->getMaxTokens());
        $this->assertTrue($gpt4turbo->hasCapability(Capability::VISION));
    }
}