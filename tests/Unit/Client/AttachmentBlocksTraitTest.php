<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Client;

use Lingoda\AiSdk\Client\AttachmentBlocksTrait;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Prompt\Attachment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttachmentBlocksTraitTest extends TestCase
{
    public function testStringPayloadAndMessagesWithoutAttachmentsAreUntouched(): void
    {
        $payload = [['role' => 'system', 'content' => 'sys'], ['role' => 'user', 'content' => 'Hi']];

        self::assertSame('Hi', $this->expand('Hi'));
        self::assertSame($payload, $this->expand($payload));
    }

    public function testDetectsAttachmentsInEveryPayloadShape(): void
    {
        $pdf = Attachment::fromBytes('%PDF-1.4 x', 'application/pdf');

        self::assertFalse($this->has('Hi'));
        self::assertFalse($this->has([['role' => 'user', 'content' => 'Hi']]));
        self::assertTrue($this->has([['role' => 'user', 'content' => 'Hi', 'attachments' => [$pdf]]]));
        self::assertTrue($this->has(['messages' => [['role' => 'user', 'content' => 'Hi', 'attachments' => [$pdf]]]]));
    }

    public function testAttachmentsBecomeBlocksBeforeTheText(): void
    {
        $payload = [['role' => 'user', 'content' => 'Read', 'attachments' => [Attachment::fromBytes('%PDF-1.4 x', 'application/pdf')]]];

        self::assertSame(
            [['role' => 'user', 'content' => [['kind' => 'application/pdf', 'position' => 1], ['type' => 'text', 'text' => 'Read']]]],
            $this->expand($payload)
        );
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function invalidMessages(): iterable
    {
        yield 'attachments not a list' => [[['role' => 'user', 'content' => 'x', 'attachments' => 'nope']]];
        yield 'content already blocks' => [[['role' => 'user', 'content' => [['type' => 'text', 'text' => 'x']], 'attachments' => [Attachment::fromBytes('%PDF-1.4 x', 'application/pdf')]]]];
        yield 'entry not an Attachment' => [[['role' => 'user', 'content' => 'x', 'attachments' => ['nope']]]];
    }

    /**
     * @param array<mixed> $payload
     */
    #[DataProvider('invalidMessages')]
    public function testInvalidAttachmentMessagesAreRejected(array $payload): void
    {
        $this->expectException(ClientException::class);

        $this->expand($payload);
    }

    /**
     * @param array<mixed>|string $payload
     */
    private function has(array|string $payload): bool
    {
        $subject = new class() {
            use AttachmentBlocksTrait;

            /**
             * @param array<mixed>|string $payload
             */
            public function run(array|string $payload): bool
            {
                return $this->hasAttachments($payload);
            }
        };

        return $subject->run($payload);
    }

    /**
     * @param array<mixed>|string $payload
     *
     * @return array<mixed>|string
     */
    private function expand(array|string $payload): array|string
    {
        $subject = new class() {
            use AttachmentBlocksTrait;

            /**
             * @param array<mixed>|string $payload
             *
             * @return array<mixed>|string
             */
            public function run(array|string $payload): array|string
            {
                return $this->expandAttachments(
                    $payload,
                    static fn (Attachment $attachment, int $position): array => ['kind' => $attachment->mimeType, 'position' => $position]
                );
            }
        };

        return $subject->run($payload);
    }
}
