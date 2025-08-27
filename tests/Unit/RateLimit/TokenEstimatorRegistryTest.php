<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\RateLimit;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;
use Lingoda\AiSdk\RateLimit\AnthropicTokenEstimator;
use Lingoda\AiSdk\RateLimit\GeminiTokenEstimator;
use Lingoda\AiSdk\RateLimit\OpenAITokenEstimator;
use Lingoda\AiSdk\RateLimit\TokenEstimator;
use Lingoda\AiSdk\RateLimit\TokenEstimatorInterface;
use Lingoda\AiSdk\RateLimit\TokenEstimatorRegistry;
use PHPUnit\Framework\TestCase;

final class TokenEstimatorRegistryTest extends TestCase
{
    private TokenEstimatorRegistry $registry;
    private ModelInterface $openaiModel;
    private ModelInterface $anthropicModel;
    private ModelInterface $geminiModel;
    private ModelInterface $unknownModel;

    protected function setUp(): void
    {
        $this->registry = new TokenEstimatorRegistry();

        // Create mock models for different providers
        $openaiProvider = $this->createMock(ProviderInterface::class);
        $openaiProvider->method('getId')->willReturn('openai');
        
        $this->openaiModel = $this->createMock(ModelInterface::class);
        $this->openaiModel->method('getProvider')->willReturn($openaiProvider);

        $anthropicProvider = $this->createMock(ProviderInterface::class);
        $anthropicProvider->method('getId')->willReturn('anthropic');
        
        $this->anthropicModel = $this->createMock(ModelInterface::class);
        $this->anthropicModel->method('getProvider')->willReturn($anthropicProvider);

        $geminiProvider = $this->createMock(ProviderInterface::class);
        $geminiProvider->method('getId')->willReturn('gemini');
        
        $this->geminiModel = $this->createMock(ModelInterface::class);
        $this->geminiModel->method('getProvider')->willReturn($geminiProvider);

        $unknownProvider = $this->createMock(ProviderInterface::class);
        $unknownProvider->method('getId')->willReturn('unknown');
        
        $this->unknownModel = $this->createMock(ModelInterface::class);
        $this->unknownModel->method('getProvider')->willReturn($unknownProvider);
    }

    public function testRegisterAndGetEstimator(): void
    {
        $customEstimator = $this->createMock(TokenEstimatorInterface::class);
        
        $this->registry->register(AIProvider::OPENAI, $customEstimator);
        
        $result = $this->registry->getEstimatorForModel($this->openaiModel);
        
        $this->assertSame($customEstimator, $result);
    }

    public function testGetEstimatorForUnregisteredProviderReturnsFallback(): void
    {
        $result = $this->registry->getEstimatorForModel($this->unknownModel);
        
        $this->assertInstanceOf(TokenEstimator::class, $result);
    }

    public function testCreateDefaultRegistersAllProviders(): void
    {
        $registry = TokenEstimatorRegistry::createDefault();
        
        $openaiEstimator = $registry->getEstimatorForModel($this->openaiModel);
        $anthropicEstimator = $registry->getEstimatorForModel($this->anthropicModel);
        $geminiEstimator = $registry->getEstimatorForModel($this->geminiModel);
        
        $this->assertInstanceOf(OpenAITokenEstimator::class, $openaiEstimator);
        $this->assertInstanceOf(AnthropicTokenEstimator::class, $anthropicEstimator);
        $this->assertInstanceOf(GeminiTokenEstimator::class, $geminiEstimator);
    }

    public function testEstimateUsesCorrectEstimator(): void
    {
        $registry = TokenEstimatorRegistry::createDefault();
        
        $openaiResult = $registry->estimate($this->openaiModel, 'Hello world');
        $anthropicResult = $registry->estimate($this->anthropicModel, 'Hello world');
        $geminiResult = $registry->estimate($this->geminiModel, 'Hello world');
        
        $this->assertGreaterThan(0, $openaiResult);
        $this->assertGreaterThan(0, $anthropicResult);
        $this->assertGreaterThan(0, $geminiResult);
        
        // Results might be slightly different due to provider-specific calculations
        $this->assertIsInt($openaiResult);
        $this->assertIsInt($anthropicResult);
        $this->assertIsInt($geminiResult);
    }

    public function testGetRegisteredProviders(): void
    {
        $this->registry->register(AIProvider::OPENAI, $this->createMock(TokenEstimatorInterface::class));
        $this->registry->register(AIProvider::ANTHROPIC, $this->createMock(TokenEstimatorInterface::class));
        
        $providers = $this->registry->getRegisteredProviders();
        
        $this->assertEquals(['openai', 'anthropic'], $providers);
    }

    public function testHasEstimatorForModel(): void
    {
        $this->registry->register(AIProvider::OPENAI, $this->createMock(TokenEstimatorInterface::class));
        
        $this->assertTrue($this->registry->hasEstimatorForModel($this->openaiModel));
        $this->assertFalse($this->registry->hasEstimatorForModel($this->anthropicModel));
    }

    public function testOpenAIEstimatorHandlesMessages(): void
    {
        $estimator = new OpenAITokenEstimator();
        
        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello world'],
                ['role' => 'assistant', 'content' => 'Hi there!'],
            ]
        ];
        
        $result = $estimator->estimate($this->openaiModel, $payload);
        
        $this->assertGreaterThan(0, $result);
        $this->assertIsInt($result);
    }

    public function testAnthropicEstimatorHandlesContentBlocks(): void
    {
        $estimator = new AnthropicTokenEstimator();
        
        $payload = [
            'content' => [
                ['type' => 'text', 'text' => 'Hello world']
            ]
        ];
        
        $result = $estimator->estimate($this->anthropicModel, $payload);
        
        $this->assertGreaterThan(0, $result);
        $this->assertIsInt($result);
    }

    public function testGeminiEstimatorHandlesContents(): void
    {
        $estimator = new GeminiTokenEstimator();
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Hello world']
                    ]
                ]
            ]
        ];
        
        $result = $estimator->estimate($this->geminiModel, $payload);
        
        $this->assertGreaterThan(0, $result);
        $this->assertIsInt($result);
    }
}