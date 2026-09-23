<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Client\Bedrock;

use AsyncAws\Core\Exception\Http\HttpException;
use Lingoda\AiSdk\Client\AttachmentBlocksTrait;
use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Enum\Bedrock\ChatModel;
use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\UnsupportedCapabilityException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Prompt\Attachment;
use Lingoda\AiSdk\Provider\BedrockProvider;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\TextResult;
use Lingoda\AiSdk\Result\ToolCall;
use Lingoda\AiSdk\Result\ToolCallResult;
use Lingoda\AiSdk\Result\Usage;
use Lingoda\AiSdk\Usage\Anthropic\AnthropicUsageExtractor;
use Lingoda\AiSdk\Usage\Bedrock\NovaUsageExtractor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Bridge\Bedrock\RegionMapper;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface as SymfonyPlatformInterface;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ResultInterface as SymfonyResultInterface;
use Symfony\AI\Platform\Result\TextResult as SymfonyTextResult;
use Symfony\AI\Platform\Result\ToolCall as SymfonyToolCall;
use Symfony\AI\Platform\Result\ToolCallResult as SymfonyToolCallResult;

/**
 * AWS Bedrock chat client (Nova, Claude) using the region's cross-region inference profile (eu. or us.).
 * Create it with BedrockClientFactory.
 */
final class BedrockClient implements ClientInterface
{
    use AttachmentBlocksTrait;

    private const array OPTIONS = ['temperature', 'max_tokens'];

    private readonly string $regionPrefix;
    private readonly BedrockProvider $provider;

