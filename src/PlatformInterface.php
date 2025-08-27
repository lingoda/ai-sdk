<?php

declare(strict_types=1);

namespace Lingoda\AiSdk;

use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\ModelNotFoundException;
use Lingoda\AiSdk\Exception\RuntimeException;
use Lingoda\AiSdk\Prompt\Conversation;
use Lingoda\AiSdk\Prompt\Prompt;
use Lingoda\AiSdk\Result\ResultInterface;

interface PlatformInterface
{
    /**
     * Simple ask method for common use cases.
     * Automatically resolves models and converts string input to UserPrompt.
     *
     * @param string|Prompt|Conversation $input The input to send to the AI model
     * @param string|null $model Optional model ID to use (if null, uses client's default)
     * @param array<string, mixed> $options Additional options for the request
     *
     * @throws ClientException|InvalidArgumentException|ModelNotFoundException|RuntimeException
     */
    public function ask(string|Prompt|Conversation $input, ?string $model = null, array $options = []): ResultInterface;

    /**
     * @throws InvalidArgumentException
     */
    public function getProvider(string $name): ProviderInterface;

    /**
     * @return string[] List of available provider IDs
     */
    public function getAvailableProviders(): array;

    public function hasProvider(string $name): bool;

    /**
     * Configure the default model for a specific provider.
     */
    public function configureProviderDefaultModel(string $providerName, string $defaultModel): void;
}