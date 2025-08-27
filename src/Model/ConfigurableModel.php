<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Model;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\ModelConfigurationInterface;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;

final readonly class ConfigurableModel implements ModelInterface
{
    public function __construct(
        private ModelConfigurationInterface $configuration,
        private ProviderInterface $provider,
        private ?string $defaultModel = null,
    ) {
    }

    public function getId(): string
    {
        return $this->configuration->getId();
    }

    public function getMaxTokens(): int
    {
        return $this->configuration->getMaxTokens();
    }

    public function getProvider(): ProviderInterface
    {
        return $this->provider;
    }

    public function hasCapability(Capability $capability): bool
    {
        return $this->configuration->hasCapability($capability);
    }

    public function getCapabilities(): array
    {
        return $this->configuration->getCapabilities();
    }

    public function getOptions(): array
    {
        return $this->configuration->getOptions();
    }

    public function getDisplayName(): string
    {
        return $this->configuration->getDisplayName();
    }

    public function getConfiguration(): ModelConfigurationInterface
    {
        return $this->configuration;
    }

    public function getDefaultModel(): string
    {
        // Bundle config takes precedence, fall back to ChatModel's default
        return $this->defaultModel ?? $this->configuration->getDefaultModel();
    }
}