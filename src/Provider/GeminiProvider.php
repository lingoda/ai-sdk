<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Provider;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Enum\Gemini\ChatModel as GeminiChatModel;
use Lingoda\AiSdk\Model\ConfigurableModel;
use Lingoda\AiSdk\ModelInterface;

final class GeminiProvider extends AbstractProvider
{
    public function __construct(?string $defaultModel = null)
    {
        if ($defaultModel !== null) {
            $this->setDefaultModel($defaultModel);
        }
    }

    public function getId(): string
    {
        return AIProvider::GEMINI->value;
    }

    public function getName(): string
    {
        return AIProvider::GEMINI->getName();
    }

    /**
     * @return array<string, ModelInterface>
     */
    protected function createModels(): array
    {
        $models = [];

        // Create models from enum cases
        foreach (GeminiChatModel::cases() as $modelEnum) {
            $models[$modelEnum->value] = new ConfigurableModel(
                $modelEnum,
                $this
            );
        }

        return $models;
    }
}