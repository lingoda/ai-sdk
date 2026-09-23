<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Client\Bedrock\Contract;

use Symfony\AI\Platform\Bridge\Bedrock\Nova\Nova;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;

/**
 * Copy of the final Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\UserMessageNormalizer (0.13.0)
 * plus a Document branch, which upstream lacks (it throws on PDFs).
 *
 * Delete this class once upstream supports Document: NovaUserMessageNormalizerTest fails when it does.
 *
 * @internal Tied to Symfony AI (0.x) internals
 */
final class NovaUserMessageNormalizer extends ModelContractNormalizer
{
    private const array DOCUMENT_FORMATS = [
        'application/pdf' => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ];

    /**
     * @param UserMessage $data
     * @param array<string, mixed> $context
     *
     * @return array{
     *     role: 'user',
     *     content: list<array{
     *         text?: string,
     *         image?: array{format: string, source: array{bytes: string}},
     *         document?: array{format: string, name: string, source: array{bytes: string}}
     *     }>
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $content = [];

        $documentIndex = 0;
        foreach ($data->getContent() as $value) {
            if ($value instanceof Text) {
                $content[] = ['text' => $value->getText()];
            } elseif ($value instanceof Image) {
                $content[] = ['image' => [
                    'format' => str_replace('jpg', 'jpeg', str_replace('image/', '', $value->getFormat())),
                    'source' => ['bytes' => $value->asBase64()],
                ]];
            } elseif ($value instanceof Document && isset(self::DOCUMENT_FORMATS[$value->getFormat()])) {
                $content[] = ['document' => [
                    'format' => self::DOCUMENT_FORMATS[$value->getFormat()],
                    // Generated name (document-N by position, set by BedrockClient), never the user's filename
                    'name' => $value->getFilename() ?? 'document-' . ++$documentIndex,
                    'source' => ['bytes' => $value->asBase64()],
                ]];
            } else {
                throw new RuntimeException('Unsupported message type.');
            }
        }

        return ['role' => 'user', 'content' => $content];
    }

    protected function supportedDataClass(): string
    {
        return UserMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Nova;
    }
}
