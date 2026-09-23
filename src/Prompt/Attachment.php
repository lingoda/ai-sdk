<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Prompt;

use Lingoda\AiSdk\Exception\InvalidArgumentException;

/**
 * A document or image sent alongside the user prompt.
 *
 * Only the mime type and size are exposed for logging and tracing; there is no filename.
 */
final class Attachment
{
    public const string PDF = 'application/pdf';
    public const string DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    public const array IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    /** Sent to the model as text, so every provider can read them */
    public const array TEXT_TYPES = ['text/plain', 'text/csv', 'text/markdown', 'text/html', 'application/json'];
    public const array MIME_TYPES = [self::PDF, self::DOCX, ...self::IMAGE_TYPES, ...self::TEXT_TYPES];

    public const int MAX_BYTES = 15 * 1024 * 1024;

    public readonly string $sha256;

    /**
     * @var \WeakMap<self, string>|null
     */
    private static ?\WeakMap $bytes = null;

    /**
     * @throws InvalidArgumentException
     */
    private function __construct(
        #[\SensitiveParameter]
        string $bytes,
        public readonly string $mimeType,
    ) {
        if (!in_array($mimeType, self::MIME_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported attachment type "%s". Supported: %s',
                $mimeType,
                implode(', ', self::MIME_TYPES)
            ));
        }

        if ($bytes === '') {
            throw new InvalidArgumentException('Attachment cannot be empty.');
        }

        if (mb_strlen($bytes, '8bit') > self::MAX_BYTES) {
            throw new InvalidArgumentException(sprintf(
                'Attachment exceeds the size limit of %d bytes.',
                self::MAX_BYTES
            ));
        }

        if (in_array($mimeType, self::TEXT_TYPES, true) && !mb_check_encoding($bytes, 'UTF-8')) {
            throw new InvalidArgumentException(sprintf('Text attachment "%s" must be valid UTF-8.', $mimeType));
        }

        $this->sha256 = hash('sha256', $bytes);
        self::$bytes ??= new \WeakMap();
        self::$bytes[$this] = $bytes;
    }

    /**
     * Attachments are immutable and not cloneable.
     */
    private function __clone()
    {
    }

    /**
     * @param string|null $mimeType Detected from the content when omitted
     *
     * @throws InvalidArgumentException
     */
    public static function fromBytes(#[\SensitiveParameter] string $bytes, ?string $mimeType = null): self
    {
        return new self($bytes, $mimeType ?? self::detectMimeType($bytes));
    }

    public function bytes(): string
    {
        return self::$bytes[$this] ?? throw new \LogicException('Attachment bytes are missing.');
    }

    public function isImage(): bool
    {
        return in_array($this->mimeType, self::IMAGE_TYPES, true);
    }

    /**
     * Text formats (plain text, CSV, Markdown, HTML, JSON): clients send the content as text.
     */
    public function isText(): bool
    {
        return in_array($this->mimeType, self::TEXT_TYPES, true);
    }

    public function size(): int
    {
        return mb_strlen($this->bytes(), '8bit');
    }

    /**
     * Safe representation for logs and traces: no bytes, no filename.
     *
     * @return array{mime: string, size: int}
     */
    public function toMetadata(): array
    {
        return ['mime' => $this->mimeType, 'size' => $this->size()];
    }

    /**
     * @return array{mime: string, size: int}
     */
    public function __debugInfo(): array
    {
        return $this->toMetadata();
    }

    /**
     * Attachments cannot be serialized: pass a storage reference and create the Attachment where the request is sent.
     *
     * @return never
     */
    public function __serialize(): array
    {
        throw new \LogicException('Attachment cannot be serialized: pass a storage reference, not the document bytes.');
    }

    /**
     * @param array<mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('Attachment cannot be unserialized.');
    }

    /**
     * An undetectable type becomes application/octet-stream, which the allowlist then rejects.
     */
    private static function detectMimeType(#[\SensitiveParameter] string $bytes): string
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
    }
}
