<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Enum\OpenAI;

use Lingoda\AiSdk\Enum\Capability;
use Lingoda\AiSdk\ModelConfigurationInterface;

enum ChatModel: string implements ModelConfigurationInterface
{
    // GPT-5 Series (Latest - August 2025) - Flagship models
    case GPT_5 = 'gpt-5';
    case GPT_5_20250807 = 'gpt-5-2025-08-07';
    case GPT_5_MINI = 'gpt-5-mini';
    case GPT_5_MINI_20250807 = 'gpt-5-mini-2025-08-07';
    case GPT_5_NANO = 'gpt-5-nano';
    case GPT_5_NANO_20250807 = 'gpt-5-nano-2025-08-07';
    
    // GPT-4.1 Series (April 2025) - 1M context window
    case GPT_41 = 'gpt-4.1';
    case GPT_41_20250414 = 'gpt-4.1-2025-04-14';
    case GPT_41_MINI = 'gpt-4.1-mini';
    case GPT_41_MINI_20250414 = 'gpt-4.1-mini-2025-04-14';
    case GPT_41_NANO = 'gpt-4.1-nano';
    case GPT_41_NANO_20250414 = 'gpt-4.1-nano-2025-04-14';
    
    // GPT-4o Series (Current) - 128K context window
    case GPT_4O = 'gpt-4o';
    case GPT_4O_20241120 = 'gpt-4o-2024-11-20';
    case GPT_4O_MINI = 'gpt-4o-mini';
    case GPT_4O_MINI_20240718 = 'gpt-4o-mini-2024-07-18';
    
    // GPT-4 Turbo (Legacy)
    case GPT_4_TURBO = 'gpt-4-turbo';
    case GPT_4_TURBO_20240409 = 'gpt-4-turbo-2024-04-09';

    public function getId(): string
    {
        return $this->value;
    }

    public function getMaxTokens(): int
    {
        return match($this) {
            // GPT-5 series - Assumed very high context (likely 1M+ based on progression)
            self::GPT_5,
            self::GPT_5_20250807 => 2000000, // 2M estimated
            
            // GPT-5 mini/nano series  
            self::GPT_5_MINI,
            self::GPT_5_MINI_20250807,
            self::GPT_5_NANO,
            self::GPT_5_NANO_20250807 => 1000000, // 1M estimated
            
            // GPT-4.1 series with 1M context window (confirmed from search)
            self::GPT_41,
            self::GPT_41_20250414,
            self::GPT_41_MINI,
            self::GPT_41_MINI_20250414,
            self::GPT_41_NANO,
            self::GPT_41_NANO_20250414 => 1000000,
            
            // GPT-4o series with 128K context window (confirmed)
            self::GPT_4O,
            self::GPT_4O_20241120,
            self::GPT_4O_MINI,
            self::GPT_4O_MINI_20240718,
            // GPT-4 Turbo with 128K context window
            self::GPT_4_TURBO,
            self::GPT_4_TURBO_20240409 => 128000,
        };
    }

    public function getCapabilities(): array
    {
        return match($this) {
            // GPT-5 models - Full multimodal capabilities
            self::GPT_5,
            self::GPT_5_20250807 => [
                Capability::TEXT,
                Capability::TOOLS,
                Capability::VISION,
                Capability::MULTIMODAL,
                Capability::REASONING,
            ],
            
            // GPT-5 mini/nano - Efficient models with full capabilities  
            self::GPT_5_MINI,
            self::GPT_5_MINI_20250807,
            self::GPT_5_NANO,
            self::GPT_5_NANO_20250807,
            // GPT-4.1 flagship models - Full multimodal capabilities
            self::GPT_41,
            self::GPT_41_20250414,
            // GPT-4o models - Multimodal capabilities
            self::GPT_4O,
            self::GPT_4O_20241120,
            self::GPT_4O_MINI,
            self::GPT_4O_MINI_20240718 => [
                Capability::TEXT,
                Capability::TOOLS,
                Capability::VISION,
                Capability::MULTIMODAL,
            ],

            // GPT-4 Turbo
            self::GPT_4_TURBO,
            self::GPT_4_TURBO_20240409,
            // GPT-4.1 efficient models
            self::GPT_41_MINI,
            self::GPT_41_MINI_20250414 => [
                Capability::TEXT,
                Capability::TOOLS,
                Capability::VISION,
            ],
            
            // GPT-4.1 nano - Text and tools focused
            self::GPT_41_NANO,
            self::GPT_41_NANO_20250414 => [
                Capability::TEXT,
                Capability::TOOLS,
            ],
        };
    }

