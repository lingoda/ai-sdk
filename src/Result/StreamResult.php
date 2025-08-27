<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Result;

use Psr\Http\Message\StreamInterface;

/**
 * Represents a streaming result from an AI provider.
 * Wraps a PSR-7 stream for provider-agnostic streaming responses.
 * The stream is not consumed until explicitly read.
 *
 * @implements \IteratorAggregate<int, string>
 */
final class StreamResult extends BaseResult implements \IteratorAggregate
{
    /**
     * @param StreamInterface $stream The PSR-7 stream to wrap
     * @param string $mimeType The MIME type of the streamed content
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        private readonly StreamInterface $stream,
        private readonly string $mimeType = 'application/octet-stream',
        array $metadata = [],
    ) {
        parent::__construct($metadata);
    }

    /**
     * Get the underlying PSR-7 stream.
     * This allows direct access to the stream for advanced usage.
     */
    public function getContent(): StreamInterface
    {
        return $this->stream;
    }

    /**
     * Get the MIME type of the streamed content.
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Create an iterator that yields chunks from the stream.
     * This allows for foreach iteration over the stream chunks.
     *
     * @return \Generator<string>
     */
    public function getIterator(): \Generator
    {
        while (!$this->stream->eof()) {
            yield $this->stream->read(1024);
        }
    }

    /**
     * Check if the stream is readable.
     */
    public function isReadable(): bool
    {
        return $this->stream->isReadable();
    }

    /**
     * Check if the stream is at end-of-file.
     */
    public function eof(): bool
    {
        return $this->stream->eof();
    }

    /**
     * Read data from the stream.
     *
     * @param int $length Number of bytes to read
     * @return string The data read from the stream
     */
    public function read(int $length): string
    {
        return $this->stream->read($length);
    }
}
