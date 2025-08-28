<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit;

use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\ModelNotFoundException;
use Lingoda\AiSdk\Platform;
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\SystemPrompt;
use Lingoda\AiSdk\Prompt\UserPrompt;
use Lingoda\AiSdk\Provider\OpenAIProvider;
use Lingoda\AiSdk\Provider\ProviderCollection;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\TextResult;
use PHPUnit\Framework\TestCase;

final class PlatformTest extends TestCase
{
    public function testPlatformFindsCorrectClient(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');

        // Create a mock client that supports our model
        $client = $this->createMock(ClientInterface::class);
        $client->method('supports')->with($model)->willReturn(true);
        $client->method('getProvider')->willReturn($provider);
        $client->method('request')
            ->willReturn(new TextResult('Test response', ['id' => 'test']))
        ;

        $platform = new Platform([$client]);
        $result = $platform->ask('Hello', $model->getId());

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertEquals('Test response', $result->getContent());
    }

    public function testPlatformThrowsExceptionForUnsupportedModel(): void
    {
        $platform = new Platform([]);
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');

        $this->expectException(ModelNotFoundException::class);

        $platform->ask('Hello', $model->getId());
    }

    public function testPlatformThrowsExceptionWhenNoClientSupportsModel(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');

        // Create a client that doesn't support our model
        $client = $this->createMock(ClientInterface::class);
        $client->method('supports')->with($model)->willReturn(false);

        $platform = new Platform([$client]);

        $this->expectException(ModelNotFoundException::class);

        $platform->ask('Hello', $model->getId());
    }

    public function testGetProvider(): void
    {
        $provider = new OpenAIProvider();
        $client = $this->createMock(ClientInterface::class);
        $client->method('getProvider')->willReturn($provider);

        $platform = new Platform([$client]);

        $this->assertSame($provider, $platform->getProvider('openai'));
    }

