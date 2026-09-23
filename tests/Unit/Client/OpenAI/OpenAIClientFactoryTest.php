<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Client\OpenAI;

use Lingoda\AiSdk\Client\OpenAI\OpenAIClient;
use Lingoda\AiSdk\Client\OpenAI\OpenAIClientFactory;
use Lingoda\AiSdk\Enum\AIProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

final class OpenAIClientFactoryTest extends TestCase
{
    public function testCreateClientBuildsADefaultHttpClient(): void
    {
        $client = OpenAIClientFactory::createClient('test-key');

        $this->assertInstanceOf(OpenAIClient::class, $client);
        $this->assertTrue($client->getProvider()->is(AIProvider::OPENAI));
    }

    public function testCreateUsesTheInjectedHttpClient(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->never())->method('sendRequest');

        $client = (new OpenAIClientFactory('test-key', 'org-test', 10, $httpClient))->create();

        $this->assertInstanceOf(OpenAIClient::class, $client);
    }
}
