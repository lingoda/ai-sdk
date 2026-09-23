<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\Prompt\Attachment;
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Provider\AnthropicProvider;
use Lingoda\AiSdk\Provider\BedrockProvider;
use Lingoda\AiSdk\Provider\GeminiProvider;
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\RateLimit\TokenEstimatorRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins today's behaviour: estimators ignore attachments (and the list payload in general), so a large PDF
 * cannot exhaust a token bucket. When the estimator gets fixed, attachments need a per-model constant here.
 */
final class AttachmentEstimationTest extends TestCase
{
    /**
     * @return iterable<string, array{ProviderInterface, string}>
     */
    public static function providers(): iterable
    {
        yield 'bedrock' => [new BedrockProvider(), 'amazon.nova-2-lite-v1:0'];
        yield 'openai' => [new OpenAIProvider(), 'gpt-4o-mini'];
        yield 'anthropic' => [new AnthropicProvider(), 'claude-3-5-haiku-20241022'];
        yield 'gemini' => [new GeminiProvider(), 'gemini-2.5-flash'];
    }

    #[DataProvider('providers')]
    public function testAttachmentsDoNotChangeTheEstimate(ProviderInterface $provider, string $modelId): void
    {
        $model = $provider->getModel($modelId);
        $conversation = Conversation::fromUser(UserPrompt::create('Extract the fields please.'));
        $registry = TokenEstimatorRegistry::createDefault();

        $withAttachment = $conversation
            ->withAttachments(Attachment::fromBytes(str_repeat('x', 1_000_000), 'application/pdf'))
            ->toRequestArray()
        ;

        self::assertSame(
            $registry->estimate($model, $conversation->toRequestArray()),
            $registry->estimate($model, $withAttachment)
        );
    }
}
