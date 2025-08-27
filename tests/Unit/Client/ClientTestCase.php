<?php

declare(strict_types=1);

namespace Lingoda\AiSdk\Tests\Unit\Client;

use Lingoda\AiSdk\ClientInterface;
use Lingoda\AiSdk\Enum\AIProvider;
use Lingoda\AiSdk\ModelInterface;
use Lingoda\AiSdk\ProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * Base test case for Client implementations.
 * 
 * Provides common test patterns and utilities for testing AI client classes.
 */
abstract class ClientTestCase extends TestCase
{
    /**
     * The API client mock (e.g., OpenAIAPIClient, GeminiAPIClient).
     * 
     * @var MockObject
     */
    protected MockObject $apiClient;
    
    /**
     * Logger mock.
     * 
     * @var LoggerInterface&MockObject
     */
    protected LoggerInterface&MockObject $logger;
    
    /**
     * The client being tested.
     * 
     * @var ClientInterface
     */
    protected ClientInterface $client;
    
    /**
     * Model mock for testing.
     * 
     * @var ModelInterface&MockObject
     */
    protected ModelInterface&MockObject $model;
    
    /**
     * Provider mock for testing.
     * 
     * @var ProviderInterface&MockObject
     */
    protected ProviderInterface&MockObject $provider;
    
    /**
     * Create an instance of the client being tested.
     * 
     * @param mixed $apiClient The API client (type varies by implementation)
     * @param LoggerInterface $logger The logger
     */
    abstract protected function createClient(mixed $apiClient, LoggerInterface $logger): ClientInterface;
    
    /**
     * Get the expected provider enum for this client.
     */
    abstract protected function getProviderEnum(): AIProvider;
    
    /**
     * Get the API client class name for mocking.
     * 
     * @return class-string
     */
    abstract protected function getApiClientClass(): string;
    
    /**
     * Get default model ID for testing.
     */
    protected function getDefaultModelId(): string
    {
        return 'test-model';
    }
    
    /**
     * Get default model options for testing.
     * 
     * @return array<string, mixed>
     */
    protected function getDefaultModelOptions(): array
    {
        return ['temperature' => 0.7];
    }
    
    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        // Create API client mock
        $this->apiClient = $this->createMock($this->getApiClientClass());
        
        // Create logger mock
        $this->logger = $this->createMock(LoggerInterface::class);
        
        // Create the client
        $this->client = $this->createClient($this->apiClient, $this->logger);
        
        // Set up default model mock
        $this->setupModelMock();
    }
    
    /**
     * Set up the model mock with default behavior.
     */
    protected function setupModelMock(): void
    {
        $this->model = $this->createMock(ModelInterface::class);
        $this->model->method('getId')->willReturn($this->getDefaultModelId());
        $this->model->method('getOptions')->willReturn($this->getDefaultModelOptions());
        $this->model->method('getMaxTokens')->willReturn(8192);
        
        // Set up provider mock
        $this->provider = $this->createMock(ProviderInterface::class);
        $this->model->method('getProvider')->willReturn($this->provider);
    }
    
    /**
     * Create a mock model with specific configuration.
     * 
     * @param string $modelId The model ID
     * @param array<string, mixed> $options Model options
     * @param int $maxTokens Maximum tokens
     */
    protected function createMockModel(
        string $modelId = 'test-model',
        array $options = [],
        int $maxTokens = 8192
    ): ModelInterface&MockObject {
        $model = $this->createMock(ModelInterface::class);
        $model->method('getId')->willReturn($modelId);
        $model->method('getOptions')->willReturn($options);
        $model->method('getMaxTokens')->willReturn($maxTokens);
        
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('is')->willReturnCallback(function(AIProvider $p) {
            return $p === $this->getProviderEnum();
        });
        
        $model->method('getProvider')->willReturn($provider);
        
        return $model;
    }
    
    /**
     * Test that client supports correct provider.
     */
    public function testSupportsCorrectProvider(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('is')
            ->with($this->getProviderEnum())
            ->willReturn(true);
        
        $model = $this->createMock(ModelInterface::class);
        $model->method('getProvider')->willReturn($provider);
        
        $this->assertTrue($this->client->supports($model));
    }
    
    /**
     * Test that client does not support other providers.
     */
    public function testDoesNotSupportOtherProviders(): void
    {
        // Test with each provider except the expected one
        $allProviders = AIProvider::cases();
        $expectedProvider = $this->getProviderEnum();
        
        foreach ($allProviders as $provider) {
            if ($provider === $expectedProvider) {
                continue;
            }
            
            $providerMock = $this->createMock(ProviderInterface::class);
            $providerMock->method('is')
                ->with($expectedProvider)
                ->willReturn(false);
            
            $model = $this->createMock(ModelInterface::class);
            $model->method('getProvider')->willReturn($providerMock);
            
            $this->assertFalse(
                $this->client->supports($model),
                sprintf('Client should not support %s provider', $provider->value)
            );
        }
    }
    
    /**
     * Test getProvider method returns correct provider instance.
     */
    public function testGetProvider(): void
    {
        $provider = $this->client->getProvider();
        
        $this->assertInstanceOf(ProviderInterface::class, $provider);
        $this->assertTrue($provider->is($this->getProviderEnum()));
    }
    
    /**
     * Test that getProvider returns same instance (lazy loading).
     */
    public function testGetProviderLazyLoading(): void
    {
        $provider1 = $this->client->getProvider();
        $provider2 = $this->client->getProvider();
        
        $this->assertSame($provider1, $provider2, 'Provider should be lazy loaded');
    }
    
    /**
     * Invoke a private/protected method for testing.
     * 
     * @param string $methodName Method name to invoke
     * @param array $arguments Method arguments
     * @return mixed The method result
     */
    protected function invokePrivateMethod(string $methodName, array $arguments): mixed
    {
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($this->client, $arguments);
    }
    
    /**
     * Get a private/protected property value.
     * 
     * @param string $propertyName Property name
     * @return mixed The property value
     */
    protected function getPrivateProperty(string $propertyName): mixed
    {
        $reflection = new ReflectionClass($this->client);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        
        return $property->getValue($this->client);
    }
    
    /**
     * Set a private/protected property value.
     * 
     * @param string $propertyName Property name
     * @param mixed $value Value to set
     */
    protected function setPrivateProperty(string $propertyName, mixed $value): void
    {
        $reflection = new ReflectionClass($this->client);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($this->client, $value);
    }
}