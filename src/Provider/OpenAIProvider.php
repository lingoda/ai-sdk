<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Provider;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Enum\OpenAI\ChatModel as OpenAIChatModel;
use Lingoda\AiSdk\Model\ConfigurableModel;
use Lingoda\AiSdk\ModelInterface;

final class OpenAIProvider extends AbstractProvider
{
    public function __construct(?string $defaultModel = null)
    {
        if ($defaultModel !== null) {
            $this->setDefaultModel($defaultModel);
        }
    }

    public function getId(): string
    {
        return AIProvider::OPENAI->value;
    }

    public function getName(): string
    {
        return AIProvider::OPENAI->getName();
    }

    /**
     * @return array<string, ModelInterface>
     */
    protected function createModels(): array
    {
        $models = [];

        // Create models from enum cases
        foreach (OpenAIChatModel::cases() as $modelEnum) {
            $models[$modelEnum->value] = new ConfigurableModel(
                $modelEnum,
                $this
            );
        }

        return $models;
    }
}