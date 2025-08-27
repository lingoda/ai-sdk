<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Result;

interface ResultInterface
{
    /**
     * Get the content of the result.
     */
    public function getContent(): mixed;
}
