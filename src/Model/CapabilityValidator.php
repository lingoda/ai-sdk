<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Model;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\Exception\UnsupportedCapabilityException;
use Lingoda\AiSdk\ModelInterface;

/**
 * Validates that models support required capabilities for specific operations.
 */
final class CapabilityValidator
{
    /**
     * Validates that a model supports the required capability.
     *
     * @throws UnsupportedCapabilityException if the model lacks the capability
     */
    public static function requireCapability(ModelInterface $model, Capability $capability): void
    {
        if (!$model->hasCapability($capability)) {
            throw new UnsupportedCapabilityException(sprintf(
                'Model "%s" does not support required capability "%s". Available capabilities: %s',
                $model->getId(),
                $capability->value,
                implode(', ', array_map(static fn ($cap) => $cap->value, $model->getCapabilities()))
            ));
        }
    }

    /**
     * Validates that a model supports all of the required capabilities.
     *
     * @param array<Capability> $capabilities
     * @throws UnsupportedCapabilityException if the model lacks any capability
     */
    public static function requireCapabilities(ModelInterface $model, array $capabilities): void
    {
        $missing = [];
        foreach ($capabilities as $capability) {
            if (!$model->hasCapability($capability)) {
                $missing[] = $capability->value;
            }
        }

        if (!empty($missing)) {
            throw new UnsupportedCapabilityException(sprintf(
                'Model "%s" does not support required capabilities: %s. Available capabilities: %s',
                $model->getId(),
                implode(', ', $missing),
                implode(', ', array_map(static fn ($cap) => $cap->value, $model->getCapabilities()))
            ));
        }
    }
}
