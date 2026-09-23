<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Client\Bedrock\Contract;

use Lingoda\AiSdk\Client\Bedrock\Contract\NovaUserMessageNormalizer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\UserMessageNormalizer as UpstreamNovaUserMessageNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;

/**
 * Drift guards for our copy of the final upstream Nova UserMessageNormalizer (symfony/ai-bedrock-platform 0.13.0).
 */
#[Group('bedrock')]
final class NovaUserMessageNormalizerTest extends TestCase
{
    public function testTextAndImageOutputMatchesUpstream(): void
    {
        $message = Message::ofUser('Describe', new Image('JPEG-BYTES', 'image/jpg'), new Image('PNG-BYTES', 'image/png'));
        $context = [Contract::CONTEXT_MODEL => new Nova('nova-2-lite')];

        self::assertSame(
            (new UpstreamNovaUserMessageNormalizer())->normalize($message, null, $context),
            (new NovaUserMessageNormalizer())->normalize($message, null, $context),
            'Upstream changed the Nova user message shape: update NovaUserMessageNormalizer to match.'
        );
    }

    /**
     * When this fails, upstream supports documents for Nova: delete NovaUserMessageNormalizer and use the upstream contract.
     */
    public function testUpstreamStillRejectsDocuments(): void
    {
        $this->expectException(RuntimeException::class);

        (new UpstreamNovaUserMessageNormalizer())->normalize(
            Message::ofUser(new Document('%PDF-1.4', 'application/pdf'), 'Extract'),
            null,
            [Contract::CONTEXT_MODEL => new Nova('nova-2-lite')]
        );
    }

    public function testPdfBecomesDocumentBlockWithGeneratedName(): void
    {
        $normalized = (new NovaUserMessageNormalizer())->normalize(
            Message::ofUser(new Document('PDF-ONE', 'application/pdf'), new Document('PDF-TWO', 'application/pdf'), 'Extract')
        );

        self::assertSame([
            'role' => 'user',
            'content' => [
                ['document' => ['format' => 'pdf', 'name' => 'document-1', 'source' => ['bytes' => base64_encode('PDF-ONE')]]],
                ['document' => ['format' => 'pdf', 'name' => 'document-2', 'source' => ['bytes' => base64_encode('PDF-TWO')]]],
                ['text' => 'Extract'],
            ],
        ], $normalized);
    }

    public function testNonPdfDocumentIsRejected(): void
    {
        $this->expectException(RuntimeException::class);

        (new NovaUserMessageNormalizer())->normalize(Message::ofUser(new Document('x', 'text/csv'), 'Extract'));
    }

    public function testSupportsOnlyNova(): void
    {
        $normalizer = new NovaUserMessageNormalizer();
        $message = Message::ofUser('Hi');

        self::assertTrue($normalizer->supportsNormalization($message, null, [Contract::CONTEXT_MODEL => new Nova('nova-2-lite')]));
        self::assertFalse($normalizer->supportsNormalization($message, null, [Contract::CONTEXT_MODEL => new Claude('claude-haiku-4-5-20251001')]));
    }
}
