<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Client\TypeSafe;

use Lingoda\AiSdk\Client\TypeSafe\TypeSafeDecisionPlatform;
use Lingoda\AiSdk\Decision\Question;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\Exception\ModelNotFoundException;
use Lingoda\AiSdk\Tests\Unit\Security\TestLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TypeSafeDecisionPlatformTest extends TestCase
{
    private const string API_KEY = 'test-api-key';
    private const string STATE = 'Student wrote: STATE_MARKER_4711';

    private const array SUCCESS_BODY = [
        'model' => 'jev-1.13.0',
        'answers' => [
            'on_topic' => ['type' => 'noul', 'noul' => 0.91],
            'color' => [
                'type' => 'choice',
                'choice' => 'green',
                'probabilities' => ['green' => 0.8, 'red' => 0.2],
                'confidence' => 0.8,
            ],
            'level' => [
                'type' => 'score',
                'score' => 2.4,
                'legend' => ['low', 'mid', 'high'],
                'probabilities' => ['0' => 0.1, '1' => 0.4, '2' => 0.5],
                'confidence' => 0.5,
            ],
        ],
        'usage' => ['input_tokens' => 120],
    ];

    /** @var array{method: string, url: string, options: array<string, mixed>}|null */
    private ?array $request = null;

    public function testSendsRequestAndParsesAllAnswerTypes(): void
    {
        $platform = $this->platform(new MockResponse(json_encode(self::SUCCESS_BODY, JSON_THROW_ON_ERROR)));

        $result = $platform->decide(self::STATE, [
            'on_topic' => Question::noul('Is it on topic?'),
            'color' => Question::choice('Which color?', ['green' => 'Green', 'red' => 'Red']),
            'level' => Question::score('Level?', ['low', 'mid', 'high']),
        ]);

        $this->assertNotNull($this->request);
        $this->assertSame('POST', $this->request['method']);
        $this->assertSame('https://api.typesafe.ai/v1/systemone', $this->request['url']);
        $this->assertContains('Authorization: Bearer ' . self::API_KEY, $this->headers());
        $this->assertSame([
            'model' => 'jev-1.13.0',
            'state' => self::STATE,
            'questions' => [
                'on_topic' => ['type' => 'noul', 'instructions' => 'Is it on topic?'],
                'color' => ['type' => 'choice', 'instructions' => 'Which color?', 'criteria' => ['green' => 'Green', 'red' => 'Red']],
                'level' => ['type' => 'score', 'instructions' => 'Level?', 'criteria' => ['low', 'mid', 'high']],
            ],
        ], json_decode($this->body(), true, 512, JSON_THROW_ON_ERROR));

        $onTopic = $result->getAnswer('on_topic');
        $this->assertSame('noul', $onTopic->type);
        $this->assertSame(0.91, $onTopic->probability);
        $this->assertTrue($onTopic->isTrue());

        $color = $result->getAnswer('color');
        $this->assertSame('green', $color->choice);
        $this->assertSame(['green' => 0.8, 'red' => 0.2], $color->probabilities);
        $this->assertSame(0.8, $color->confidence);

        $level = $result->getAnswer('level');
        $this->assertSame(2.4, $level->score);
        $this->assertSame(['low', 'mid', 'high'], $level->legend);
        $this->assertSame(['0' => 0.1, '1' => 0.4, '2' => 0.5], $level->probabilities);
        $this->assertSame(0.5, $level->confidence);

        $this->assertCount(3, $result->getContent());

        $usage = $result->getUsage();
        $this->assertNotNull($usage);
        $this->assertSame(120, $usage->promptTokens);
        $this->assertSame(0, $usage->completionTokens);
        $this->assertSame(120, $usage->totalTokens);

        $this->assertSame('jev-1.13.0', $result->getMetadata()['model']);
        $this->assertSame('typesafe', $result->getMetadata()['provider']);
    }

    public function testNumericChoiceKeysAreSentAsJsonObject(): void
    {
        $platform = $this->platform(new MockResponse(json_encode(self::SUCCESS_BODY, JSON_THROW_ON_ERROR)));

        $platform->decide(['text' => 'hello'], ['color' => Question::choice('Pick', [0 => 'a', 1 => 'b'])]);

        $this->assertStringContainsString('"questions":{"color":{"type":"choice","instructions":"Pick","criteria":{"0":"a","1":"b"}}}', $this->body());
        $this->assertStringContainsString('"state":{"text":"hello"}', $this->body());
    }

    public function testNoulWithoutCriteriaHasNoCriteriaKey(): void
    {
        $platform = $this->platform(new MockResponse(json_encode(self::SUCCESS_BODY, JSON_THROW_ON_ERROR)));

        $platform->decide('state', ['on_topic' => Question::noul('On topic?')]);

        $this->assertStringContainsString('"questions":{"on_topic":{"type":"noul","instructions":"On topic?"}}', $this->body());
    }

    public function testCustomDefaultModelAndBaseUrlWithTrailingSlash(): void
    {
        $platform = $this->platform(
            new MockResponse(json_encode(['answers' => ['q' => ['type' => 'noul', 'noul' => 0.5]]], JSON_THROW_ON_ERROR)),
            defaultModel: 'jev-preview',
            baseUrl: 'http://wiremock:8080/'
        );

        $result = $platform->decide('state', ['q' => Question::noul('On topic?')]);

        $this->assertNotNull($this->request);
        $this->assertSame('http://wiremock:8080/v1/systemone', $this->request['url']);
        $this->assertStringContainsString('"model":"jev-preview"', $this->body());
        $this->assertSame('jev-preview', $result->getMetadata()['model']);
        $this->assertNull($result->getUsage());
    }

    public function testExplicitModelOverridesDefault(): void
    {
        $platform = $this->platform(new MockResponse(json_encode(self::SUCCESS_BODY, JSON_THROW_ON_ERROR)));

        $platform->decide('state', ['on_topic' => Question::noul('On topic?')], 'jev-latest');

        $this->assertStringContainsString('"model":"jev-latest"', $this->body());
    }

    public function testUnknownModelIsRejectedWithoutRequest(): void
    {
        $platform = $this->platform(new MockResponse('{}'));

        try {
            $platform->decide('state', ['on_topic' => Question::noul('On topic?')], 'jev-9.9.9');
            $this->fail('Expected ModelNotFoundException');
        } catch (ModelNotFoundException) {
        }

        $this->assertNull($this->request);
    }

    public function testProviderIsTypeSafe(): void
    {
        $provider = $this->platform(new MockResponse('{}'))->getProvider();

        $this->assertSame('typesafe', $provider->getId());
        $this->assertSame('jev-1.13.0', $provider->getDefaultModel());
    }

    /**
     * @return iterable<string, array{int, array<mixed>, string}>
     */
    public static function errorResponses(): iterable
    {
        yield '400 string detail' => [400, ['detail' => 'Bad request'], 'Bad request'];
        yield '401 invalid key' => [401, ['detail' => 'Invalid API key'], 'Invalid API key'];
        yield '422 validation list' => [
            422,
            ['detail' => [['loc' => ['body', 'questions', 'x'], 'msg' => 'field required']]],
            'body.questions.x: field required',
        ];
        yield '429 message object' => [429, ['detail' => ['message' => 'Too many requests']], 'Too many requests'];
        yield '529 overloaded' => [529, ['detail' => 'Overloaded'], 'Overloaded'];
        yield '500 without detail' => [500, ['error' => 'x'], 'no error detail'];
    }

    /**
     * @param array<mixed> $body
     */
    #[DataProvider('errorResponses')]
    public function testErrorResponsesBecomeClientException(int $status, array $body, string $expectedMessage): void
    {
        $platform = $this->platform(new MockResponse(json_encode($body, JSON_THROW_ON_ERROR), ['http_code' => $status]));

        try {
            $platform->decide('state', ['q' => Question::noul('On topic?')]);
            $this->fail('Expected ClientException');
        } catch (ClientException $e) {
            $this->assertSame($status, $e->getCode());
            $this->assertStringContainsString('HTTP ' . $status, $e->getMessage());
            $this->assertStringContainsString($expectedMessage, $e->getMessage());
        }
    }

    public function testTransportErrorBecomesClientExceptionWithoutPrevious(): void
    {
        $platform = $this->platform(new MockResponse('', ['error' => 'Connection refused']));

        try {
            $platform->decide('state', ['q' => Question::noul('On topic?')]);
            $this->fail('Expected ClientException');
        } catch (ClientException $e) {
            $this->assertNull($e->getPrevious());
            $this->assertStringContainsString('Connection refused', $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function nonJsonErrorProvider(): iterable
    {
        yield '503 html' => [503, '<html><body>Service Unavailable</body></html>'];
        yield '502 empty' => [502, ''];
    }

    #[DataProvider('nonJsonErrorProvider')]
    public function testNonJsonErrorBodyKeepsTheStatusCode(int $status, string $body): void
    {
        $platform = $this->platform(new MockResponse($body, ['http_code' => $status]));

        try {
            $platform->decide('state', ['q' => Question::noul('On topic?')]);
            $this->fail('Expected ClientException');
        } catch (ClientException $e) {
            $this->assertSame($status, $e->getCode());
            $this->assertStringContainsString('HTTP ' . $status, $e->getMessage());
        }
    }

    public function testNonJsonSuccessBodyBecomesClientException(): void
    {
        $platform = $this->platform(new MockResponse('not json'));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('invalid response');

        $platform->decide('state', ['q' => Question::noul('On topic?')]);
    }

    public function testUnansweredQuestionBecomesClientException(): void
    {
        $platform = $this->platform(new MockResponse(json_encode(self::SUCCESS_BODY, JSON_THROW_ON_ERROR)));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('No answer for question(s): missing');

        $platform->decide('state', ['on_topic' => Question::noul('On topic?'), 'missing' => Question::noul('Asked but not answered?')]);
    }

    public function testMissingAnswersBecomesClientException(): void
    {
        $platform = $this->platform(new MockResponse(json_encode(['model' => 'jev-1.13.0'], JSON_THROW_ON_ERROR)));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Response does not contain answers.');

        $platform->decide('state', ['q' => Question::noul('On topic?')]);
    }

    public function testMalformedAnswerBecomesClientException(): void
    {
        $platform = $this->platform(new MockResponse(json_encode(
            ['answers' => ['q' => ['type' => 'noul', 'noul' => 'yes']]],
            JSON_THROW_ON_ERROR
        )));

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Answer "q" has no numeric "noul".');

        $platform->decide('state', ['q' => Question::noul('On topic?')]);
    }

    public function testEmptyQuestionsAreRejectedWithoutRequest(): void
    {
        $platform = $this->platform(new MockResponse('{}'));

        try {
            $platform->decide('state', []);
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException) {
            $this->assertNull($this->request);
        }
    }

    public function testStateIsNeverLogged(): void
    {
        $logger = new TestLogger();
        $platform = $this->platform(
            new MockResponse(json_encode(['detail' => 'Internal error'], JSON_THROW_ON_ERROR), ['http_code' => 500]),
            logger: $logger
        );

        try {
            $platform->decide(self::STATE, ['q' => Question::noul('On topic?')]);
            $this->fail('Expected ClientException');
        } catch (ClientException $e) {
            $this->assertStringNotContainsString('STATE_MARKER_4711', $e->getMessage());
        }

        $this->assertTrue($logger->hasError('TypeSafe decision request failed'));
        $this->assertStringNotContainsString('STATE_MARKER_4711', json_encode($logger->records, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString(self::API_KEY, json_encode($logger->records, JSON_THROW_ON_ERROR));
    }

    public function testRemoteErrorDetailStaysOutOfTheLog(): void
    {
        $logger = new TestLogger();
        $platform = $this->platform(
            new MockResponse(json_encode(['detail' => 'echoed DETAIL_MARKER'], JSON_THROW_ON_ERROR), ['http_code' => 400]),
            logger: $logger
        );

        try {
            $platform->decide('state', ['q' => Question::noul('On topic?')]);
            $this->fail('Expected ClientException');
        } catch (ClientException $e) {
            $this->assertStringContainsString('DETAIL_MARKER', $e->getMessage());
        }

        $this->assertStringNotContainsString('DETAIL_MARKER', json_encode($logger->records, JSON_THROW_ON_ERROR));
        $this->assertSame(400, $logger->records[0]['context']['code'] ?? null);
    }

    public function testUnknownDefaultModelFailsAtConstruction(): void
    {
        $this->expectException(ModelNotFoundException::class);

        new TypeSafeDecisionPlatform(new MockHttpClient(), self::API_KEY, 'jev-9.9.9');
    }

    private function platform(
        MockResponse $response,
        ?string $defaultModel = null,
        string $baseUrl = 'https://api.typesafe.ai',
        LoggerInterface $logger = new NullLogger(),
    ): TypeSafeDecisionPlatform {
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $this->request = ['method' => $method, 'url' => $url, 'options' => $options];

            return $response;
        });

        return new TypeSafeDecisionPlatform($httpClient, self::API_KEY, $defaultModel, $baseUrl, $logger);
    }

    private function body(): string
    {
        $this->assertNotNull($this->request);
        $body = $this->request['options']['body'] ?? null;
        $this->assertIsString($body);

        return $body;
    }

    /**
     * @return list<string>
     */
    private function headers(): array
    {
        $this->assertNotNull($this->request);
        $headers = $this->request['options']['headers'] ?? [];
        $this->assertIsArray($headers);

        return array_values(array_filter($headers, 'is_string'));
    }
}
