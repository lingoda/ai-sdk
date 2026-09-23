<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Client\Bedrock;

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use Lingoda\AiSdk\Client\Bedrock\Contract\NovaUserMessageNormalizer;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\RuntimeException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\AI\Platform\Bridge\Bedrock\Factory;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\AssistantMessageNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\MessageBagNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\ToolCallMessageNormalizer;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract\ToolNormalizer;
use Symfony\AI\Platform\Contract;

/**
 * Requires the optional packages symfony/ai-bedrock-platform (~0.13.0) and async-aws/bedrock-runtime.
 *
 * Pass a runtime client that builds its own HTTP client: async-aws only adds its retries (429, 5xx, throttling) then.
 */
final class BedrockClientFactory
{
    /**
     * @throws RuntimeException when the optional Bedrock packages are not installed
     * @throws InvalidArgumentException when no region is configured or it is not an eu-/us- region
     */
    public static function createClient(
        BedrockRuntimeClient $runtimeClient,
        LoggerInterface $logger = new NullLogger(),
    ): BedrockClient {
        // Only reachable without the bridge installed
        // @codeCoverageIgnoreStart
        if (!class_exists(Factory::class)) {
            throw new RuntimeException('The Bedrock provider requires symfony/ai-bedrock-platform. Run "composer require symfony/ai-bedrock-platform:~0.13.0 async-aws/bedrock-runtime".');
        }
        // @codeCoverageIgnoreEnd

        // Refuse async-aws's implicit us-east-1 fallback
        if ($runtimeClient->getConfiguration()->isDefault('region')) {
            throw new InvalidArgumentException('Configure the Bedrock runtime client region explicitly (e.g. eu-west-1); the us-east-1 fallback is refused.');
        }

        // One platform per API format; the Converse one uses our Nova normalizer for PDF support
        $anthropicMessagesPlatform = Factory::createPlatform($runtimeClient);
        $conversePlatform = Factory::createPlatform($runtimeClient, contract: Contract::create([
            new NovaUserMessageNormalizer(),
            new AssistantMessageNormalizer(),
            new MessageBagNormalizer(),
            new ToolCallMessageNormalizer(),
            new ToolNormalizer(),
        ]));

        return new BedrockClient(
            $anthropicMessagesPlatform,
            $conversePlatform,
            (string) $runtimeClient->getConfiguration()->get('region'),
            $logger,
        );
    }
}
