<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Enum\Bedrock;

use Lingoda\AiSdk\Enum\Bedrock\ApiFormat;
use Lingoda\AiSdk\Enum\Bedrock\ChatModel;
use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\Prompt\Attachment;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testTemperatureAndResponseFormatSupport(): void
    {
        $noTemperature = [ChatModel::CLAUDE_OPUS_47, ChatModel::CLAUDE_OPUS_48, ChatModel::CLAUDE_SONNET_5, ChatModel::CLAUDE_OPUS_5, ChatModel::CLAUDE_OPUS_55];
        $responseFormat = [ChatModel::CLAUDE_HAIKU_45, ChatModel::CLAUDE_SONNET_45, ChatModel::CLAUDE_OPUS_45, ChatModel::CLAUDE_SONNET_46, ChatModel::CLAUDE_OPUS_46];

        foreach (ChatModel::cases() as $model) {
            $this->assertSame(!in_array($model, $noTemperature, true), $model->supportsTemperature(), $model->value);
            $this->assertSame(in_array($model, $responseFormat, true), $model->supportsResponseFormat(), $model->value);
        }
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

    /**
     * @return iterable<string, array{ChatModel, string, bool}>
     */
    public static function documentMatrix(): iterable
    {
        yield 'nova pdf' => [ChatModel::NOVA_2_LITE, Attachment::PDF, true];
        yield 'nova docx' => [ChatModel::NOVA_2_LITE, Attachment::DOCX, true];
        yield 'nova csv' => [ChatModel::NOVA_2_LITE, 'text/csv', false];
        yield 'nova png' => [ChatModel::NOVA_2_LITE, 'image/png', false];
        yield 'haiku pdf' => [ChatModel::CLAUDE_HAIKU_45, Attachment::PDF, true];
        yield 'haiku docx' => [ChatModel::CLAUDE_HAIKU_45, Attachment::DOCX, false];
        yield 'haiku csv' => [ChatModel::CLAUDE_HAIKU_45, 'text/csv', false];
        yield 'haiku png' => [ChatModel::CLAUDE_HAIKU_45, 'image/png', false];
    }

    #[DataProvider('documentMatrix')]
    public function testAcceptsDocument(ChatModel $model, string $mimeType, bool $expected): void
    {
        $this->assertSame($expected, $model->acceptsDocument($mimeType));
    }
}
