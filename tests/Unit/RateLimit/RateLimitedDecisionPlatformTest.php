<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\Decision\DecisionPlatformInterface;
use Lingoda\AiSdk\Decision\DecisionResult;
use Lingoda\AiSdk\Decision\Question;
use Lingoda\AiSdk\Enum\TypeSafe\DecisionModel;
use Lingoda\AiSdk\Exception\ClientException;
use Lingoda\AiSdk\Exception\ModelNotFoundException;
use Lingoda\AiSdk\Exception\RateLimitExceededException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\Provider\TypeSafeProvider;
use Lingoda\AiSdk\RateLimit\RateLimitedDecisionPlatform;
use Lingoda\AiSdk\RateLimit\RateLimiterInterface;
use Lingoda\AiSdk\RateLimit\SymfonyRateLimiter;
use Lingoda\AiSdk\RateLimit\TokenEstimatorRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RateLimitedDecisionPlatformTest extends TestCase
{
    private DecisionPlatformInterface&MockObject $inner;
    private TestDelay $delay;
    private DecisionResult $result;
    /** @var array<string, Question> */
    private array $questions;

    protected function setUp(): void
    {
        $this->inner = $this->createMock(DecisionPlatformInterface::class);
        $this->inner->method('getProvider')->willReturn(new TypeSafeProvider());
        $this->delay = new TestDelay();
        $this->result = new DecisionResult([], ['model' => DecisionModel::JEV_1_13_0->value]);
        $this->questions = ['refund' => Question::noul('Does the learner ask for a refund?')];
    }

    public function testDecidesThroughTheLimiterWithTheResolvedModel(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::once())
            ->method('consume')
            ->with(
                self::callback(static fn (ModelInterface $model): bool => $model->getId() === DecisionModel::JEV_1_13_0->value),
                self::greaterThan(1)
            )
        ;
        $this->inner->expects(self::once())
            ->method('decide')
            ->with('Please refund my voucher.', $this->questions, DecisionModel::JEV_1_13_0->value)
            ->willReturn($this->result)
        ;

        $result = $this->platform($rateLimiter)->decide('Please refund my voucher.', $this->questions);

        self::assertSame($this->result, $result);
        self::assertSame([], $this->delay->delayCalls);
    }

    public function testExplicitModelIsPassedOn(): void
    {
        $this->inner->expects(self::once())
            ->method('decide')
            ->with(['text' => 'state'], $this->questions, DecisionModel::JEV_LATEST->value)
            ->willReturn($this->result)
        ;

        $this->platform($this->createMock(RateLimiterInterface::class))->decide(['text' => 'state'], $this->questions, DecisionModel::JEV_LATEST->value);
    }

    public function testUnknownModelFailsBeforeTheLimiter(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::never())->method('consume');
        $this->inner->expects(self::never())->method('decide');

        $this->expectException(ModelNotFoundException::class);

        $this->platform($rateLimiter)->decide('state', $this->questions, 'jev-unknown');
    }

    public function testWaitsForTheLocalLimiterThenDecides(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects(self::exactly(2))
            ->method('consume')
            ->willReturnOnConsecutiveCalls(self::throwException(new RateLimitExceededException(7)), null)
        ;
        $this->inner->expects(self::once())->method('decide')->willReturn($this->result);

        self::assertSame($this->result, $this->platform($rateLimiter)->decide('state', $this->questions));
        self::assertSame([7], $this->delay->delayCalls);
    }

    public function testExhaustedLocalLimiterThrowsAtOnceWithRetriesDisabled(): void
    {
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->method('consume')->willThrowException(new RateLimitExceededException(7));
        $this->inner->expects(self::never())->method('decide');

        $this->expectException(RateLimitExceededException::class);

        try {
            $this->platform($rateLimiter, enableRetries: false)->decide('state', $this->questions);
        } finally {
            self::assertSame([], $this->delay->delayCalls);
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function retryableStatusCodes(): iterable
    {
        yield 'too many requests' => [429];
        yield 'bad gateway' => [502];
        yield 'service unavailable' => [503];
        yield 'gateway timeout' => [504];
        yield 'overloaded' => [529];
    }

    #[DataProvider('retryableStatusCodes')]
    public function testThrottledProviderIsRetriedWithBackoff(int $status): void
    {
        $this->inner->expects(self::exactly(3))
            ->method('decide')
            ->willReturnOnConsecutiveCalls(
                self::throwException(new ClientException('throttled', $status)),
                self::throwException(new ClientException('throttled', $status)),
                $this->result
            )
        ;

        self::assertSame($this->result, $this->platform($this->createMock(RateLimiterInterface::class))->decide('state', $this->questions));
        self::assertSame([1, 2], $this->delay->delayCalls);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function nonRetryableStatusCodes(): iterable
    {
        yield 'transport failure' => [0];
        yield 'bad request' => [400];
        yield 'unauthorized' => [401];
        yield 'validation' => [422];
        yield 'internal error' => [500];
    }

    #[DataProvider('nonRetryableStatusCodes')]
    public function testOtherFailuresAreRethrownAtOnce(int $status): void
    {
        $failure = new ClientException('failed', $status);
        $this->inner->expects(self::once())->method('decide')->willThrowException($failure);

        try {
            $this->platform($this->createMock(RateLimiterInterface::class))->decide('state', $this->questions);
            self::fail('Expected ClientException');
        } catch (ClientException $e) {
            self::assertSame($failure, $e);
        }
        self::assertSame([], $this->delay->delayCalls);
    }

    public function testGivesUpAfterMaxRetriesWithCappedBackoff(): void
    {
        $this->inner->expects(self::exactly(8))
            ->method('decide')
            ->willThrowException(new ClientException('overloaded', 529))
        ;

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(529);

        try {
            $this->platform($this->createMock(RateLimiterInterface::class), maxRetries: 8)->decide('state', $this->questions);
        } finally {
            self::assertSame([1, 2, 4, 8, 16, 30, 30], $this->delay->delayCalls);
        }
    }

    public function testThrottledProviderIsNotRetriedWithRetriesDisabled(): void
    {
        $this->inner->expects(self::once())->method('decide')->willThrowException(new ClientException('throttled', 429));

        $this->expectException(ClientException::class);

        $this->platform($this->createMock(RateLimiterInterface::class), enableRetries: false)->decide('state', $this->questions);
    }

    public function testTypeSafeRequestLimitIsEnforcedBySymfonyRateLimiter(): void
    {
        $this->inner->method('decide')->willReturn($this->result);
        $platform = $this->platform(new SymfonyRateLimiter(), enableRetries: false);

        // AIProvider::TYPESAFE allows 1,080 requests per minute
        for ($i = 0; $i < 1080; ++$i) {
            $platform->decide('state', $this->questions);
        }

        $this->expectException(RateLimitExceededException::class);

        $platform->decide('state', $this->questions);
    }

    public function testProviderComesFromTheWrappedPlatform(): void
    {
        self::assertSame($this->inner->getProvider(), $this->platform($this->createMock(RateLimiterInterface::class))->getProvider());
    }

    private function platform(RateLimiterInterface $rateLimiter, bool $enableRetries = true, int $maxRetries = 10): RateLimitedDecisionPlatform
    {
        return new RateLimitedDecisionPlatform(
            $this->inner,
            $rateLimiter,
            TokenEstimatorRegistry::createDefault(),
            delay: $this->delay,
            enableRetries: $enableRetries,
            maxRetries: $maxRetries,
        );
    }
}
