<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Exception;

/**
 * Base exception for all AI SDK errors.
 * All SDK-specific exceptions should extend this.
 */
class AiSdkException extends \Exception implements ExceptionInterface
{
}