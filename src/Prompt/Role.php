<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Prompt;

enum Role: string
{
    case USER = 'user';
    case SYSTEM = 'system';
    case ASSISTANT = 'assistant';
}