    /**
     * @internal Takes Symfony AI (0.x) types, so it may change with any Symfony AI bump. Use BedrockClientFactory::createClient().
     *
     * @throws InvalidArgumentException when the region is not an eu- or us- region
     */
    public function __construct(
        private readonly SymfonyPlatformInterface $anthropicMessagesPlatform,
        private readonly SymfonyPlatformInterface $conversePlatform,
        string $region,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        // Standard eu-/us- regions only: us-gov-* and eusc-* use other inference profiles
        $prefix = RegionMapper::map($region);
        if (preg_match('/^(eu|us)-(?!gov-)[a-z]+-\d+$/', $region) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Bedrock region "%s" is not supported. Use an eu- or us- region.',
                $region
            ));
        }

        $this->regionPrefix = $prefix;
        $this->provider = new BedrockProvider();
    }

    public function supports(ModelInterface $model): bool
    {
        return $model->getProvider()->is(AIProvider::BEDROCK);
    }

    public function request(ModelInterface $model, array|string $payload, array $options = []): ResultInterface
    {
        $chatModel = ChatModel::tryFrom($model->getId());
        if ($chatModel === null) {
            throw new UnsupportedCapabilityException(sprintf('Model "%s" is not a Bedrock chat model.', $model->getId()));
        }

        $this->rejectMeaningfulOptions($chatModel, $options);
        $hasAttachments = $this->hasAttachments($payload);
        $messages = $this->buildMessageBag($payload, $chatModel);
        $platform = $this->isConverse($chatModel) ? $this->conversePlatform : $this->anthropicMessagesPlatform;

        try {
            $deferred = $platform->invoke($this->catalogName($chatModel), $messages, $this->filterOptions($model, $chatModel, $options));
            $data = $deferred->getRawResult()->getData();
            $stopReason = $data['stop_reason'] ?? $data['stopReason'] ?? null;
            if ($stopReason === 'refusal') {
                throw new ClientException('The model refused the request (stop_reason: refusal).');
            }
            $result = $deferred->getResult();

            $metadata = [
                'model' => $this->regionPrefix . '.' . $chatModel->value,
                'provider' => AIProvider::BEDROCK->value,
                'stop_reason' => $stopReason,
            ];

            return $this->convertResult($result, $metadata)->withUsage($this->extractUsage($chatModel, $data));
        } catch (ClientException $e) {
            throw $e; // refusal or no text: already a clear, payload-free message
        } catch (\Throwable $e) {
            [$context, $reason, $code] = $this->describeFailure($model, $e);

            // Requests with attachments keep no previous exception: its frames hold the document
            throw $this->failure($context, $reason, $code, $hasAttachments ? null : $e);
        }
    }

    public function getProvider(): ProviderInterface
    {
        return $this->provider;
    }

    /**
     * @param array<mixed>|string $payload
     *
     * @throws ClientException
     * @throws UnsupportedCapabilityException when a Nova conversation does not open with the user turn
     */
    private function buildMessageBag(array|string $payload, ChatModel $chatModel): MessageBag
    {
        if (is_string($payload)) {
            return new MessageBag(Message::ofUser($payload));
        }

        $bag = new MessageBag();
        $hasUser = false;

        foreach ($payload as $message) {
            if (!is_array($message) || !isset($message['role'], $message['content'])
                || !is_string($message['role']) || !is_string($message['content'])) {
                throw new ClientException('Bedrock payload must be a list of {role, content} messages.');
            }

            switch ($message['role']) {
                case 'system':
                    if (mb_trim($message['content']) !== '') {
                        $bag->add(Message::forSystem($message['content']));
                    }
                    break;
                case 'assistant':
                    if (!$hasUser && $this->isConverse($chatModel)) {
                        throw new UnsupportedCapabilityException(sprintf('Bedrock model "%s" requires the conversation to start with the user message; assistant prompts before it are not supported.', $chatModel->value));
                    }
                    $bag->add(Message::ofAssistant($message['content']));
                    break;
                case 'user':
                    // Documents before the text
                    $bag->add(Message::ofUser(...[...$this->toContent($message['attachments'] ?? [], $chatModel), $message['content']]));
                    $hasUser = true;
                    break;
                default:
                    throw new ClientException(sprintf('Unsupported message role "%s" for Bedrock.', $message['role']));
            }
        }

        if (!$hasUser) {
            throw new ClientException('Bedrock payload must contain a user message.');
        }

        return $bag;
    }

    /**
     * @throws ClientException
     * @throws UnsupportedCapabilityException for a document type the model does not read
     *
     * @return list<ContentInterface>
     */
    private function toContent(mixed $attachments, ChatModel $chatModel): array
    {
        if (!is_array($attachments)) {
            throw new ClientException('Attachments must be a list of Attachment objects.');
        }

        $content = [];
        foreach (array_values($attachments) as $index => $attachment) {
            if (!$attachment instanceof Attachment) {
                throw new ClientException('Attachments must be a list of Attachment objects.');
            }

            $content[] = match (true) {
                $attachment->isImage() => new Image($attachment->bytes(), $attachment->mimeType),
                $attachment->isText() => new Text($this->attachmentText($attachment, $index + 1)),
                // The path only carries the generated name (document-N) to the Nova normalizer
                $this->acceptsDocument($chatModel, $attachment->mimeType) => new Document($attachment->bytes(), $attachment->mimeType, sprintf('document-%d', $index + 1)),
                default => throw $this->unsupportedAttachment('Bedrock', $chatModel->value, $attachment),
            };
        }

        return $content;
    }

    /**
     * Rejects options the model does not support and that would change the result.
     *
     * @param array<string, mixed> $options
     *
     * @throws UnsupportedCapabilityException
     */
    private function rejectMeaningfulOptions(ChatModel $chatModel, array $options): void
    {
        if (isset($options['tools'])) {
            throw new UnsupportedCapabilityException(sprintf('Tools are not supported for Bedrock model "%s" yet.', $chatModel->value));
        }

        if (isset($options['response_format']) && !$this->supportsResponseFormat($chatModel)) {
            throw new UnsupportedCapabilityException(sprintf('response_format is not supported for Bedrock model "%s".', $chatModel->value));
        }
    }

    /**
     * Keeps only the options Bedrock accepts for the model.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function filterOptions(ModelInterface $model, ChatModel $chatModel, array $options): array
    {
        $merged = array_merge($model->getOptions(), $options);
        $allowed = $this->supportsResponseFormat($chatModel) ? [...self::OPTIONS, 'response_format'] : self::OPTIONS;
        if (!$this->supportsTemperature($chatModel)) {
            $allowed = array_values(array_diff($allowed, ['temperature']));
        }

        $dropped = array_values(array_diff(array_keys($merged), $allowed));
        if ($dropped !== []) {
            $this->logger->debug('Dropped options not supported by Bedrock', [
                'model' => $chatModel->value,
                'options' => $dropped,
            ]);
        }

        return array_intersect_key($merged, array_flip($allowed));
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @throws ClientException
     * @throws InvalidArgumentException
     */
    private function convertResult(SymfonyResultInterface $result, array $metadata): TextResult|ToolCallResult
    {
        if ($result instanceof MultiPartResult) {
            $result = $result->asToolCallResult() ?? new SymfonyTextResult($result->asText());
        }

        if ($result instanceof SymfonyTextResult) {
            return new TextResult($result->getContent(), $metadata);
        }

        if ($result instanceof SymfonyToolCallResult) {
            return new ToolCallResult($metadata, ...array_map(
                static fn (SymfonyToolCall $toolCall): ToolCall => new ToolCall($toolCall->getId(), $toolCall->getName(), $toolCall->getArguments()),
                $result->getContent()
            ));
        }

        // e.g. only a thinking block, when max_tokens ran out before any text
        throw new ClientException(sprintf('No text in the Bedrock response (stop_reason: %s).', is_string($metadata['stop_reason'] ?? null) ? $metadata['stop_reason'] : 'unknown'));
    }

    /**
     * @param array<mixed> $data
     */
    private function extractUsage(ChatModel $chatModel, array $data): ?Usage
    {
        $rawUsage = $data['usage'] ?? null;
        if (!is_array($rawUsage)) {
            return null;
        }

        // Token counts only
        $usage = [];
        foreach ($rawUsage as $key => $value) {
            if (is_string($key) && is_int($value)) {
                $usage[$key] = $value;
            }
        }

        return $this->isConverse($chatModel)
            ? (new NovaUsageExtractor())->extract($usage)
            : (new AnthropicUsageExtractor())->extract($usage);
    }

    /**
     * @return array{array<string, mixed>, string, int} log context, reason, HTTP status (0 when there is none)
     */
    private function describeFailure(ModelInterface $model, \Throwable $e): array
    {
        $context = [
            'model' => $model->getId(),
            'exception_class' => $e::class,
            'origin' => $e->getFile() . ':' . $e->getLine(),
        ];

        if (!$e instanceof HttpException) {
            return [$context, sprintf('%s: %s', (new \ReflectionClass($e))->getShortName(), $e->getMessage()), 0];
        }

        $code = $this->httpStatus($e);
        $context['http_status'] = $code;
        $context['aws_code'] = $e->getAwsCode();
        $context['request_id'] = $this->requestId($e);

        return [$context, sprintf('%s: %s', $e->getAwsCode() ?? 'HTTP error', $e->getAwsMessage() ?? ''), $code];
    }

    /**
     * HTTP status (0 when there is none) as the code.
     *
     * @param array<string, mixed> $context
     */
    private function failure(array $context, string $reason, int $code, ?\Throwable $previous = null): ClientException
    {
        $this->logger->error('Bedrock request failed', $context);

        return new ClientException(sprintf('Bedrock request failed: %s', mb_substr($reason, 0, 500)), $code, $previous);
    }

    private function httpStatus(HttpException $e): int
    {
        // getInfo() never throws, unlike getStatusCode()
        $status = $e->getResponse()->getInfo('http_code');

        return is_int($status) ? $status : 0;
    }

    private function requestId(HttpException $e): ?string
    {
        $headers = $e->getResponse()->getInfo('response_headers');
        foreach (is_array($headers) ? $headers : [] as $line) {
            if (is_string($line) && mb_stripos($line, 'x-amzn-requestid:') === 0) {
                return mb_trim(mb_substr($line, mb_strlen('x-amzn-requestid:')));
            }
        }

        return null;
    }

    /**
     * Nova speaks the Converse body shape and needs the user turn first; Claude speaks Anthropic Messages.
     */
    private function isConverse(ChatModel $chatModel): bool
    {
        return match ($chatModel) {
            ChatModel::NOVA_MICRO, ChatModel::NOVA_LITE, ChatModel::NOVA_PRO, ChatModel::NOVA_2_LITE => true,
            ChatModel::CLAUDE_HAIKU_45, ChatModel::CLAUDE_SONNET_45, ChatModel::CLAUDE_OPUS_45, ChatModel::CLAUDE_SONNET_46,
            ChatModel::CLAUDE_OPUS_46, ChatModel::CLAUDE_OPUS_47, ChatModel::CLAUDE_OPUS_48, ChatModel::CLAUDE_SONNET_5,
            ChatModel::CLAUDE_OPUS_5, ChatModel::CLAUDE_OPUS_55 => false,
        };
    }

    /**
     * Model name in the Symfony AI Bedrock model catalog.
     *
     * @return non-empty-string
     */
    private function catalogName(ChatModel $chatModel): string
    {
        return match ($chatModel) {
            ChatModel::NOVA_MICRO => 'nova-micro',
            ChatModel::NOVA_LITE => 'nova-lite',
            ChatModel::NOVA_PRO => 'nova-pro',
            ChatModel::NOVA_2_LITE => 'nova-2-lite',
            ChatModel::CLAUDE_HAIKU_45 => 'claude-haiku-4-5-20251001',
            ChatModel::CLAUDE_SONNET_45 => 'claude-sonnet-4-5-20250929',
            ChatModel::CLAUDE_OPUS_45 => 'claude-opus-4-5-20251101',
            ChatModel::CLAUDE_SONNET_46 => 'claude-sonnet-4-6',
            ChatModel::CLAUDE_OPUS_46 => 'claude-opus-4-6',
            ChatModel::CLAUDE_OPUS_47 => 'claude-opus-4-7',
            ChatModel::CLAUDE_OPUS_48 => 'claude-opus-4-8',
            ChatModel::CLAUDE_SONNET_5 => 'claude-sonnet-5',
            ChatModel::CLAUDE_OPUS_5 => 'claude-opus-5',
            ChatModel::CLAUDE_OPUS_55 => 'claude-opus-5-5',
        };
    }

    private function supportsResponseFormat(ChatModel $chatModel): bool
    {
        return match ($chatModel) {
            ChatModel::CLAUDE_HAIKU_45, ChatModel::CLAUDE_SONNET_45, ChatModel::CLAUDE_OPUS_45, ChatModel::CLAUDE_SONNET_46,
            ChatModel::CLAUDE_OPUS_46 => true,
            ChatModel::NOVA_MICRO, ChatModel::NOVA_LITE, ChatModel::NOVA_PRO, ChatModel::NOVA_2_LITE,
            ChatModel::CLAUDE_OPUS_47, ChatModel::CLAUDE_OPUS_48, ChatModel::CLAUDE_SONNET_5, ChatModel::CLAUDE_OPUS_5,
            ChatModel::CLAUDE_OPUS_55 => false,
        };
    }

    private function supportsTemperature(ChatModel $chatModel): bool
    {
        return match ($chatModel) {
            ChatModel::CLAUDE_OPUS_47, ChatModel::CLAUDE_OPUS_48, ChatModel::CLAUDE_SONNET_5, ChatModel::CLAUDE_OPUS_5,
            ChatModel::CLAUDE_OPUS_55 => false,
            ChatModel::NOVA_MICRO, ChatModel::NOVA_LITE, ChatModel::NOVA_PRO, ChatModel::NOVA_2_LITE, ChatModel::CLAUDE_HAIKU_45,
            ChatModel::CLAUDE_SONNET_45, ChatModel::CLAUDE_OPUS_45, ChatModel::CLAUDE_SONNET_46, ChatModel::CLAUDE_OPUS_46 => true,
        };
    }

    /**
     * Document types (PDF, DOCX) the model reads; text and image attachments are handled separately.
     */
    private function acceptsDocument(ChatModel $chatModel, string $mimeType): bool
    {
        if (!$chatModel->hasCapability(Capability::DOCUMENT)) {
            return false;
        }

        return $this->isConverse($chatModel)
            ? in_array($mimeType, [Attachment::PDF, Attachment::DOCX], true)
            : $mimeType === Attachment::PDF;
    }
}
