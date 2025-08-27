<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk;

use Lingoda\AiSdk\Enum\Capability;

/**
 * Interface for model configuration data.
 * Implemented by enums to provide static model specifications.
 */
interface ModelConfigurationInterface
{
    /**
     * Get the unique identifier for this model.
     */
    public function getId(): string;

    /**
     * Get the maximum number of tokens this model can handle.
     */
    public function getMaxTokens(): int;

    /**
     * Get all supported capabilities for this model.
     *
     * @return Capability[]
     */
    public function getCapabilities(): array;

    /**
     * Check if this model supports a specific capability.
     */
    public function hasCapability(Capability $capability): bool;

    /**
     * Get default options for this model.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array;

    /**
     * Get the human-readable display name of the model.
     */
    public function getDisplayName(): string;

    /**
     * Get the default model ID for this model configuration.
     * This is used when no specific model is requested.
     */
    public function getDefaultModel(): string;
}
