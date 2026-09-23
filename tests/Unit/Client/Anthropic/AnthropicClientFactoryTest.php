<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Client\Anthropic;

use Lingoda\AiSdk\Client\Anthropic\AnthropicClient;
use Lingoda\AiSdk\Client\Anthropic\AnthropicClientFactory;
use Lingoda\AiSdk\Enum\AIProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

final class AnthropicClientFactoryTest extends TestCase
{
    public function testCreateClientBuildsADefaultHttpClient(): void
    {
        $client = AnthropicClientFactory::createClient('test-key');

        $this->assertInstanceOf(AnthropicClient::class, $client);
        $this->assertTrue($client->getProvider()->is(AIProvider::ANTHROPIC));
    }

    public function testCreateUsesTheInjectedHttpClient(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->never())->method('sendRequest');

        $client = (new AnthropicClientFactory('test-key', 10, $httpClient))->create();

        $this->assertInstanceOf(AnthropicClient::class, $client);
    }
}
