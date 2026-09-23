<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\RateLimit\SystemDelay;
use PHPUnit\Framework\TestCase;

final class SystemDelayTest extends TestCase
{
    public function testDelaySleepsForTheGivenSeconds(): void
    {
        $start = microtime(true);

        (new SystemDelay())->delay(1);

        $this->assertGreaterThanOrEqual(0.9, microtime(true) - $start);
    }
}
