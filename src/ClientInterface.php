<?php

declare(strict_types=1);

namespace Lingoda\AiSdk;

use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Result\ResultInterface;

interface ClientInterface
{
    /**
     * Check if this client supports the given model.
     */
    public function supports(ModelInterface $model): bool;

    /**
     * Make a request to the AI provider.
     *
     * @param array<string, mixed>|array<int, array{role: string, content: string}>|string $payload
     * @param array<string, mixed> $options
     *
     * @throws ClientException
     */
    public function request(ModelInterface $model, array|string $payload, array $options = []): ResultInterface;

    /**
     * Get the provider this client serves.
     */
    public function getProvider(): ProviderInterface;
}