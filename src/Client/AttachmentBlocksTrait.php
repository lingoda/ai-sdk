<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Client;

use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\UnsupportedCapabilityException;
use Lingoda\AiSdk\Prompt\Attachment;
use Psr\Log\LoggerInterface;

/**
 * Attachment handling shared by the chat clients.
 */
trait AttachmentBlocksTrait
{
    /**
     * @param array<mixed>|string $payload
     * @param callable(Attachment, int): array<string, mixed> $toBlock Gets the attachment and its 1-based position
     *
     * @throws ClientException when an attachments entry is not an Attachment
     *
     * @return array<mixed>|string
     */
    private function expandAttachments(array|string $payload, callable $toBlock): array|string
    {
        if (is_string($payload)) {
            return $payload;
        }

        // Legacy {messages: [...]} shape accepted by the clients
        if (isset($payload['messages']) && is_array($payload['messages'])) {
            $payload['messages'] = $this->expandAttachments($payload['messages'], $toBlock);

            return $payload;
        }

        foreach ($payload as $index => $message) {
            if (!is_array($message) || empty($message['attachments'])) {
                continue;
            }

            if (!is_array($message['attachments']) || !is_string($message['content'] ?? null)) {
                throw new ClientException('Attachments must be a list of Attachment objects on a message with text content.');
            }

            $blocks = [];
            foreach (array_values($message['attachments']) as $position => $attachment) {
                if (!$attachment instanceof Attachment) {
                    throw new ClientException('Attachments must be a list of Attachment objects.');
                }
                $blocks[] = $toBlock($attachment, $position + 1);
            }
            $blocks[] = ['type' => 'text', 'text' => $message['content']];

            unset($message['attachments']);
            $message['content'] = $blocks;
            $payload[$index] = $message;
        }

        return $payload;
    }

    /**
     * @param array<mixed>|string $payload
     */
    private function hasAttachments(array|string $payload): bool
    {
        if (is_string($payload)) {
            return false;
        }

        $messages = isset($payload['messages']) && is_array($payload['messages']) ? $payload['messages'] : $payload;
        foreach ($messages as $message) {
            if (is_array($message) && !empty($message['attachments'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Failure of a request that carried attachments: exception class and message only, no previous exception.
     */
    private function attachmentFailure(LoggerInterface $logger, string $provider, string $modelId, string $exceptionClass, string $message): ClientException
    {
        $logger->error(sprintf('%s request with attachments failed', $provider), [
            'exception_class' => $exceptionClass,
            'model' => $modelId,
            'error' => mb_substr($message, 0, 500),
        ]);

        return new ClientException(sprintf('%s request failed: %s', $provider, mb_substr($message, 0, 500)));
    }

    /**
     * A text attachment as a delimited text block, for providers that take it as part of the prompt.
     * The tag comes from the content's hash, so the content cannot contain its own closing tag.
     */
    private function attachmentText(Attachment $attachment, int $position): string
    {
        $tag = 'document-' . mb_substr($attachment->sha256, 0, 12);

        return sprintf("<%s name=\"document-%d\" type=\"%s\">\n%s\n</%s>", $tag, $position, $attachment->mimeType, $attachment->bytes(), $tag);
    }

    private function unsupportedAttachment(string $provider, string $modelId, Attachment $attachment): UnsupportedCapabilityException
    {
        return new UnsupportedCapabilityException(sprintf('%s model "%s" does not accept "%s" attachments.', $provider, $modelId, $attachment->mimeType));
    }
}
