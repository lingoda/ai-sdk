<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Client\Bedrock;

use AsyncAws\Core\Exception\Http\HttpException;
use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Enum\Bedrock\ApiFormat;
use Lingoda\AiSdk\Enum\Bedrock\ChatModel;
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
    private const array REGION_PREFIXES = ['eu', 'us'];
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
        $prefix = RegionMapper::map($region);
        if (!in_array($prefix, self::REGION_PREFIXES, true)) {
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
        $messages = $this->buildMessageBag($payload, $chatModel);
        $platform = match ($chatModel->apiFormat()) {
            ApiFormat::ANTHROPIC_MESSAGES => $this->anthropicMessagesPlatform,
            ApiFormat::CONVERSE => $this->conversePlatform,
        };

        try {
            $deferred = $platform->invoke($chatModel->catalogName(), $messages, $this->filterOptions($model, $chatModel, $options));
            $result = $deferred->getResult();
            $data = $deferred->getRawResult()->getData();

            $metadata = [
                'model' => $this->regionPrefix . '.' . $chatModel->value,
                'provider' => AIProvider::BEDROCK->value,
                'stop_reason' => $data['stop_reason'] ?? $data['stopReason'] ?? null,
            ];

            return $this->convertResult($result, $metadata)->withUsage($this->extractUsage($chatModel, $data));
        } catch (\Throwable $e) {
            [$context, $reason, $code] = $this->describeFailure($model, $e);

            throw $this->failure($context, $reason, $code);
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
                    if (!$hasUser && $chatModel->requiresLeadingUserTurn()) {
                        throw new UnsupportedCapabilityException(sprintf('Bedrock model "%s" requires the conversation to start with the user message; assistant prompts before it are not supported.', $chatModel->value));
                    }
                    $bag->add(Message::ofAssistant($message['content']));
                    break;
                case 'user':
                    // Documents before the text
                    $bag->add(Message::ofUser(...[...$this->toContent($message['attachments'] ?? []), $message['content']]));
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
     *
     * @return list<ContentInterface>
     */
    private function toContent(mixed $attachments): array
    {
        if (!is_array($attachments)) {
            throw new ClientException('Attachments must be a list of Attachment objects.');
        }

        $content = [];
        foreach ($attachments as $attachment) {
            if (!$attachment instanceof Attachment) {
                throw new ClientException('Attachments must be a list of Attachment objects.');
            }

            $content[] = $attachment->isImage()
                ? new Image($attachment->bytes(), $attachment->mimeType)
                : new Document($attachment->bytes(), $attachment->mimeType);
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

        if (isset($options['response_format']) && !$chatModel->supportsResponseFormat()) {
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
        $allowed = $chatModel->supportsResponseFormat() ? [...self::OPTIONS, 'response_format'] : self::OPTIONS;

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

        throw new ClientException(sprintf('Unsupported Bedrock result type "%s".', $result::class));
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

        return match ($chatModel->apiFormat()) {
            ApiFormat::ANTHROPIC_MESSAGES => (new AnthropicUsageExtractor())->extract($usage),
            ApiFormat::CONVERSE => (new NovaUsageExtractor())->extract($usage),
        };
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
     * HTTP status (0 when there is none) as the code, no previous exception.
     *
     * @param array<string, mixed> $context
     */
    private function failure(array $context, string $reason, int $code): ClientException
    {
        $this->logger->error('Bedrock request failed', $context);

        return new ClientException(sprintf('Bedrock request failed: %s', mb_substr($reason, 0, 500)), $code);
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
}
