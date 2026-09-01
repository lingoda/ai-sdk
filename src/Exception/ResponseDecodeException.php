<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Exception;

use Lingoda\AiSdk\Result\ResultInterface;

/**
 * Thrown when a provider response was received and billed, but its content
 * could not be decoded into the requested structured format.
 */
class ResponseDecodeException extends ClientException
{
    public function __construct(
        private readonly ResultInterface $result,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /**
     * The raw result as returned by the provider, including metadata
     * (e.g. finish reason, prompt feedback) and token usage.
     */
    public function getResult(): ResultInterface
    {
        return $this->result;
    }
}