    public function testGetProviderThrowsExceptionForUnknownProvider(): void
    {
        $provider = new OpenAIProvider();
        $client = $this->createMock(ClientInterface::class);
        $client->method('getProvider')->willReturn($provider);

        $platform = new Platform([$client]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Provider "unknown" not found. Available providers: openai');

        $platform->getProvider('unknown');
    }

    public function testGetAvailableProviders(): void
    {
        $provider1 = new OpenAIProvider();
        $client1 = $this->createMock(ClientInterface::class);
        $client1->method('getProvider')->willReturn($provider1);

        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('getId')->willReturn('anthropic');
        $client2 = $this->createMock(ClientInterface::class);
        $client2->method('getProvider')->willReturn($provider2);

        $platform = new Platform([$client1, $client2]);

        $providers = $platform->getAvailableProviders();
        $this->assertInstanceOf(ProviderCollection::class, $providers);
        $this->assertContains('openai', $providers->getIds());
        $this->assertContains('anthropic', $providers->getIds());
        $this->assertCount(2, $providers);
    }

    public function testGetAvailableProvidersRemovesDuplicates(): void
    {
        $provider = new OpenAIProvider();
        $client1 = $this->createMock(ClientInterface::class);
        $client1->method('getProvider')->willReturn($provider);
        $client2 = $this->createMock(ClientInterface::class);
        $client2->method('getProvider')->willReturn($provider);

        $platform = new Platform([$client1, $client2]);

        $providers = $platform->getAvailableProviders();
        $this->assertInstanceOf(ProviderCollection::class, $providers);
        $this->assertEquals(['openai'], $providers->getIds());
    }

    public function testHasProvider(): void
    {
        $provider = new OpenAIProvider();
        $client = $this->createMock(ClientInterface::class);
        $client->method('getProvider')->willReturn($provider);

        $platform = new Platform([$client]);

        $this->assertTrue($platform->hasProvider('openai'));
        $this->assertFalse($platform->hasProvider('unknown'));
    }

    public function testAskWithStringInput(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');

        $client = $this->createMock(ClientInterface::class);
        $client->method('supports')->with($model)->willReturn(true);
        $client->method('getProvider')->willReturn($provider);
        $client->method('request')
            ->willReturn(new TextResult('Response', ['id' => 'test']))
        ;

        $platform = new Platform([$client]);
        $result = $platform->ask('Hello world', $model->getId());

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertEquals('Response', $result->getContent());
    }

    public function testAskWithUserPromptInput(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');
        $userPrompt = UserPrompt::create('Hello from prompt');

        $client = $this->createMock(ClientInterface::class);
        $client->method('supports')->with($model)->willReturn(true);
        $client->method('getProvider')->willReturn($provider);
        $client->method('request')
            ->willReturn(new TextResult('Response', ['id' => 'test']))
        ;

        $platform = new Platform([$client]);
        $result = $platform->ask($userPrompt, $model->getId());

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertEquals('Response', $result->getContent());
    }

    public function testAskWithConversationInput(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');
        $conversation = Conversation::fromUser(UserPrompt::create('Hello'));

        $client = $this->createMock(ClientInterface::class);
        $client->method('supports')->with($model)->willReturn(true);
        $client->method('getProvider')->willReturn($provider);
        $client->method('request')
            ->willReturn(new TextResult('Response', ['id' => 'test']))
        ;

        $platform = new Platform([$client]);
        $result = $platform->ask($conversation, $model->getId());

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertEquals('Response', $result->getContent());
    }

    public function testAskWithNullModelAndSingleClient(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');

        $client = $this->createMock(ClientInterface::class);
        $client->method('supports')->with($model)->willReturn(true);
        $client->method('getProvider')->willReturn($provider);
        $client->method('request')
            ->willReturn(new TextResult('Response', ['id' => 'test']))
        ;

        $platform = new Platform([$client]);
        $result = $platform->ask('Hello world', null);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertEquals('Response', $result->getContent());
    }

    public function testAskWithNullModelAndDefaultProvider(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');

        $client1 = $this->createMock(ClientInterface::class);
        $client1->method('supports')->with($model)->willReturn(true);
        $client1->method('getProvider')->willReturn($provider);
        $client1->method('request')
            ->willReturn(new TextResult('Response', ['id' => 'test']))
        ;

        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('getId')->willReturn('anthropic');
        $client2 = $this->createMock(ClientInterface::class);
        $client2->method('getProvider')->willReturn($provider2);

        $platform = new Platform([$client1, $client2], true, null, null, 'openai');
        $result = $platform->ask('Hello world', null);

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertEquals('Response', $result->getContent());
    }

    public function testAskWithNullModelThrowsExceptionForMultipleClientsWithoutDefault(): void
    {
        $provider1 = new OpenAIProvider();
        $client1 = $this->createMock(ClientInterface::class);
        $client1->method('getProvider')->willReturn($provider1);

        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('getId')->willReturn('anthropic');
        $provider2->method('getAvailableModels')->willReturn(['claude-3-sonnet']);
        $client2 = $this->createMock(ClientInterface::class);
        $client2->method('getProvider')->willReturn($provider2);

        $platform = new Platform([$client1, $client2]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiple providers configured. Must specify model parameter or configure the default provider');

        $platform->ask('Hello world', null);
    }

    public function testAskWithNullModelThrowsExceptionForUnknownDefaultProvider(): void
    {
        $provider1 = new OpenAIProvider();
        $client1 = $this->createMock(ClientInterface::class);
        $client1->method('getProvider')->willReturn($provider1);

        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('getId')->willReturn('anthropic');
        $client2 = $this->createMock(ClientInterface::class);
        $client2->method('getProvider')->willReturn($provider2);

        // Using multiple clients with an unknown default provider
        $platform = new Platform([$client1, $client2], true, null, null, 'unknown');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Default provider 'unknown' not found in configured clients");

        $platform->ask('Hello world', null);
    }

    public function testConstructorWithIteratorClients(): void
    {
        $provider = new OpenAIProvider();
        $client = $this->createMock(ClientInterface::class);
        $client->method('getProvider')->willReturn($provider);

        $iterator = new \ArrayIterator([$client]);
        $platform = new Platform($iterator);

        $this->assertTrue($platform->hasProvider('openai'));
    }

    public function testConstructorDisablessanitizationWhenRequested(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');

        $client = $this->createMock(ClientInterface::class);
        $client->method('supports')->with($model)->willReturn(true);
        $client->method('getProvider')->willReturn($provider);
        $client->method('request')
            ->willReturn(new TextResult('Response', ['id' => 'test']))
        ;

        $platform = new Platform([$client], false); // Disable sanitization
        $result = $platform->ask('Hello world', $model->getId());

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertEquals('Response', $result->getContent());
    }

    public function testAskWithSystemPromptInput(): void
    {
        $provider = new OpenAIProvider();
        $model = $provider->getModel('gpt-4o-mini');
        $systemPrompt = SystemPrompt::create('You are a helpful assistant');

        $client = $this->createMock(ClientInterface::class);
        $client->method('supports')->with($model)->willReturn(true);
        $client->method('getProvider')->willReturn($provider);
        $client->method('request')
            ->willReturn(new TextResult('Response', ['id' => 'test']))
        ;

        $platform = new Platform([$client]);
        $result = $platform->ask($systemPrompt, $model->getId());

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertEquals('Response', $result->getContent());
    }


    public function testGetAvailableProvidersWithEmptyClients(): void
    {
        $platform = new Platform([]);

        $providers = $platform->getAvailableProviders();
        $this->assertInstanceOf(ProviderCollection::class, $providers);
        $this->assertTrue($providers->isEmpty());
    }

    public function testConfigureProviderDefaultModel(): void
    {
        $provider = new OpenAIProvider();
        $client = $this->createMock(ClientInterface::class);
        $client->method('getProvider')->willReturn($provider);

        $platform = new Platform([$client]);

        // Verify initial default model
        $this->assertEquals('gpt-4o-mini', $provider->getDefaultModel());

        // Configure new default model
        $platform->configureProviderDefaultModel('openai', 'gpt-4o');

        // Verify the default model was updated
        $this->assertEquals('gpt-4o', $provider->getDefaultModel());
    }

    public function testConfigureProviderDefaultModelForNonExistentProvider(): void
    {
        $provider = new OpenAIProvider();
        $client = $this->createMock(ClientInterface::class);
        $client->method('getProvider')->willReturn($provider);

        $platform = new Platform([$client]);

        // Verify initial default model
        $this->assertEquals('gpt-4o-mini', $provider->getDefaultModel());

        // Configure default model for non-existent provider (should do nothing)
        $platform->configureProviderDefaultModel('anthropic', 'claude-3-sonnet');

        // Verify the OpenAI default model was not changed
        $this->assertEquals('gpt-4o-mini', $provider->getDefaultModel());
    }

    public function testConfigureProviderDefaultModelWithIteratorClients(): void
    {
        $provider = new OpenAIProvider();
        $client = $this->createMock(ClientInterface::class);
        $client->method('getProvider')->willReturn($provider);

        $iterator = new \ArrayIterator([$client]);
        $platform = new Platform($iterator);

        // Verify initial default model
        $this->assertEquals('gpt-4o-mini', $provider->getDefaultModel());

        // Configure new default model
        $platform->configureProviderDefaultModel('openai', 'gpt-4o');

        // Verify the default model was updated
        $this->assertEquals('gpt-4o', $provider->getDefaultModel());
    }
}
