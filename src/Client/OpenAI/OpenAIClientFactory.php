<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Client\OpenAI;

use OpenAI;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;

final readonly class OpenAIClientFactory
{
    public function __construct(
        private string $apiKey,
        private ?string $organization = null,
        private int $timeout = 30,
        private ?ClientInterface $httpClient = null,
    ) {
    }

    public static function createClient(
        string $apiKey,
        ?string $organization = null,
        int $timeout = 30,
        ?ClientInterface $httpClient = null,
        LoggerInterface $logger = new NullLogger(),
    ): OpenAIClient {
        return (new self($apiKey, $organization, $timeout, $httpClient))->create($logger);
    }

    public function create(
        LoggerInterface $logger = new NullLogger(),
    ): OpenAIClient {
        $httpClient = $this->httpClient ?? new Psr18Client(HttpClient::create([
            'timeout' => $this->timeout,
        ]));

        $client = OpenAI::factory()
            ->withApiKey($this->apiKey)
            ->withHttpClient($httpClient)
            ->withOrganization($this->organization)
            ->make()
        ;

        return new OpenAIClient($client, $logger);
    }
}
