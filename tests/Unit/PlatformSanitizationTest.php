<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit;

use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Tests\Unit\Security\TestLogger;
use PHPUnit\Framework\TestCase;

final class PlatformSanitizationTest extends TestCase
{
    private ClientInterface $client;
    private ModelInterface $model;
    private TestLogger $logger;

    protected function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->model = $this->createMock(ModelInterface::class);
        $provider = $this->createMock(ProviderInterface::class);
        $this->logger = new TestLogger();
        
        $provider->method('getId')->willReturn('test-provider');
        $this->model->method('getProvider')->willReturn($provider);
        $this->model->method('getId')->willReturn('test-model');
        $this->client->method('supports')->willReturn(true);
    }

    public function testSanitizesUserPromptByDefault(): void
    {
        $input = UserPrompt::create('Please email me at john@example.com or call 555-123-4567');
        
        $this->client->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->callback(function ($payload) {
                    return is_array($payload) &&
                           isset($payload[0]['content']) &&
                           $payload[0]['content'] === 'Please email me at [REDACTED_EMAIL] or call [REDACTED_PHONE]';
                }),
                []
            )
            ->willReturn(new TextResult('Response'));
        
        $platform = new Platform([$this->client], true, null, $this->logger);
        $platform->ask($input, $this->model->getId());

        $this->assertTrue($this->logger->hasInfo('Sensitive data sanitized in user prompt before API request'));
    }


    public function testDoesNotSanitizeWhenDisabled(): void
    {
        $input = UserPrompt::create('Please email me at john@example.com');
        
        $this->client->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->callback(function ($payload) {
                    return is_array($payload) &&
                           isset($payload[0]['content']) &&
                           $payload[0]['content'] === 'Please email me at john@example.com'; // Original content
                }),
                []
            )
            ->willReturn(new TextResult('Response'));
        
        $platform = new Platform([$this->client], false); // Sanitization disabled
        $platform->ask($input, $this->model->getId());

        $this->assertFalse($this->logger->hasInfo('Sensitive data sanitized in user prompt before API request'));
    }


    public function testDoesNotLogWhenContentUnchanged(): void
    {
        $input = UserPrompt::create('This is completely safe text with no sensitive data');
        
        $this->client->expects($this->once())
            ->method('request')
            ->willReturn(new TextResult('Response'));
        
        $platform = new Platform([$this->client], true, null, $this->logger);
        $platform->ask($input, $this->model->getId());
        
        // Should not log if content wasn't changed
        $this->assertFalse($this->logger->hasInfo('Sensitive data sanitized in user prompt before API request'));
    }

    public function testSanitizesConversationObject(): void
    {
        $input = Conversation::fromUser(UserPrompt::create('Email: admin@company.com'));
        
        $this->client->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->callback(function ($payload) {
                    return is_array($payload) &&
                           isset($payload[0]['content']) &&
                           $payload[0]['content'] === 'Email: [REDACTED_EMAIL]';
                }),
                []
            )
            ->willReturn(new TextResult('Response'));
        
        $platform = new Platform([$this->client], true, null, $this->logger);
        $platform->ask($input, $this->model->getId());

        $this->assertTrue($this->logger->hasInfo('Sensitive data sanitized in user prompt before API request'));
    }
    
    public function testConversationWithSystemMessage(): void
    {
        $input = Conversation::withSystem(
            UserPrompt::create('My SSN is 123-45-6789'),
            SystemPrompt::create('You are helpful')
        );
        
        $this->client->expects($this->once())
            ->method('request')
            ->with(
                $this->anything(),
                $this->callback(function ($payload) {
                    return is_array($payload) &&
                           count($payload) === 2 &&
                           $payload[0]['content'] === 'You are helpful' &&
                           $payload[1]['content'] === 'My SSN is [REDACTED_SSN]';
                }),
                []
            )
            ->willReturn(new TextResult('Response'));
        
        $platform = new Platform([$this->client], true, null, $this->logger);
        $platform->ask($input, $this->model->getId());

        $this->assertTrue($this->logger->hasInfo('Sensitive data sanitized in user prompt before API request'));
    }
}