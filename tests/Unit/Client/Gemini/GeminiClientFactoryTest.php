<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Client\Gemini;

use Lingoda\AiSdk\Client\Gemini\GeminiClient;
use Lingoda\AiSdk\Client\Gemini\GeminiClientFactory;
use Lingoda\AiSdk\Enum\AIProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

final class GeminiClientFactoryTest extends TestCase
{
    public function testCreateClientBuildsADefaultHttpClient(): void
    {
        $client = GeminiClientFactory::createClient('test-key');

        $this->assertInstanceOf(GeminiClient::class, $client);
        $this->assertTrue($client->getProvider()->is(AIProvider::GEMINI));
    }

    public function testCreateUsesTheInjectedHttpClient(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->never())->method('sendRequest');

        $client = (new GeminiClientFactory('test-key', 10, $httpClient))->create();

        $this->assertInstanceOf(GeminiClient::class, $client);
    }
}
