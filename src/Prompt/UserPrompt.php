<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Prompt;

use Webmozart\Assert\Assert;

/**
 * Value object representing a user prompt in a conversation
 * This is the primary input from the user that will be processed by the AI
 *
 * Documents and images travel with the user prompt as attachments. The text content is still required.
 */
final readonly class UserPrompt extends Prompt
{
    /**
     * @var list<Attachment>
     */
    private array $attachments;

    /**
     * @param array<string, mixed> $parameters
     * @param list<Attachment> $attachments
     */
    public function __construct(string $content, array $parameters = [], array $attachments = [])
    {
        parent::__construct($content, $parameters);
        Assert::allIsInstanceOf($attachments, Attachment::class);
        $this->attachments = array_values($attachments);
    }

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

    /**
     * Replace the attachments of this prompt.
     */
    public function withAttachments(Attachment ...$attachments): self
    {
        return new self($this->content, [], array_values($attachments));
    }

    /**
     * @return list<Attachment>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function hasAttachments(): bool
    {
        return $this->attachments !== [];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function withParameters(array $parameters): static
    {
        $compiled = parent::withParameters($parameters);
        if ($compiled === $this) {
            return $this;
        }

        return new self($compiled->getContent(), [], $this->attachments);
    }

    public function equals(Prompt $other): bool
    {
        return parent::equals($other)
            && $other instanceof self
            && $this->attachmentHashes() === $other->attachmentHashes();
    }

    public function hash(): string
    {
        if ($this->attachments === []) {
            return parent::hash();
        }

        return md5(parent::hash() . ':' . implode(',', $this->attachmentHashes()));
    }

    /**
     * @return list<string>
     */
    private function attachmentHashes(): array
    {
        // The mime type is part of the identity
        return array_map(static fn (Attachment $attachment): string => $attachment->mimeType . ':' . $attachment->sha256, $this->attachments);
    }
}
