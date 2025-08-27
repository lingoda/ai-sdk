<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Prompt;

/**
 * Value object representing a system prompt in a conversation
 * Contains instructions or context that guides the AI's behavior
 */
final readonly class SystemPrompt extends Prompt
{
    /**
     * Factory method for creating a system prompt
     *
     * @param array<string, mixed> $parameters
     */
    public static function create(string $content, array $parameters = []): self
    {
        return new self($content, $parameters);
    }
    
    /**
     * Get the role identifier for system prompts
     */
    public function getRole(): Role
    {
        return Role::SYSTEM;
    }
}
