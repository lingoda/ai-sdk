<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Enum\Anthropic;

use Lingoda\AiSdk\Enum\Anthropic\ChatModel;
use Lingoda\AiSdk\Enum\Capability;
use PHPUnit\Framework\TestCase;

final class ChatModelTest extends TestCase
{
    public function testModelValues(): void
    {
        $this->assertEquals('claude-opus-4-1-20250805', ChatModel::CLAUDE_OPUS_41->value);
        $this->assertEquals('claude-opus-4-20250514', ChatModel::CLAUDE_OPUS_4->value);
        $this->assertEquals('claude-sonnet-4-20250514', ChatModel::CLAUDE_SONNET_4->value);
        $this->assertEquals('claude-3-7-sonnet-20250219', ChatModel::CLAUDE_SONNET_37->value);
        $this->assertEquals('claude-3-5-haiku-20241022', ChatModel::CLAUDE_HAIKU_35->value);
        $this->assertEquals('claude-3-haiku-20240307', ChatModel::CLAUDE_HAIKU_3->value);
    }

    public function testGetId(): void
    {
        $this->assertEquals('claude-opus-4-1-20250805', ChatModel::CLAUDE_OPUS_41->getId());
        $this->assertEquals('claude-opus-4-20250514', ChatModel::CLAUDE_OPUS_4->getId());
        $this->assertEquals('claude-sonnet-4-20250514', ChatModel::CLAUDE_SONNET_4->getId());
        $this->assertEquals('claude-3-7-sonnet-20250219', ChatModel::CLAUDE_SONNET_37->getId());
        $this->assertEquals('claude-3-5-haiku-20241022', ChatModel::CLAUDE_HAIKU_35->getId());
        $this->assertEquals('claude-3-haiku-20240307', ChatModel::CLAUDE_HAIKU_3->getId());
    }

    public function testGetMaxTokens(): void
    {
        $this->assertEquals(200000, ChatModel::CLAUDE_OPUS_41->getMaxTokens());
        $this->assertEquals(200000, ChatModel::CLAUDE_OPUS_4->getMaxTokens());
        $this->assertEquals(200000, ChatModel::CLAUDE_SONNET_4->getMaxTokens());
        $this->assertEquals(200000, ChatModel::CLAUDE_SONNET_37->getMaxTokens());
        $this->assertEquals(200000, ChatModel::CLAUDE_HAIKU_35->getMaxTokens());
        $this->assertEquals(200000, ChatModel::CLAUDE_HAIKU_3->getMaxTokens());
    }

    public function testGetMaxOutputTokens(): void
    {
        $this->assertEquals(32000, ChatModel::CLAUDE_OPUS_41->getMaxOutputTokens());
        $this->assertEquals(32000, ChatModel::CLAUDE_OPUS_4->getMaxOutputTokens());
        $this->assertEquals(64000, ChatModel::CLAUDE_SONNET_4->getMaxOutputTokens());
        $this->assertEquals(64000, ChatModel::CLAUDE_SONNET_37->getMaxOutputTokens());
        $this->assertEquals(8192, ChatModel::CLAUDE_HAIKU_35->getMaxOutputTokens());
        $this->assertEquals(4096, ChatModel::CLAUDE_HAIKU_3->getMaxOutputTokens());
    }

    public function testGetCapabilities(): void
    {
        $expectedCapabilities = [
            Capability::TEXT,
            Capability::TOOLS,
            Capability::VISION,
            Capability::MULTIMODAL,
        ];

        $this->assertEquals($expectedCapabilities, ChatModel::CLAUDE_OPUS_41->getCapabilities());
        $this->assertEquals($expectedCapabilities, ChatModel::CLAUDE_OPUS_4->getCapabilities());
        $this->assertEquals($expectedCapabilities, ChatModel::CLAUDE_SONNET_4->getCapabilities());
        $this->assertEquals($expectedCapabilities, ChatModel::CLAUDE_SONNET_37->getCapabilities());
        $this->assertEquals($expectedCapabilities, ChatModel::CLAUDE_HAIKU_35->getCapabilities());
        $this->assertEquals($expectedCapabilities, ChatModel::CLAUDE_HAIKU_3->getCapabilities());
    }

