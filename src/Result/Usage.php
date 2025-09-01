<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Result;

/**
 * Represents token usage information from AI provider responses.
 * Provides both provider-specific data and normalized format for integrations like Langfuse.
 */
final class Usage
{
    /**
     * @param int $promptTokens Number of tokens in the prompt/input
     * @param int $completionTokens Number of tokens in the completion/output
     * @param int $totalTokens Total tokens used (prompt + completion)
     * @param TokenDetails|null $promptDetails Detailed breakdown of prompt tokens
     * @param TokenDetails|null $completionDetails Detailed breakdown of completion tokens
     * @param int|null $cachedTokens Cached tokens (provider-specific)
     * @param int|null $toolUseTokens Tokens used for tool calls
     * @param int|null $reasoningTokens Tokens used for reasoning (OpenAI o1 models)
     * @param int|null $thoughtsTokens Tokens used for thinking (Gemini thinking models)
     */
    public function __construct(
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly int $totalTokens,
        public readonly ?TokenDetails $promptDetails = null,
        public readonly ?TokenDetails $completionDetails = null,
        public readonly ?int $cachedTokens = null,
        public readonly ?int $toolUseTokens = null,
        public readonly ?int $reasoningTokens = null,
        public readonly ?int $thoughtsTokens = null,
    ) {
    }

    /**
     * Get normalized usage data in Langfuse format.
     *
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function toLangfuse(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }

    /**
     * Get all usage data including detailed breakdowns.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
        ];

        if ($this->promptDetails !== null) {
            $result['prompt_details'] = $this->promptDetails->toArray();
        }

        if ($this->completionDetails !== null) {
            $result['completion_details'] = $this->completionDetails->toArray();
        }

        if ($this->cachedTokens !== null) {
            $result['cached_tokens'] = $this->cachedTokens;
        }

        if ($this->toolUseTokens !== null) {
            $result['tool_use_tokens'] = $this->toolUseTokens;
        }

        if ($this->reasoningTokens !== null) {
            $result['reasoning_tokens'] = $this->reasoningTokens;
        }

        if ($this->thoughtsTokens !== null) {
            $result['thoughts_tokens'] = $this->thoughtsTokens;
        }

        return $result;
    }
}
