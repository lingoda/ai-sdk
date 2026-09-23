<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Decision;

use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\ModelNotFoundException;
use Lingoda\AiSdk\ProviderInterface;

/**
 * Structured decisions (yes/no, choice, score) over a state, e.g. TypeSafe Jev.
 *
 * Not a chat platform: Platform::ask() never routes here. The data sanitizer does not run on the state.
 */
interface DecisionPlatformInterface
{
    /**
     * @param string|array<string, mixed> $state The text or structured data to decide on
     * @param array<string, Question> $questions Keyed by the id the answers come back under
     * @param string|null $model Model id, the platform default when null
     *
     * @throws ModelNotFoundException when the model is not one of the provider's models
     * @throws InvalidArgumentException when no questions are given
     * @throws ClientException when the request fails or the response cannot be read
     */
    public function decide(string|array $state, array $questions, ?string $model = null): DecisionResult;

    /**
     * The provider behind this platform: its id, models and default model.
     */
    public function getProvider(): ProviderInterface;
}
