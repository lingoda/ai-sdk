<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Enum\Bedrock;

use Lingoda\AiSdk\Enum\Bedrock\ChatModel;
use Lingoda\AiSdk\Enum\Capability;
use PHPUnit\Framework\TestCase;

final class ChatModelTest extends TestCase
{
    public function testModelIds(): void
    {
        $this->assertSame([
            'amazon.nova-micro-v1:0',
            'amazon.nova-lite-v1:0',
            'amazon.nova-pro-v1:0',
            'amazon.nova-2-lite-v1:0',
            'anthropic.claude-haiku-4-5-20251001-v1:0',
            'anthropic.claude-sonnet-4-5-20250929-v1:0',
            'anthropic.claude-opus-4-5-20251101-v1:0',
            'anthropic.claude-sonnet-4-6',
            'anthropic.claude-opus-4-6-v1',
            'anthropic.claude-opus-4-7',
            'anthropic.claude-opus-4-8',
            'anthropic.claude-sonnet-5',
            'anthropic.claude-opus-5',
            'anthropic.claude-opus-5-5',
        ], array_map(static fn (ChatModel $model): string => $model->getId(), ChatModel::cases()));
    }

    public function testCapabilities(): void
    {
        $this->assertSame([Capability::TEXT], ChatModel::NOVA_MICRO->getCapabilities());

        foreach (ChatModel::cases() as $model) {
            $this->assertFalse($model->hasCapability(Capability::TOOLS));
            if ($model !== ChatModel::NOVA_MICRO) {
                $this->assertSame([Capability::TEXT, Capability::VISION, Capability::DOCUMENT], $model->getCapabilities(), $model->value);
            }
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
