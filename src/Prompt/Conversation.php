<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Prompt;

use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Security\DataSanitizer;

/**
 * Value object representing an AI conversation with system, assistant, and user prompts
 *
 * This is the central object for all conversation-related operations:
 * - Sanitization of sensitive data
 * - Token estimation
 * - Rate limiting calculations
 * - Conversion to provider-specific formats
 */
final readonly class Conversation
{
    private UserPrompt $userPrompt;
    private ?SystemPrompt $systemPrompt;
    private ?AssistantPrompt $assistantPrompt;
    private bool $isSanitized;

    /**
     * @param UserPrompt $userPrompt The user's input (required)
     * @param SystemPrompt|null $systemPrompt System instructions/context
     * @param AssistantPrompt|null $assistantPrompt Assistant's previous response or context
     * @param bool $isSanitized Whether this conversation has been sanitized
     */
    public function __construct(
        UserPrompt $userPrompt,
        ?SystemPrompt $systemPrompt = null,
        ?AssistantPrompt $assistantPrompt = null,
        bool $isSanitized = false
    ) {
        $this->userPrompt = $userPrompt;
        $this->systemPrompt = $systemPrompt;
        $this->assistantPrompt = $assistantPrompt;
        $this->isSanitized = $isSanitized;
    }

    /**
     * Create a conversation from just a user prompt
     */
    public static function fromUser(UserPrompt $prompt): self
    {
        return new self($prompt);
    }

    /**
     * Create a conversation with system and user prompts
     */
    public static function withSystem(UserPrompt $user, SystemPrompt $system): self
    {
        return new self($user, $system);
    }

    /**
     * Create a full conversation
     */
    public static function conversation(
        UserPrompt $user,
        SystemPrompt $system,
        AssistantPrompt $assistant
    ): self {
        return new self($user, $system, $assistant);
    }

    /**
     * Create from an array format (common in APIs)
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        // Handle simple content field
        if (isset($data['content']) && is_string($data['content'])) {
            return new self(UserPrompt::create($data['content']));
        }

        // Handle messages array format
        if (isset($data['messages']) && is_array($data['messages'])) {
            $systemPrompt = null;
            $userPrompt = null;
            $assistantPrompt = null;

            foreach ($data['messages'] as $message) {
                if (!is_array($message) || !isset($message['role'], $message['content'])
                    || !is_string($message['role']) || !is_string($message['content'])) {
                    continue;
                }

                if (isset($message['attachments'])) {
                    throw new InvalidArgumentException('Attachments cannot be restored from an array: toArray() keeps only their metadata.');
                }

                switch ($message['role']) {
                    case 'system':
                        $systemPrompt = SystemPrompt::create($message['content']);
                        break;
                    case 'user':
                        $userPrompt = UserPrompt::create($message['content']);
                        break;
                    case 'assistant':
                        $assistantPrompt = AssistantPrompt::create($message['content']);
                        break;
                }
            }

            if ($userPrompt === null) {
                throw new InvalidArgumentException('No user message found in messages array');
            }

            return new self($userPrompt, $systemPrompt, $assistantPrompt);
        }

        // Handle other common fields
        $userFields = ['prompt', 'query', 'question', 'text', 'input', 'user'];
        foreach ($userFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                return new self(
                    UserPrompt::create($data[$field]),
                    isset($data['system']) && is_string($data['system']) ? SystemPrompt::create($data['system']) : null,
                    isset($data['assistant']) && is_string($data['assistant']) ? AssistantPrompt::create($data['assistant']) : null
                );
            }
        }

        throw new InvalidArgumentException('Unable to extract conversation from array data');
    }

    /**
     * Get the user prompt
     */
    public function getUserPrompt(): UserPrompt
    {
        return $this->userPrompt;
    }

    /**
     * Get the system prompt
     */
    public function getSystemPrompt(): ?SystemPrompt
    {
        return $this->systemPrompt;
    }

    /**
     * Get the assistant prompt
     */
    public function getAssistantPrompt(): ?AssistantPrompt
    {
        return $this->assistantPrompt;
    }

    /**
     * Get the user prompt content as string
     */
    public function getUserContent(): string
    {
        return $this->userPrompt->getContent();
    }

    /**
     * Get the system prompt content as string (null if not set)
     */
    public function getSystemContent(): ?string
    {
        return $this->systemPrompt?->getContent();
    }

    /**
     * Get the assistant prompt content as string (null if not set)
     */
    public function getAssistantContent(): ?string
    {
        return $this->assistantPrompt?->getContent();
    }

    /**
     * Check if this conversation has been sanitized
     */
    public function isSanitized(): bool
    {
        return $this->isSanitized;
    }

    /**
     * Create a sanitized version of this conversation
     * Only sanitizes the user prompt, preserving system and assistant prompts
     */
    public function sanitize(DataSanitizer $sanitizer): self
    {
        $originalUserContent = $this->userPrompt->getContent();
        $sanitizedUserContent = $sanitizer->sanitize($originalUserContent);

        // If nothing changed, return the same instance
        if ($sanitizedUserContent === $originalUserContent) {
            return $this;
        }

        // Attachments are sent as provided: pattern redaction corrupts data files (ids read as phone numbers)
        return new self(
            new UserPrompt(
                is_string($sanitizedUserContent) ? $sanitizedUserContent : $originalUserContent,
                attachments: $this->userPrompt->getAttachments(),
            ),
            $this->systemPrompt,
            $this->assistantPrompt,
            true // Mark as sanitized
        );
    }

    /**
     * Add or update the system prompt
     */
    public function withSystemPrompt(SystemPrompt $systemPrompt): self
    {
        return new self(
            $this->userPrompt,
            $systemPrompt,
            $this->assistantPrompt,
            $this->isSanitized
        );
    }

    /**
     * Replace the attachments (documents, images) of the user prompt
     */
    public function withAttachments(Attachment ...$attachments): self
    {
        return new self(
            $this->userPrompt->withAttachments(...$attachments),
            $this->systemPrompt,
            $this->assistantPrompt,
            $this->isSanitized
        );
    }

    /**
     * Add or update the assistant prompt
     */
    public function withAssistantPrompt(AssistantPrompt $assistantPrompt): self
    {
        return new self(
            $this->userPrompt,
            $this->systemPrompt,
            $assistantPrompt,
            $this->isSanitized
        );
    }

    /**
     * Convert to messages array format (common for chat APIs)
     *
     * Attachments appear as metadata only (mime, size), so this is safe for traces and queued messages.
     *
     * @return array<int, array{role: string, content: string, attachments?: list<array{mime: string, size: int}>}>
     */
    public function toArray(): array
    {
        $messages = $this->contextMessages();
        $user = $this->userPrompt->toArray();

        if ($this->userPrompt->hasAttachments()) {
            $user['attachments'] = array_map(
                static fn (Attachment $attachment): array => $attachment->toMetadata(),
                $this->userPrompt->getAttachments()
            );
        }

        $messages[] = $user;

        return $messages;
    }

    /**
     * Payload handed to clients: same shape as toArray(), but attachments carry the Attachment objects.
     *
     * @internal used by Platform only, never log or trace this output
     *
     * @return array<int, array{role: string, content: string, attachments?: list<Attachment>}>
     */
    public function toRequestArray(): array
    {
        $messages = $this->contextMessages();
        $user = $this->userPrompt->toArray();

        if ($this->userPrompt->hasAttachments()) {
            $user['attachments'] = $this->userPrompt->getAttachments();
        }

        $messages[] = $user;

        return $messages;
    }

    /**
     * Convert to a simple string (returns user prompt)
     */
    public function toString(): string
    {
        return $this->userPrompt->getContent();
    }

    /**
     * Get the full text content for token estimation
     */
    public function getFullContent(): string
    {
        $parts = [];

        if ($this->systemPrompt !== null) {
            $parts[] = $this->systemPrompt->getContent();
        }

        if ($this->assistantPrompt !== null) {
            $parts[] = $this->assistantPrompt->getContent();
        }

        $parts[] = $this->userPrompt->getContent();

        return implode("\n", $parts);
    }

    /**
     * Estimate the number of tokens in this conversation
     *
     * @param callable(string): int $estimator A function that estimates tokens for a string
     */
    public function estimateTokens(callable $estimator): int
    {
        return $estimator($this->getFullContent());
    }

    /**
     * Check if this conversation equals another
     */
    public function equals(self $other): bool
    {
        return $this->userPrompt->equals($other->userPrompt)
            && (($this->systemPrompt === null && $other->systemPrompt === null)
                || ($this->systemPrompt !== null && $other->systemPrompt !== null && $this->systemPrompt->equals($other->systemPrompt)))
            && (($this->assistantPrompt === null && $other->assistantPrompt === null)
                || ($this->assistantPrompt !== null && $other->assistantPrompt !== null && $this->assistantPrompt->equals($other->assistantPrompt)));
    }

    /**
     * Create a hash for caching purposes
     */
    public function hash(): string
    {
        if ($this->userPrompt->hasAttachments()) {
            // Separate, length-prefixed encoding: no text-only conversation can produce the same hash
            $parts = [
                $this->systemPrompt?->getContent() ?? '',
                $this->assistantPrompt?->getContent() ?? '',
                $this->userPrompt->getContent(),
                ...array_map(
                    static fn (Attachment $attachment): string => $attachment->mimeType . ':' . $attachment->sha256,
                    $this->userPrompt->getAttachments()
                ),
            ];

            return md5('conversation-with-attachments:' . implode('', array_map(
                static fn (string $part): string => mb_strlen($part, '8bit') . ':' . $part,
                $parts
            )));
        }

        return md5(sprintf(
            'system:%s|assistant:%s|user:%s',
            $this->systemPrompt?->getContent() ?? '',
            $this->assistantPrompt?->getContent() ?? '',
            $this->userPrompt->getContent()
        ));
    }

    /**
     * System and assistant messages, in the order they precede the user message.
     *
     * @return list<array{role: string, content: string}>
     */
    private function contextMessages(): array
    {
        $messages = [];

        if ($this->systemPrompt !== null) {
            $messages[] = $this->systemPrompt->toArray();
        }

        if ($this->assistantPrompt !== null) {
            $messages[] = $this->assistantPrompt->toArray();
        }

        return $messages;
    }
}
