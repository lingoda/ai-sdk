<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Audio;

/**
 * Platform-agnostic interface for audio processing options.
 * Each provider implements this interface with their specific configuration.
 */
interface AudioOptionsInterface
{
    /**
     * Get the configured options as an array.
     * This is used by the client implementations to build API requests.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Get the provider this options object is designed for.
     * This helps ensure compatibility between options and clients.
     *
     * @return string The provider identifier (e.g., 'openai', 'anthropic')
     */
    public function getProvider(): string;
}