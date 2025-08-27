<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Prompt;

/**
 * Value object representing a user prompt in a conversation
 * This is the primary input from the user that will be processed by the AI
 */
final readonly class UserPrompt extends Prompt
{
    /**
     * Factory method for creating a user prompt
     *
     * @param array<string, mixed> $parameters
     */
    public static function create(string $content, array $parameters = []): self
    {
        return new self($content, $parameters);
    }
    
    /**
     * Get the role identifier for user prompts
     */
    public function getRole(): Role
    {
        return Role::USER;
    }
}