    public function hasCapability(Capability $capability): bool
    {
        return in_array($capability, $this->getCapabilities(), true);
    }

    /**
     * Get default options for this model.
     *
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return match($this) {
            // GPT-5 flagship models - Highest output capabilities
            self::GPT_5,
            self::GPT_5_20250807 => [
                'temperature' => 1.0,
                'max_tokens' => 32768,
                'top_p' => 1.0,
            ],
            
            // GPT-5 efficient models
            self::GPT_5_MINI,
            self::GPT_5_MINI_20250807 => [
                'temperature' => 1.0,
                'max_tokens' => 16384,
                'top_p' => 1.0,
            ],
            
            // GPT-5 nano - Most efficient
            self::GPT_5_NANO,
            self::GPT_5_NANO_20250807 => [
                'temperature' => 1.0,
                'max_tokens' => 8192,
                'top_p' => 1.0,
            ],
            
            // GPT-4.1 flagship models - API validated: 32,768 max tokens
            self::GPT_41,
            self::GPT_41_20250414 => [
                'temperature' => 1.0,
                'max_tokens' => 32768,
                'top_p' => 1.0,
            ],
            
            // GPT-4.1 efficient models - API validated: 32,768 max tokens
            self::GPT_41_MINI,
            self::GPT_41_MINI_20250414 => [
                'temperature' => 1.0,
                'max_tokens' => 32768,
                'top_p' => 1.0,
            ],
            
            // GPT-4.1 nano - API validated: 32,768 max tokens
            self::GPT_41_NANO,
            self::GPT_41_NANO_20250414 => [
                'temperature' => 1.0,
                'max_tokens' => 32768,
                'top_p' => 1.0,
            ],
            
            // GPT-4o models - API validated: 16,384 max tokens
            self::GPT_4O,
            self::GPT_4O_20241120,
            self::GPT_4O_MINI,
            self::GPT_4O_MINI_20240718 => [
                'temperature' => 1.0,
                'max_tokens' => 16384,
                'top_p' => 1.0,
            ],
            
            // GPT-4 Turbo
            self::GPT_4_TURBO,
            self::GPT_4_TURBO_20240409 => [
                'temperature' => 1.0,
                'max_tokens' => 8192,
                'top_p' => 1.0,
            ],
        };
    }

    /**
     * Get the display name of the model.
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::GPT_5 => 'GPT-5',
            self::GPT_5_20250807 => 'GPT-5 (2025-08-07)',
            self::GPT_5_MINI => 'GPT-5 Mini',
            self::GPT_5_MINI_20250807 => 'GPT-5 Mini (2025-08-07)',
            self::GPT_5_NANO => 'GPT-5 Nano',
            self::GPT_5_NANO_20250807 => 'GPT-5 Nano (2025-08-07)',
            self::GPT_41 => 'GPT-4.1',
            self::GPT_41_20250414 => 'GPT-4.1 (2025-04-14)',
            self::GPT_41_MINI => 'GPT-4.1 Mini',
            self::GPT_41_MINI_20250414 => 'GPT-4.1 Mini (2025-04-14)',
            self::GPT_41_NANO => 'GPT-4.1 Nano',
            self::GPT_41_NANO_20250414 => 'GPT-4.1 Nano (2025-04-14)',
            self::GPT_4O => 'GPT-4o',
            self::GPT_4O_20241120 => 'GPT-4o (2024-11-20)',
            self::GPT_4O_MINI => 'GPT-4o Mini',
            self::GPT_4O_MINI_20240718 => 'GPT-4o Mini (2024-07-18)',
            self::GPT_4_TURBO => 'GPT-4 Turbo',
            self::GPT_4_TURBO_20240409 => 'GPT-4 Turbo (2024-04-09)',
        };
    }

    /**
     * Get the default model ID for OpenAI provider.
     * Returns cost-effective GPT-4o Mini as default.
     */
    public function getDefaultModel(): string
    {
        return self::GPT_4O_MINI->value;
    }
}