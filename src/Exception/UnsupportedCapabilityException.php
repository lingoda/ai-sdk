<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Exception;

/**
 * Exception thrown when attempting to use a capability
 * that is not supported by the selected model.
 */
final class UnsupportedCapabilityException extends InvalidArgumentException
{
}
