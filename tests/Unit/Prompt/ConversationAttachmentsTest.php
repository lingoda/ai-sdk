<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Prompt;

use Lingoda\AiSdk\Prompt\AssistantPrompt;
use Lingoda\AiSdk\Prompt\Attachment;
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Security\DataSanitizer;
use PHPUnit\Framework\TestCase;

final class ConversationAttachmentsTest extends TestCase
{
    private const string MARKER = 'SECRET_BYTES_MARKER_4711';

    public function testConversationWithoutAttachmentsMatchesLegacyOutput(): void
    {
        $conversation = Conversation::conversation(
            UserPrompt::create('What about now?'),
            SystemPrompt::create('You are helpful'),
            AssistantPrompt::create('I helped before')
        );

        $expected = [
            ['role' => 'system', 'content' => 'You are helpful'],
            ['role' => 'assistant', 'content' => 'I helped before'],
            ['role' => 'user', 'content' => 'What about now?'],
        ];

        $this->assertSame($expected, $conversation->toArray());
        $this->assertSame($conversation->toArray(), $conversation->toRequestArray());
        $this->assertSame(
            md5(sprintf('system:%s|assistant:%s|user:%s', 'You are helpful', 'I helped before', 'What about now?')),
            $conversation->hash()
        );
    }

    public function testUserOnlyConversationWithoutAttachmentsMatchesLegacyOutput(): void
    {
        $conversation = Conversation::fromUser(UserPrompt::create('Hello'));

        $this->assertSame([['role' => 'user', 'content' => 'Hello']], $conversation->toArray());
        $this->assertArrayNotHasKey('attachments', $conversation->toArray()[0]);
        $this->assertSame($conversation->toArray(), $conversation->toRequestArray());
        $this->assertSame(md5('system:|assistant:|user:Hello'), $conversation->hash());
        $this->assertSame(md5('user:Hello'), $conversation->getUserPrompt()->hash());
    }

