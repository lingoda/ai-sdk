<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Provider;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Enum\Bedrock\ChatModel as BedrockChatModel;
use Lingoda\AiSdk\Model\ConfigurableModel;
use Lingoda\AiSdk\ModelInterface;

final class BedrockProvider extends AbstractProvider
{
    public function __construct(?string $defaultModel = null)
    {
        if ($defaultModel !== null) {
            $this->setDefaultModel($defaultModel);
        }
    }

    public function getId(): string
    {
        return AIProvider::BEDROCK->value;
    }

    public function getName(): string
    {
        return AIProvider::BEDROCK->getName();
    }

    /**
     * @return array<string, ModelInterface>
     */
    protected function createModels(): array
    {
        $models = [];

        // Create models from enum cases
        foreach (BedrockChatModel::cases() as $modelEnum) {
            $models[$modelEnum->value] = new ConfigurableModel(
                $modelEnum,
                $this
            );
        }

        return $models;
    }
}
