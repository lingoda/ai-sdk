<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Result;

use Lingoda\AiSdk\Result\ResultInterface;
use Lingoda\AiSdk\Result\TokenDetails;
use Lingoda\AiSdk\Result\Usage;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for Result implementations.
 *
 * Provides common test patterns for all Result classes to ensure
 * consistent behavior across different result types.
 */
abstract class ResultTestCase extends TestCase
{
    /**
     * Create an instance of the Result being tested.
     *
     * @param mixed $content The content for the result
     * @param array<string, mixed> $metadata Optional metadata
     */
    abstract protected function createResult(mixed $content, array $metadata = []): ResultInterface;

    /**
     * Get the expected content for test assertions.
     *
     * @return mixed The expected content value
     */
    abstract protected function getExpectedContent(): mixed;

    /**
     * Get sample metadata for testing.
     *
     * @return array<string, mixed>
     */
    protected function getSampleMetadata(): array
    {
        return [
            'model' => 'test-model',
            'usage' => ['tokens' => 100],
            'timestamp' => time()
        ];
    }

    /**
     * Test creating a result with content only.
     */
    public function testConstructorWithContent(): void
    {
        $content = $this->getExpectedContent();
        $result = $this->createResult($content);

        $this->assertSame($content, $result->getContent());
        $this->assertSame([], $result->getMetadata());
    }

    /**
     * Test creating a result with content and metadata.
     */
    public function testConstructorWithMetadata(): void
    {
        $content = $this->getExpectedContent();
        $metadata = $this->getSampleMetadata();
        $result = $this->createResult($content, $metadata);

        $this->assertSame($content, $result->getContent());
        $this->assertSame($metadata, $result->getMetadata());
    }

    /**
     * Test the getContent method returns expected value.
     */
    public function testGetContent(): void
    {
        $content = $this->getExpectedContent();
        $result = $this->createResult($content);

        $this->assertSame($content, $result->getContent());
    }

    /**
     * Test the getMetadata method returns expected value.
     */
    public function testGetMetadata(): void
    {
        $content = $this->getExpectedContent();
        $metadata = ['key' => 'value', 'number' => 42];
        $result = $this->createResult($content, $metadata);

        $this->assertSame($metadata, $result->getMetadata());
        $this->assertArrayHasKey('key', $result->getMetadata());
        $this->assertArrayHasKey('number', $result->getMetadata());
    }

    /**
     * Test that empty metadata returns empty array.
     */
    public function testEmptyMetadata(): void
    {
        $content = $this->getExpectedContent();
        $result = $this->createResult($content);

        $this->assertIsArray($result->getMetadata());
        $this->assertEmpty($result->getMetadata());
    }

    /**
     * Test with various metadata types.
     */
    public function testMetadataWithVariousTypes(): void
    {
        $content = $this->getExpectedContent();
        $metadata = [
            'string' => 'value',
            'integer' => 123,
            'float' => 3.14,
            'boolean' => true,
            'array' => ['nested' => 'data'],
            'null' => null
        ];

        $result = $this->createResult($content, $metadata);

        $this->assertSame($metadata, $result->getMetadata());
        $this->assertIsString($result->getMetadata()['string']);
        $this->assertIsInt($result->getMetadata()['integer']);
        $this->assertIsFloat($result->getMetadata()['float']);
        $this->assertIsBool($result->getMetadata()['boolean']);
        $this->assertIsArray($result->getMetadata()['array']);
        $this->assertNull($result->getMetadata()['null']);
    }

    /**
     * Test that results initially have no usage data.
     */
    public function testInitialUsageIsNull(): void
    {
        $result = $this->createResult($this->getExpectedContent());

        $this->assertNull($result->getUsage());
    }

    /**
     * Test setting usage data with withUsage method.
     */
    public function testWithUsageReturnsNewInstance(): void
    {
        $result = $this->createResult($this->getExpectedContent());
        $usage = new Usage(10, 5, 15);

        $newResult = $result->withUsage($usage);

        // Should return a new instance
        $this->assertNotSame($result, $newResult);

        // Original should still have null usage
        $this->assertNull($result->getUsage());

        // New instance should have the usage
        $this->assertSame($usage, $newResult->getUsage());
    }

    /**
     * Test setting usage data to null with withUsage method.
     */
    public function testWithUsageNull(): void
    {
        $result = $this->createResult($this->getExpectedContent());
        $usage = new Usage(10, 5, 15);

        $resultWithUsage = $result->withUsage($usage);
        $resultWithoutUsage = $resultWithUsage->withUsage(null);

        $this->assertSame($usage, $resultWithUsage->getUsage());
        $this->assertNull($resultWithoutUsage->getUsage());
    }

    /**
     * Test that withUsage preserves the original content and metadata.
     */
    public function testWithUsagePreservesContentAndMetadata(): void
    {
        $metadata = $this->getSampleMetadata();
        $result = $this->createResult($this->getExpectedContent(), $metadata);
        $usage = new Usage(10, 5, 15);

        $newResult = $result->withUsage($usage);

        $this->assertEquals($result->getContent(), $newResult->getContent());
        $this->assertEquals($result->getMetadata(), $newResult->getMetadata());
    }

    /**
     * Test setting complex usage data with details.
     */
    public function testWithComplexUsage(): void
    {
        $result = $this->createResult($this->getExpectedContent());

        $promptDetails = new TokenDetails(audioTokens: 2, cachedTokens: 3);
        $completionDetails = new TokenDetails(reasoningTokens: 4);

        $usage = new Usage(
            promptTokens: 20,
            completionTokens: 10,
            totalTokens: 30,
            promptDetails: $promptDetails,
            completionDetails: $completionDetails,
            cachedTokens: 5,
            reasoningTokens: 4
        );

        $newResult = $result->withUsage($usage);
        $retrievedUsage = $newResult->getUsage();

        $this->assertNotNull($retrievedUsage);
        $this->assertEquals(20, $retrievedUsage->promptTokens);
        $this->assertEquals(10, $retrievedUsage->completionTokens);
        $this->assertEquals(30, $retrievedUsage->totalTokens);
        $this->assertSame($promptDetails, $retrievedUsage->promptDetails);
        $this->assertSame($completionDetails, $retrievedUsage->completionDetails);
        $this->assertEquals(5, $retrievedUsage->cachedTokens);
        $this->assertEquals(4, $retrievedUsage->reasoningTokens);
    }
}
