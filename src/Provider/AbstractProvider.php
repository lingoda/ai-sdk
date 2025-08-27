<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Provider;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\ModelNotFoundException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;

abstract class AbstractProvider implements ProviderInterface
{
    /**
     * @var array<string, ModelInterface>
     */
    private array $models = [];

    /**
     * @var string|null Configured default model ID
     */
    private ?string $configuredDefaultModel = null;

    public function getModels(): array
    {
        $this->loadModels();

        return array_values($this->models);
    }

    public function getModel(string $modelId): ModelInterface
    {
        $this->loadModels();

        if (!isset($this->models[$modelId])) {
            throw new ModelNotFoundException(sprintf(
                'Model "%s" is not supported by provider "%s"',
                $modelId,
                $this->getId(),
            ));
        }

        return $this->models[$modelId];
    }

    public function hasModel(string $modelId): bool
    {
        $this->loadModels();

        return isset($this->models[$modelId]);
    }

    private function loadModels(): void
    {
        if (empty($this->models)) {
            $this->models = $this->createModels();
        }
    }

    public function is(AIProvider $provider): bool
    {
        return $this->getId() === $provider->value;
    }

    public function getDefaultModel(): string
    {
        // If a default model was explicitly configured, validate it exists
        if ($this->configuredDefaultModel !== null) {
            if (!$this->hasModel($this->configuredDefaultModel)) {
                throw new InvalidArgumentException(sprintf(
                    'Configured default model "%s" is not available for provider "%s". Available models: %s',
                    $this->configuredDefaultModel,
                    $this->getId(),
                    implode(', ', $this->getAvailableModels())
                ));
            }
            return $this->configuredDefaultModel;
        }

        $models = $this->getModels();
        if (empty($models)) {
            throw new ModelNotFoundException(sprintf(
                'No models available for provider "%s"',
                $this->getId()
            ));
        }

        // Get default model from first model's configuration
        return reset($models)->getDefaultModel();
    }

    public function setDefaultModel(?string $modelId): void
    {
        $this->configuredDefaultModel = $modelId;
    }

    public function getAvailableModels(): array
    {
        $this->loadModels();
        return array_keys($this->models);
    }

    /**
     * Create all models for this provider.
     *
     * @return array<string, ModelInterface>
     */
    abstract protected function createModels(): array;
}
