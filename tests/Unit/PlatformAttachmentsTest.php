<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit;

use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Enum\Bedrock\ChatModel as BedrockChatModel;
use Lingoda\AiSdk\Exception\UnsupportedCapabilityException;
use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\Prompt\Attachment;
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Provider\BedrockProvider;
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\TextResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PlatformAttachmentsTest extends TestCase
{
    public function testPdfOnModelWithoutDocumentCapabilityIsRejectedBeforeClientCall(): void
    {
        $client = $this->client(new OpenAIProvider());
        $client->expects($this->never())->method('request');

        $conversation = Conversation::fromUser(UserPrompt::create('Summarize'))
            ->withAttachments(Attachment::fromBytes('%PDF-1.4 x', 'application/pdf'))
        ;

        $this->expectException(UnsupportedCapabilityException::class);
        $this->expectExceptionMessage('document');

        // gpt-4-turbo reads images but not PDFs
        (new Platform([$client]))->ask($conversation, 'gpt-4-turbo');
    }

    public function testImageOnModelWithoutVisionIsRejectedBeforeClientCall(): void
    {
        $client = $this->client(new OpenAIProvider());
        $client->expects($this->never())->method('request');

        $conversation = Conversation::fromUser(UserPrompt::create('Describe'))
            ->withAttachments(Attachment::fromBytes('img', 'image/png'))
        ;

        $this->expectException(UnsupportedCapabilityException::class);
        $this->expectExceptionMessage('vision');

        (new Platform([$client]))->ask($conversation, 'gpt-4.1-nano');
    }

    public function testImageOnVisionModelIsPassedToClient(): void
    {
        $image = Attachment::fromBytes('img', 'image/png');
        $captured = null;

        $client = $this->client(new OpenAIProvider());
        $client->expects($this->once())
            ->method('request')
            ->willReturnCallback(function ($model, $payload) use (&$captured) {
                $captured = $payload;

                return new TextResult('ok');
            })
        ;

        $conversation = Conversation::withSystem(UserPrompt::create('Describe'), SystemPrompt::create('Be brief'))
            ->withAttachments($image)
        ;

        (new Platform([$client]))->ask($conversation, 'gpt-4o-mini');

        $this->assertIsArray($captured);
        $last = $captured[array_key_last($captured)];
        $this->assertSame('user', $last['role']);
        $this->assertSame([$image], $last['attachments']);
    }

    public function testPdfOnBedrockModelIsPassedToClient(): void
    {
        $pdf = Attachment::fromBytes('%PDF-1.4 x', 'application/pdf');
        $captured = null;

        $client = $this->client(new BedrockProvider());
        $client->expects($this->once())
            ->method('request')
            ->willReturnCallback(function ($model, $payload) use (&$captured) {
                $captured = $payload;

                return new TextResult('ok');
            })
        ;

        // The email makes the sanitizer rebuild the conversation, attachments must survive it
        $conversation = Conversation::fromUser(UserPrompt::create('Mail john.doe@example.com the summary'))
            ->withAttachments($pdf)
        ;

        (new Platform([$client]))->ask($conversation, BedrockChatModel::NOVA_2_LITE->value);

        $this->assertIsArray($captured);
        $this->assertSame([$pdf], $captured[0]['attachments']);
        $this->assertStringNotContainsString('john.doe@example.com', $captured[0]['content']);
    }

    public function testTextOnlyRequestSendsLegacyPayload(): void
    {
        $conversation = Conversation::withSystem(UserPrompt::create('Hello'), SystemPrompt::create('Be brief'));

        $client = $this->client(new OpenAIProvider());
        $client->expects($this->once())
            ->method('request')
            ->with($this->anything(), $conversation->toArray())
            ->willReturn(new TextResult('ok'))
        ;

        (new Platform([$client]))->ask($conversation, 'gpt-4o-mini');
    }

    private function client(ProviderInterface $provider): ClientInterface&MockObject
    {
        $client = $this->createMock(ClientInterface::class);
        $client->method('supports')->willReturn(true);
        $client->method('getProvider')->willReturn($provider);

        return $client;
    }
}
