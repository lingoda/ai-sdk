<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\ModelNotFoundException;

interface ProviderInterface
{
    /**
     * Get the unique identifier for this provider.
     */
    public function getId(): string;

    /**
     * Get the display name for this provider.
     */
    public function getName(): string;

    /**
     * Get all available models for this provider.
     *
     * @return ModelInterface[]
     */
    public function getModels(): array;

    /**
     * Get a specific model by its identifier.
     *
     * @throws ModelNotFoundException When model is not found
     */
    public function getModel(string $modelId): ModelInterface;

    /**
     * Check if this provider has a specific model.
     */
    public function hasModel(string $modelId): bool;

    /**
     * Check if this provider matches the given AIProvider enum.
     */
    public function is(AIProvider $provider): bool;

    /**
     * Get the default model ID for this provider.
     *
     * @throws ModelNotFoundException|InvalidArgumentException
     */
    public function getDefaultModel(): string;

    /**
     * Set the configured default model for this provider.
     */
    public function setDefaultModel(?string $modelId): void;

    /**
     * Get all available model IDs for this provider.
     *
     * @return array<string>
     */
    public function getAvailableModels(): array;
}
