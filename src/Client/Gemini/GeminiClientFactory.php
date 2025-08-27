<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Client\Gemini;

use Gemini;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

final readonly class GeminiClientFactory
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
    ): GeminiClient {
        return (new self($apiKey, $timeout, $httpClient))->create($logger);
    }

    public function create(
        LoggerInterface $logger = new NullLogger(),
    ): GeminiClient {
        $httpClient = $this->httpClient ?? new Psr18Client(HttpClient::create([
            'timeout' => $this->timeout,
        ]));

        $client = Gemini::factory()
            ->withApiKey($this->apiKey)
            ->withHttpClient($httpClient)
            ->make();

        return new GeminiClient($client, $logger);
    }
}