    public function testToArrayCarriesAttachmentMetadataOnly(): void
    {
        $pdf = $this->pdf();
        $image = Attachment::fromBytes('img ' . self::MARKER, 'image/png');
        $conversation = Conversation::withSystem(UserPrompt::create('Summarize'), SystemPrompt::create('Be brief'))
            ->withAttachments($pdf, $image)
        ;

        $messages = $conversation->toArray();
        $user = $messages[array_key_last($messages)];

        $this->assertSame('user', $user['role']);
        $this->assertSame('Summarize', $user['content']);
        $this->assertSame([
            ['mime' => 'application/pdf', 'size' => $pdf->size()],
            ['mime' => 'image/png', 'size' => $image->size()],
        ], $user['attachments'] ?? null);

        $json = json_encode($messages, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(self::MARKER, $json);
    }

    public function testToRequestArrayCarriesAttachmentObjects(): void
    {
        $pdf = $this->pdf();
        $conversation = Conversation::conversation(
            UserPrompt::create('Summarize'),
            SystemPrompt::create('Be brief'),
            AssistantPrompt::create('Ok')
        )->withAttachments($pdf);

        $messages = $conversation->toRequestArray();

        $this->assertCount(3, $messages);
        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('assistant', $messages[1]['role']);
        $this->assertSame('user', $messages[2]['role']);
        $this->assertSame([$pdf], $messages[2]['attachments'] ?? null);
    }

    public function testSanitizeKeepsAttachments(): void
    {
        $pdf = $this->pdf();
        $conversation = Conversation::fromUser(UserPrompt::create('Contact me at john.doe@example.com please'))
            ->withAttachments($pdf)
        ;

        $sanitized = $conversation->sanitize(DataSanitizer::createDefault());

        $this->assertNotSame($conversation, $sanitized);
        $this->assertTrue($sanitized->isSanitized());
        $this->assertStringNotContainsString('john.doe@example.com', $sanitized->getUserContent());
        $this->assertSame([$pdf], $sanitized->getUserPrompt()->getAttachments());
    }

    public function testWithSystemAndAssistantPromptKeepAttachments(): void
    {
        $pdf = $this->pdf();
        $conversation = Conversation::fromUser(UserPrompt::create('Hi'))
            ->withAttachments($pdf)
            ->withSystemPrompt(SystemPrompt::create('System'))
            ->withAssistantPrompt(AssistantPrompt::create('Assistant'))
        ;

        $this->assertSame([$pdf], $conversation->getUserPrompt()->getAttachments());
        $this->assertSame('System', $conversation->getSystemContent());
        $this->assertSame('Assistant', $conversation->getAssistantContent());
    }

    public function testUserPromptWithParametersKeepsAttachments(): void
    {
        $pdf = $this->pdf();
        $prompt = UserPrompt::create('Hi {{name}}')->withAttachments($pdf);

        $compiled = $prompt->withParameters(['name' => 'x']);

        $this->assertSame('Hi x', $compiled->getContent());
        $this->assertSame([$pdf], $compiled->getAttachments());
    }

    public function testWithAttachmentsReplacesExistingAttachments(): void
    {
        $first = $this->pdf();
        $second = Attachment::fromBytes('second', 'image/png');

        $conversation = Conversation::fromUser(UserPrompt::create('Hi'))
            ->withAttachments($first)
            ->withAttachments($second)
        ;

        $this->assertSame([$second], $conversation->getUserPrompt()->getAttachments());

        $cleared = $conversation->withAttachments();
        $this->assertFalse($cleared->getUserPrompt()->hasAttachments());
        $this->assertSame([['role' => 'user', 'content' => 'Hi']], $cleared->toArray());
    }

    public function testDifferentAttachmentsAreNotEqual(): void
    {
        $a = Conversation::fromUser(UserPrompt::create('Same'))->withAttachments(Attachment::fromBytes('one', 'application/pdf'));
        $b = Conversation::fromUser(UserPrompt::create('Same'))->withAttachments(Attachment::fromBytes('two', 'application/pdf'));
        $plain = Conversation::fromUser(UserPrompt::create('Same'));

        $this->assertFalse($a->equals($b));
        $this->assertNotSame($a->hash(), $b->hash());
        $this->assertFalse($a->getUserPrompt()->equals($b->getUserPrompt()));
        $this->assertNotSame($a->getUserPrompt()->hash(), $b->getUserPrompt()->hash());

        $this->assertFalse($a->equals($plain));
        $this->assertNotSame($a->hash(), $plain->hash());
    }

    public function testSameAttachmentsAreEqual(): void
    {
        $a = Conversation::fromUser(UserPrompt::create('Same'))->withAttachments(Attachment::fromBytes('one', 'application/pdf'));
        $b = Conversation::fromUser(UserPrompt::create('Same'))->withAttachments(Attachment::fromBytes('one', 'application/pdf'));

        $this->assertTrue($a->equals($b));
        $this->assertSame($a->hash(), $b->hash());
        $this->assertSame($a->getUserPrompt()->hash(), $b->getUserPrompt()->hash());
    }

    private function pdf(): Attachment
    {
        return Attachment::fromBytes('%PDF-1.4 ' . self::MARKER, 'application/pdf');
    }

    public function testUserPromptRejectsNonAttachmentItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UserPrompt('Hello', attachments: ['not an attachment']); // @phpstan-ignore argument.type
    }

    public function testSameBytesWithADifferentMimeTypeAreADifferentPrompt(): void
    {
        $asImage = Conversation::fromUser(UserPrompt::create('Read'))->withAttachments(Attachment::fromBytes('SAME-BYTES', 'image/png'));
        $asPdf = Conversation::fromUser(UserPrompt::create('Read'))->withAttachments(Attachment::fromBytes('SAME-BYTES', 'application/pdf'));

        $this->assertFalse($asImage->equals($asPdf));
        $this->assertNotSame($asImage->hash(), $asPdf->hash());
        $this->assertFalse($asImage->getUserPrompt()->equals($asPdf->getUserPrompt()));
        $this->assertNotSame($asImage->getUserPrompt()->hash(), $asPdf->getUserPrompt()->hash());
    }

    public function testTextCannotReproduceAnAttachmentConversationHash(): void
    {
        $attachment = Attachment::fromBytes('%PDF-1.4 x', 'application/pdf');
        $withAttachment = Conversation::fromUser(UserPrompt::create('Read'))->withAttachments($attachment);
        $crafted = Conversation::fromUser(UserPrompt::create('Read|attachments:' . $attachment->mimeType . ':' . $attachment->sha256));

        $this->assertNotSame($withAttachment->hash(), $crafted->hash());
    }
}
