<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Enum\TypeSafe;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\ModelConfigurationInterface;

/**
 * TypeSafe Jev decision models. Text only: the state is a string, a JSON object or a list of texts.
 *
 * @see https://docs.typesafe.ai/models
 */
enum DecisionModel: string implements ModelConfigurationInterface
{
    case JEV_1_13_0 = 'jev-1.13.0';
    /** Alias that moves with TypeSafe releases; prefer the pinned version in production */
    case JEV_LATEST = 'jev-latest';
    case JEV_PREVIEW = 'jev-preview';

    public function getId(): string
    {
        return $this->value;
    }

    /**
     * 64k tokens per request (the state plus the longest question may use 32k of it).
     */
    public function getMaxTokens(): int
    {
        return 64000;
    }

    /**
     * @return Capability[]
     */
    public function getCapabilities(): array
    {
        return [Capability::TEXT];
    }

    public function hasCapability(Capability $capability): bool
    {
        return in_array($capability, $this->getCapabilities(), true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return [];
    }

    public function getDisplayName(): string
    {
        return match ($this) {
            self::JEV_1_13_0 => 'Jev 1.13.0',
            self::JEV_LATEST => 'Jev (latest)',
            self::JEV_PREVIEW => 'Jev (preview)',
        };
    }

    public function getDefaultModel(): string
    {
        return self::JEV_1_13_0->value;
    }
}
