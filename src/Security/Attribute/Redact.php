<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Security\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class Redact
{
    public function __construct(
        private string $pattern,
        private string $replacement = '[REDACTED]'
    ) {}

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getReplacement(): string
    {
        return $this->replacement;
    }
}