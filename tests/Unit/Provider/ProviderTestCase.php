<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Provider;

use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\Exception\InvalidArgumentException;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for Provider implementations.
 * 
 * Provides common test patterns for all Provider classes to ensure
 * consistent behavior across different AI provider implementations.
 */
abstract class ProviderTestCase extends TestCase
{
    /**
     * The provider being tested.
     */
    protected ProviderInterface $provider;
    
    /**
     * Create an instance of the provider being tested.
     */
    abstract protected function createProvider(): ProviderInterface;
    
    /**
     * Get the expected provider ID.
     */
    abstract protected function getExpectedId(): string;
    
    /**
     * Get the expected provider name.
     */
    abstract protected function getExpectedName(): string;
    
    /**
     * Get expected model IDs that should be available.
     * 
     * @return string[] Array of model IDs
     */
    abstract protected function getExpectedModelIds(): array;
    
    /**
     * Get the provider enum for this provider.
     */
    abstract protected function getProviderEnum(): AIProvider;
    
    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        $this->provider = $this->createProvider();
    }
    
    /**
     * Test getId returns expected value.
     */
    public function testGetId(): void
    {
        $this->assertEquals($this->getExpectedId(), $this->provider->getId());
    }
    
    /**
     * Test getName returns expected value.
     */
    public function testGetName(): void
    {
        $this->assertEquals($this->getExpectedName(), $this->provider->getName());
    }
    
    /**
     * Test getModels returns non-empty array of models.
     */
    public function testGetModels(): void
    {
        $models = $this->provider->getModels();
        
        $this->assertIsArray($models);
        $this->assertNotEmpty($models, 'Provider should have at least one model');
        
        foreach ($models as $model) {
            $this->assertInstanceOf(
                ModelInterface::class,
                $model,
                'All models should implement ModelInterface'
            );
        }
    }
    
    /**
     * Test getModels returns array with numeric keys (array_values applied).
     */
    public function testGetModelsReturnsArrayValues(): void
    {
        $models = $this->provider->getModels();
        
        $keys = array_keys($models);
        $expectedKeys = range(0, count($models) - 1);
        
        $this->assertEquals(
            $expectedKeys,
            $keys,
            'Models array should have numeric keys starting from 0'
        );
    }
    
    /**
     * Test getModel with valid model ID.
     */
    public function testGetModelWithValidId(): void
    {
        $expectedModelIds = $this->getExpectedModelIds();
        
        $this->assertNotEmpty(
            $expectedModelIds,
            'Test must provide at least one expected model ID'
        );
        
        foreach ($expectedModelIds as $modelId) {
            $model = $this->provider->getModel($modelId);
            
            $this->assertInstanceOf(ModelInterface::class, $model);
            $this->assertEquals($modelId, $model->getId());
            $this->assertSame(
                $this->provider,
                $model->getProvider(),
                'Model should reference its provider'
            );
        }
    }
    
    /**
     * Test getModel with invalid ID throws exception.
     */
    public function testGetModelWithInvalidIdThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'Model "non-existent-model" is not supported by provider "%s"',
            $this->getExpectedId()
        ));
        
        $this->provider->getModel('non-existent-model');
    }
    
    /**
     * Test hasModel with valid models.
     */
    public function testHasModelWithValidModels(): void
    {
        $expectedModelIds = $this->getExpectedModelIds();
        
        foreach ($expectedModelIds as $modelId) {
            $this->assertTrue(
                $this->provider->hasModel($modelId),
                sprintf('Provider should have model "%s"', $modelId)
            );
        }
    }
    
    /**
     * Test hasModel with invalid models.
     */
    public function testHasModelWithInvalidModels(): void
    {
        $invalidModelIds = [
            'non-existent-model',
            'invalid-model-123',
            '',
            'model-from-other-provider'
        ];
        
        foreach ($invalidModelIds as $modelId) {
            $this->assertFalse(
                $this->provider->hasModel($modelId),
                sprintf('Provider should not have model "%s"', $modelId)
            );
        }
    }
    
    /**
     * Test is method with correct provider.
     */
    public function testIsMethodWithCorrectProvider(): void
    {
        $this->assertTrue(
            $this->provider->is($this->getProviderEnum()),
            'Provider should match its enum'
        );
    }
    
    /**
     * Test is method with incorrect providers.
     */
    public function testIsMethodWithIncorrectProviders(): void
    {
        $allProviders = AIProvider::cases();
        $expectedProvider = $this->getProviderEnum();
        
        foreach ($allProviders as $provider) {
            if ($provider === $expectedProvider) {
                continue;
            }
            
            $this->assertFalse(
                $this->provider->is($provider),
                sprintf('Provider should not match %s', $provider->value)
            );
        }
    }
    
    /**
     * Test all models have correct provider reference.
     */
    public function testAllModelsHaveCorrectProvider(): void
    {
        $models = $this->provider->getModels();
        
        foreach ($models as $model) {
            $this->assertSame(
                $this->provider,
                $model->getProvider(),
                'All models should reference the same provider instance'
            );
            
            $this->assertEquals(
                $this->getExpectedId(),
                $model->getProvider()->getId(),
                'Model provider should have correct ID'
            );
        }
    }
    
    /**
     * Test model count matches expected models.
     */
    public function testModelCount(): void
    {
        $models = $this->provider->getModels();
        $expectedModelIds = $this->getExpectedModelIds();
        
        $this->assertGreaterThanOrEqual(
            count($expectedModelIds),
            count($models),
            'Provider should have at least the expected models'
        );
    }
    
    /**
     * Test that expected models are present.
     */
    public function testExpectedModelsArePresent(): void
    {
        $models = $this->provider->getModels();
        $actualModelIds = array_map(fn($model) => $model->getId(), $models);
        
        foreach ($this->getExpectedModelIds() as $expectedId) {
            $this->assertContains(
                $expectedId,
                $actualModelIds,
                sprintf('Expected model "%s" not found in provider', $expectedId)
            );
        }
    }
    
    /**
     * Test provider implements ProviderInterface.
     */
    public function testImplementsProviderInterface(): void
    {
        $this->assertInstanceOf(ProviderInterface::class, $this->provider);
    }
}