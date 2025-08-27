<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Security\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class Sensitive
{
    public function __construct(
        private ?string $type = null,
        private string $redactionText = '[REDACTED]'
    ) {
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getRedactionText(): string
    {
        return $this->redactionText;
    }
}
