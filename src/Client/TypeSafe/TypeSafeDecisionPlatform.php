<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Client\TypeSafe;

use Lingoda\AiSdk\Decision\Answer;
use Lingoda\AiSdk\Decision\DecisionPlatformInterface;
use Lingoda\AiSdk\Decision\DecisionResult;
use Lingoda\AiSdk\Decision\Question;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Enum\TypeSafe\DecisionModel;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\ModelNotFoundException;
use Lingoda\AiSdk\Provider\TypeSafeProvider;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\Result\Usage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * TypeSafe Jev decision platform (System One API). The state is never logged.
 */
final readonly class TypeSafeDecisionPlatform implements DecisionPlatformInterface
{
    private string $baseUrl;
    private TypeSafeProvider $provider;

    /**
     * @param string $baseUrl Base URL of a TypeSafe-compatible endpoint (override for WireMock)
     *
     * @throws ModelNotFoundException when the default model is not a Jev model
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter]
        private string $apiKey,
        ?string $defaultModel = null,
        string $baseUrl = 'https://api.typesafe.ai',
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->baseUrl = mb_rtrim($baseUrl, '/');
        $this->provider = new TypeSafeProvider();
        // Fails here, not on the first decide(), when the default model is unknown
        $this->provider->setDefaultModel($this->provider->getModel($defaultModel ?? DecisionModel::JEV_1_13_0->value)->getId());
    }

    public function getProvider(): ProviderInterface
    {
        return $this->provider;
    }

    public function decide(string|array $state, array $questions, ?string $model = null): DecisionResult
    {
        if ($questions === []) {
            throw new InvalidArgumentException('A decision needs at least one question.');
        }

        // Unknown model ids fail before any request
        $model = $this->provider->getModel($model ?? $this->provider->getDefaultModel())->getId();

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . '/v1/systemone', [
                'auth_bearer' => $this->apiKey,
                'json' => [
                    'model' => $model,
                    'state' => $state,
                    'questions' => (object) array_map(static fn (Question $question): array => $question->toArray(), $questions),
                ],
            ]);
            $status = $response->getStatusCode();
        } catch (HttpClientExceptionInterface $e) {
            throw $this->failure($model, $e::class, $e->getMessage());
        }

        try {
            $data = $response->toArray(false);
        } catch (HttpClientExceptionInterface $e) {
            // Gateways answer 502/503/504 with HTML: keep the status code, it is what callers retry on
            throw $status !== 200
                ? $this->failure($model, 'HTTP ' . $status, 'no error detail', $status)
                : $this->failure($model, 'invalid response', $e->getMessage());
        }

        if ($status !== 200) {
            throw $this->failure($model, 'HTTP ' . $status, $this->errorMessage($data) ?? 'no error detail', $status);
        }

        $answersData = $data['answers'] ?? null;
        if (!is_array($answersData)) {
            throw $this->failure($model, 'invalid response', 'Response does not contain answers.');
        }

        try {
            $answers = [];
            foreach ($answersData as $id => $answer) {
                $answers[(string) $id] = Answer::fromArray((string) $id, is_array($answer) ? $answer : []);
            }
        } catch (InvalidArgumentException $e) {
            throw $this->failure($model, 'invalid response', $e->getMessage());
        }

        $missing = array_diff(array_map(strval(...), array_keys($questions)), array_keys($answers));
        if ($missing !== []) {
            throw $this->failure($model, 'invalid response', sprintf('No answer for question(s): %s', implode(', ', $missing)));
        }

        return (new DecisionResult($answers, [
            'model' => is_string($data['model'] ?? null) ? $data['model'] : $model,
            'provider' => AIProvider::TYPESAFE->value,
        ]))->withUsage($this->usage($data));
    }

    /**
     * The remote error detail goes on the exception only, not into the log.
     */
    private function failure(string $model, string $kind, string $message, int $code = 0): ClientException
    {
        $this->logger->error('TypeSafe decision request failed', [
            'model' => $model,
            'kind' => $kind,
            'code' => $code,
        ]);

        return new ClientException(sprintf('TypeSafe request failed (%s): %s', $kind, $message), $code);
    }

    /**
     * TypeSafe nests errors under "detail": a string, {message}, or a list of {loc, msg} validation violations.
     *
     * @param array<mixed> $data
     */
    private function errorMessage(array $data): ?string
    {
        $detail = $data['detail'] ?? null;

        if (is_string($detail)) {
            return $detail;
        }

        if (!is_array($detail)) {
            return null;
        }

        if (is_string($detail['message'] ?? null)) {
            return $detail['message'];
        }

        $violations = [];
        foreach ($detail as $violation) {
            if (is_array($violation) && is_array($violation['loc'] ?? null) && is_string($violation['msg'] ?? null)) {
                $violations[] = sprintf('%s: %s', implode('.', array_map(static fn (mixed $part): string => is_scalar($part) ? (string) $part : '?', $violation['loc'])), $violation['msg']);
            }
        }

        return $violations !== [] ? implode('; ', $violations) : null;
    }

    /**
     * Jev bills input tokens only; output tokens are reported when present.
     *
     * @param array<mixed> $data
     */
    private function usage(array $data): ?Usage
    {
        $usage = $data['usage'] ?? null;
        $input = is_array($usage) && is_int($usage['input_tokens'] ?? null) ? $usage['input_tokens'] : null;
        if ($input === null) {
            return null;
        }

        $output = is_int($usage['output_tokens'] ?? null) ? $usage['output_tokens'] : 0;

        return new Usage(promptTokens: $input, completionTokens: $output, totalTokens: $input + $output);
    }
}