    public function testHasCapability(): void
    {
        $models = [
            ChatModel::CLAUDE_OPUS_41,
            ChatModel::CLAUDE_OPUS_4,
            ChatModel::CLAUDE_SONNET_4,
            ChatModel::CLAUDE_SONNET_37,
            ChatModel::CLAUDE_HAIKU_35,
            ChatModel::CLAUDE_HAIKU_3,
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
        $opusExpectedOptions = [
            'temperature' => 0.7,
            'max_tokens' => 32000,
            'top_p' => 1.0,
        ];
        
        $this->assertEquals($opusExpectedOptions, ChatModel::CLAUDE_OPUS_41->getOptions());
        $this->assertEquals($opusExpectedOptions, ChatModel::CLAUDE_OPUS_4->getOptions());

        $sonnetExpectedOptions = [
            'temperature' => 0.7,
            'max_tokens' => 64000,
            'top_p' => 1.0,
        ];
        
        $this->assertEquals($sonnetExpectedOptions, ChatModel::CLAUDE_SONNET_4->getOptions());
        $this->assertEquals($sonnetExpectedOptions, ChatModel::CLAUDE_SONNET_37->getOptions());

        $haiku35ExpectedOptions = [
            'temperature' => 0.7,
            'max_tokens' => 8192,
            'top_p' => 1.0,
        ];
        
        $this->assertEquals($haiku35ExpectedOptions, ChatModel::CLAUDE_HAIKU_35->getOptions());

        $haiku3ExpectedOptions = [
            'temperature' => 0.7,
            'max_tokens' => 4096,
            'top_p' => 1.0,
        ];
        
        $this->assertEquals($haiku3ExpectedOptions, ChatModel::CLAUDE_HAIKU_3->getOptions());
    }

    public function testGetDisplayName(): void
    {
        $this->assertEquals('Claude Opus 4.1', ChatModel::CLAUDE_OPUS_41->getDisplayName());
        $this->assertEquals('Claude Opus 4', ChatModel::CLAUDE_OPUS_4->getDisplayName());
        $this->assertEquals('Claude Sonnet 4', ChatModel::CLAUDE_SONNET_4->getDisplayName());
        $this->assertEquals('Claude Sonnet 3.7', ChatModel::CLAUDE_SONNET_37->getDisplayName());
        $this->assertEquals('Claude Haiku 3.5', ChatModel::CLAUDE_HAIKU_35->getDisplayName());
        $this->assertEquals('Claude Haiku 3', ChatModel::CLAUDE_HAIKU_3->getDisplayName());
    }

    public function testImplementsModelConfigurationInterface(): void
    {
        $model = ChatModel::CLAUDE_SONNET_4;
        
        $this->assertIsString($model->getId());
        $this->assertIsInt($model->getMaxTokens());
        $this->assertIsInt($model->getMaxOutputTokens());
        $this->assertIsArray($model->getCapabilities());
        $this->assertIsArray($model->getOptions());
        $this->assertIsString($model->getDisplayName());
        $this->assertIsBool($model->hasCapability(Capability::TEXT));
    }

    public function testAllEnumCases(): void
    {
        $cases = ChatModel::cases();
        $this->assertCount(6, $cases);

        $expectedCases = [
            ChatModel::CLAUDE_OPUS_41,
            ChatModel::CLAUDE_OPUS_4,
            ChatModel::CLAUDE_SONNET_4,
            ChatModel::CLAUDE_SONNET_37,
            ChatModel::CLAUDE_HAIKU_35,
            ChatModel::CLAUDE_HAIKU_3,
        ];

        $this->assertEquals($expectedCases, $cases);
    }

    public function testDifferentModelSeries(): void
    {
        // Test Opus series (highest tier)
        $opus41 = ChatModel::CLAUDE_OPUS_41;
        $this->assertEquals(32000, $opus41->getMaxOutputTokens());
        $this->assertEquals('Claude Opus 4.1', $opus41->getDisplayName());
        
        $opus4 = ChatModel::CLAUDE_OPUS_4;
        $this->assertEquals(32000, $opus4->getMaxOutputTokens());
        $this->assertEquals('Claude Opus 4', $opus4->getDisplayName());

        // Test Sonnet series (mid-tier)
        $sonnet4 = ChatModel::CLAUDE_SONNET_4;
        $this->assertEquals(64000, $sonnet4->getMaxOutputTokens());
        $this->assertEquals('Claude Sonnet 4', $sonnet4->getDisplayName());
        
        $sonnet37 = ChatModel::CLAUDE_SONNET_37;
        $this->assertEquals(64000, $sonnet37->getMaxOutputTokens());
        $this->assertEquals('Claude Sonnet 3.7', $sonnet37->getDisplayName());

        // Test Haiku series (efficiency tier)
        $haiku35 = ChatModel::CLAUDE_HAIKU_35;
        $this->assertEquals(8192, $haiku35->getMaxOutputTokens());
        $this->assertEquals('Claude Haiku 3.5', $haiku35->getDisplayName());
        
        $haiku3 = ChatModel::CLAUDE_HAIKU_3;
        $this->assertEquals(4096, $haiku3->getMaxOutputTokens());
        $this->assertEquals('Claude Haiku 3', $haiku3->getDisplayName());
    }
}