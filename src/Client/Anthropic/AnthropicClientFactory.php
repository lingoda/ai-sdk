<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Client\Anthropic;

use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

final readonly class AnthropicClientFactory
{
    public function __construct(
        private string $apiKey,
        private int $timeout = 30,
        private ?ClientInterface $httpClient = null,
    ) {
    }

    /**
     * Static factory method for convenient client creation.
     */
    public static function createClient(
        string $apiKey,
        int $timeout = 30,
        ?ClientInterface $httpClient = null,
        LoggerInterface $logger = new NullLogger(),
    ): AnthropicClient {
        return (new self($apiKey, $timeout, $httpClient))->create($logger);
    }

    public function create(
        LoggerInterface $logger = new NullLogger(),
    ): AnthropicClient {
        $httpClient = $this->httpClient ?? new Psr18Client(HttpClient::create([
            'timeout' => $this->timeout,
        ]));

        // Use factory method to ensure we can control the HTTP client and include required version header
        // The anthropic-version header is REQUIRED by Anthropic's API for all requests
        $client = \Anthropic::factory()
            ->withApiKey($this->apiKey)
            ->withHttpClient($httpClient)
            ->withHttpHeader('anthropic-version', '2023-06-01')
            ->make()
        ;

        return new AnthropicClient($client, $logger);
    }
}
