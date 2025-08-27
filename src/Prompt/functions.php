<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Prompt;

/**
 * Helper function to create a UserPrompt
 *
 * Example:
 *   use function Lingoda\AiSdk\Prompt\userPrompt;
 *   $conversation = Conversation::fromUser(userPrompt('What is PHP?'));
 */
function userPrompt(string $content): UserPrompt
{
    return UserPrompt::create($content);
}

/**
 * Helper function to create a SystemPrompt
 *
 * Example:
 *   use function Lingoda\AiSdk\Prompt\systemPrompt;
 *   $conversation = Conversation::withSystem(userPrompt('Question'), systemPrompt('Be helpful'));
 */
function systemPrompt(string $content): SystemPrompt
{
    return SystemPrompt::create($content);
}

/**
 * Helper function to create an AssistantPrompt
 *
 * Example:
 *   use function Lingoda\AiSdk\Prompt\assistantPrompt;
 *   $conversation = Conversation::conversation(userPrompt('Q'), systemPrompt('S'), assistantPrompt('A'));
 */
function assistantPrompt(string $content): AssistantPrompt
{
    return AssistantPrompt::create($content);
}
