<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Client\OpenAI;

use Lingoda\AiSdk\Audio\OpenAI\AudioOptions;
use Lingoda\AiSdk\Client\OpenAI\OpenAIClient;
use Lingoda\AiSdk\Client\OpenAI\OpenAIClientFactory;
use Lingoda\AiSdk\Enum\AIProvider;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

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

    public function testBaseUriIsWhereTheCallGoes(): void
    {
        $seen = null;
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->method('sendRequest')
            ->willReturnCallback(static function (RequestInterface $request) use (&$seen): Response {
                $seen = $request;

                return new Response(200, ['Content-Type' => 'audio/mpeg'], 'AUDIO');
            })
        ;

        (new OpenAIClientFactory('test-key', null, 10, $httpClient, 'eu.api.openai.com/v1'))
            ->create()
            ->textToSpeech('hello', AudioOptions::textToSpeech())
        ;

        self::assertInstanceOf(RequestInterface::class, $seen);
        self::assertSame('https://eu.api.openai.com/v1/audio/speech', (string) $seen->getUri());
    }

    public function testWithoutABaseUriTheCallGoesToOpenAI(): void
    {
        $seen = null;
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->method('sendRequest')
            ->willReturnCallback(static function (RequestInterface $request) use (&$seen): Response {
                $seen = $request;

                return new Response(200, ['Content-Type' => 'audio/mpeg'], 'AUDIO');
            })
        ;

        (new OpenAIClientFactory('test-key', null, 10, $httpClient))
            ->create()
            ->textToSpeech('hello', AudioOptions::textToSpeech())
        ;

        self::assertInstanceOf(RequestInterface::class, $seen);
        self::assertSame('https://api.openai.com/v1/audio/speech', (string) $seen->getUri());
    }
}
