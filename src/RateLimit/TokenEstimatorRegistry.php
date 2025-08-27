<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\RateLimit;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\ModelInterface;
use Webmozart\Assert\Assert;

final class TokenEstimatorRegistry
{
    /**
     * @param array<value-of<AIProvider>, TokenEstimatorInterface> $estimators
     */
    public function __construct(
        private array $estimators = [],
    ) {
        Assert::allIsInstanceOf($this->estimators, TokenEstimatorInterface::class);
    }

    /**
     * Register a token estimator for a specific provider.
     */
    public function register(AIProvider $provider, TokenEstimatorInterface $estimator): void
    {
        $this->estimators[$provider->value] = $estimator;
    }

    /**
     * Get the appropriate token estimator for a model.
     */
    public function getEstimatorForModel(ModelInterface $model): TokenEstimatorInterface
    {
        $providerId = $model->getProvider()->getId();
        
        return $this->estimators[$providerId] ?? new TokenEstimator();
    }

    /**
     * Estimate tokens for a model using the appropriate estimator.
     *
     * @param array<string, mixed>|array<int, array{role: string, content: string}>|string $payload
     */
    public function estimate(ModelInterface $model, array|string $payload): int
    {
        return $this->getEstimatorForModel($model)->estimate($model, $payload);
    }

    /**
     * Create a default registry with all built-in estimators.
     */
    public static function createDefault(): self
    {
        $registry = new self();
        
        $registry->register(AIProvider::OPENAI, new OpenAITokenEstimator());
        $registry->register(AIProvider::ANTHROPIC, new AnthropicTokenEstimator());
        $registry->register(AIProvider::GEMINI, new GeminiTokenEstimator());
        
        return $registry;
    }

    /**
     * Get all registered provider IDs.
     *
     * @return value-of<AIProvider>[]
     */
    public function getRegisteredProviders(): array
    {
        return array_keys($this->estimators);
    }

    /**
     * Check if a model has a registered estimator.
     */
    public function hasEstimatorForModel(ModelInterface $model): bool
    {
        return isset($this->estimators[$model->getProvider()->getId()]);
    }
}