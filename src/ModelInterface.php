<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk;

interface ModelInterface extends ModelConfigurationInterface
{
    /**
     * Get the provider that owns this model.
     */
    public function getProvider(): ProviderInterface;

    /**
     * Get the underlying configuration (useful for accessing enum-specific methods).
     */
    public function getConfiguration(): ModelConfigurationInterface;
}
