<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Provider;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Enum\TypeSafe\DecisionModel as TypeSafeDecisionModel;
use Lingoda\AiSdk\Model\ConfigurableModel;
use Lingoda\AiSdk\ModelInterface;

/**
 * Decision provider for TypeSafe Jev. Used by TypeSafeDecisionPlatform, never registered on Platform.
 */
final class TypeSafeProvider extends AbstractProvider
{
    public function __construct(?string $defaultModel = null)
    {
        if ($defaultModel !== null) {
            $this->setDefaultModel($defaultModel);
        }
    }

    public function getId(): string
    {
        return AIProvider::TYPESAFE->value;
    }

    public function getName(): string
    {
        return AIProvider::TYPESAFE->getName();
    }

    /**
     * @return array<string, ModelInterface>
     */
    protected function createModels(): array
    {
        $models = [];

        // Create models from enum cases
        foreach (TypeSafeDecisionModel::cases() as $modelEnum) {
            $models[$modelEnum->value] = new ConfigurableModel(
                $modelEnum,
                $this
            );
        }

        return $models;
    }
}
