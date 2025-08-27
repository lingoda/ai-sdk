<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\RateLimit\DelayInterface;

/**
 * Test delay implementation that records delay calls without actually delaying.
 * Inspired by Symfony's MockClock functionality.
 */
final class TestDelay implements DelayInterface
{
    /** @var array<int> */
    public array $delayCalls = [];
    
    public function delay(int $seconds): void
    {
        $this->delayCalls[] = $seconds;
        // Don't actually delay in tests - advances instantly like MockClock
    }
    
    public function getTotalDelayTime(): int
    {
        return array_sum($this->delayCalls);
    }
    
    public function getDelayCallCount(): int
    {
        return count($this->delayCalls);
    }
    
    public function getLastDelayCall(): int
    {
        return end($this->delayCalls) ?: 0;
    }
    
    public function reset(): void
    {
        $this->delayCalls = [];
    }
    
    /**
     * Get all delay calls for detailed assertions.
     * 
     * @return array<int>
     */
    public function getDelayCalls(): array
    {
        return $this->delayCalls;
    }
}