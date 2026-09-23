<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Enum\Bedrock;

use Lingoda\AiSdk\Enum\Bedrock\ApiFormat;
use Lingoda\AiSdk\Enum\Bedrock\ChatModel;
use Lingoda\AiSdk\Enum\Capability;
use PHPUnit\Framework\TestCase;

final class ChatModelTest extends TestCase
{
    public function testModelIds(): void
    {
        $this->assertSame('amazon.nova-2-lite-v1:0', ChatModel::NOVA_2_LITE->getId());
        $this->assertSame('anthropic.claude-haiku-4-5-20251001-v1:0', ChatModel::CLAUDE_HAIKU_45->getId());
        $this->assertCount(2, ChatModel::cases());
    }

    public function testCatalogName(): void
    {
        $this->assertSame('nova-2-lite', ChatModel::NOVA_2_LITE->catalogName());
        $this->assertSame('claude-haiku-4-5-20251001', ChatModel::CLAUDE_HAIKU_45->catalogName());
    }

    public function testIsClaude(): void
    {
        $this->assertSame(ApiFormat::CONVERSE, ChatModel::NOVA_2_LITE->apiFormat());
        $this->assertSame(ApiFormat::ANTHROPIC_MESSAGES, ChatModel::CLAUDE_HAIKU_45->apiFormat());
        $this->assertFalse(ChatModel::NOVA_2_LITE->supportsResponseFormat());
        $this->assertTrue(ChatModel::CLAUDE_HAIKU_45->supportsResponseFormat());
        $this->assertTrue(ChatModel::NOVA_2_LITE->requiresLeadingUserTurn());
        $this->assertFalse(ChatModel::CLAUDE_HAIKU_45->requiresLeadingUserTurn());
    }

    public function testCapabilities(): void
    {
        foreach (ChatModel::cases() as $model) {
            $this->assertSame([Capability::TEXT, Capability::VISION, Capability::DOCUMENT], $model->getCapabilities());
            $this->assertTrue($model->hasCapability(Capability::DOCUMENT));
            $this->assertTrue($model->hasCapability(Capability::VISION));
            $this->assertFalse($model->hasCapability(Capability::TOOLS));
        }
    }

    public function testOptionsAndDefaults(): void
    {
        foreach (ChatModel::cases() as $model) {
            $this->assertSame(['max_tokens' => 4096], $model->getOptions());
            $this->assertSame(ChatModel::NOVA_2_LITE->value, $model->getDefaultModel());
            $this->assertNotSame('', $model->getDisplayName());
        }

        $this->assertSame(1000000, ChatModel::NOVA_2_LITE->getMaxTokens());
        $this->assertSame(200000, ChatModel::CLAUDE_HAIKU_45->getMaxTokens());
    }
}
