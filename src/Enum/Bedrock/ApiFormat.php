<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Enum\Bedrock;

/**
 * Request and response body shape a Bedrock model speaks over InvokeModel.
 *
 * @internal
 */
enum ApiFormat
{
    /** Anthropic Messages body (content blocks with a type), snake_case usage */
    case ANTHROPIC_MESSAGES;
    /** Converse-shaped body (content blocks keyed by kind), camelCase usage */
    case CONVERSE;
}
