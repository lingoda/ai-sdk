<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Prompt;

/**
 * Value object representing an assistant prompt in a conversation
 * Contains previous AI responses or context for continuing a conversation
 */
final readonly class AssistantPrompt extends Prompt
{
    /**
     * Factory method for creating an assistant prompt
     * 
     * @param array<string, mixed> $parameters
     */
    public static function create(string $content, array $parameters = []): self
    {
        return new self($content, $parameters);
    }
    
    /**
     * Get the role identifier for assistant prompts
     */
    public function getRole(): Role
    {
        return Role::ASSISTANT;
    }
}