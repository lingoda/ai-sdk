<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Security;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class TestLogger implements LoggerInterface
{
    /** @var array<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context
        ];
    }

    public function hasInfo(string $message): bool
    {
        return $this->hasRecord($message, LogLevel::INFO);
    }

    public function hasWarning(string $message): bool
    {
        return $this->hasRecord($message, LogLevel::WARNING);
    }

    public function hasError(string $message): bool
    {
        return $this->hasRecord($message, LogLevel::ERROR);
    }

    public function hasDebug(string $message): bool
    {
        return $this->hasRecord($message, LogLevel::DEBUG);
    }

    public function hasRecord(string $message, string $level): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && $record['message'] === $message) {
                return true;
            }
        }
        return false;
    }
}