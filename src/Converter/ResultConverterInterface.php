<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Converter;

use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\RuntimeException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Result\ResultInterface;

/**
 * @template T of object
 */
interface ResultConverterInterface
{
    /**
     * Check if this converter supports the given model and response object.
     */
    public function supports(ModelInterface $model, mixed $response): bool;

    /**
     * Convert the provider's response object to a typed result.
     *
     * @throws InvalidArgumentException|RuntimeException
     *
     * @param T $response
     */
    public function convert(ModelInterface $model, object $response): ResultInterface;
}