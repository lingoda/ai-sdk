<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Tests\Unit\Provider;

use Lingoda\AiSdk\Provider\ProviderCollection;
use Lingoda\AiSdk\ProviderInterface;
use PHPUnit\Framework\TestCase;

class ProviderCollectionTest extends TestCase
{
    public function testEmptyCollection(): void
    {
        $collection = new ProviderCollection();

        $this->assertEmpty($collection->getProviders());
        $this->assertEmpty($collection->getIds());
        $this->assertTrue($collection->isEmpty());
        $this->assertCount(0, $collection);
    }

    public function testAddProvider(): void
    {
        $collection = new ProviderCollection();

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getId')->willReturn('openai');

        $collection->add($provider);

        $this->assertFalse($collection->isEmpty());
        $this->assertCount(1, $collection);
        $this->assertSame(['openai'], $collection->getIds());
        $this->assertSame([$provider], $collection->getProviders());
    }

    public function testConstructorWithProviders(): void
    {
        $provider1 = $this->createMock(ProviderInterface::class);
        $provider1->method('getId')->willReturn('openai');

        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('getId')->willReturn('anthropic');

        $collection = new ProviderCollection([$provider1, $provider2]);

        $this->assertCount(2, $collection);
        $this->assertSame(['openai', 'anthropic'], $collection->getIds());
        $this->assertSame([$provider1, $provider2], $collection->getProviders());
    }

    public function testAddDuplicateProviderOverwrites(): void
    {
        $collection = new ProviderCollection();

        $provider1 = $this->createMock(ProviderInterface::class);
        $provider1->method('getId')->willReturn('openai');
        $provider1->method('getName')->willReturn('OpenAI v1');

        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('getId')->willReturn('openai');
        $provider2->method('getName')->willReturn('OpenAI v2');

        $collection->add($provider1);
        $collection->add($provider2);

        $this->assertCount(1, $collection);
        $this->assertSame(['openai'], $collection->getIds());
        $this->assertSame($provider2, $collection->get('openai'));
    }

    public function testHasProvider(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getId')->willReturn('openai');

        $collection = new ProviderCollection([$provider]);

        $this->assertTrue($collection->has('openai'));
        $this->assertFalse($collection->has('anthropic'));
    }

    public function testGetProvider(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getId')->willReturn('openai');

        $collection = new ProviderCollection([$provider]);

        $this->assertSame($provider, $collection->get('openai'));
        $this->assertNull($collection->get('anthropic'));
    }

    public function testIteratorInterface(): void
    {
        $provider1 = $this->createMock(ProviderInterface::class);
        $provider1->method('getId')->willReturn('openai');

        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('getId')->willReturn('anthropic');

        $collection = new ProviderCollection([$provider1, $provider2]);

        $this->assertCount(2, $collection);
        $this->assertTrue($collection->has('openai'));
        $this->assertTrue($collection->has('anthropic'));
        $this->assertSame($provider1, $collection['openai']);
        $this->assertSame($provider2, $collection['anthropic']);
    }

    public function testGetProvidersReturnsIndexedArray(): void
    {
        $provider1 = $this->createMock(ProviderInterface::class);
        $provider1->method('getId')->willReturn('openai');

        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('getId')->willReturn('anthropic');

        $provider3 = $this->createMock(ProviderInterface::class);
        $provider3->method('getId')->willReturn('gemini');

        $collection = new ProviderCollection([$provider1, $provider2, $provider3]);

        $providers = $collection->getProviders();

        // Ensure the array is indexed (0, 1, 2) not associative
        $this->assertSame([0, 1, 2], array_keys($providers));
        $this->assertSame($provider1, $providers[0]);
        $this->assertSame($provider2, $providers[1]);
        $this->assertSame($provider3, $providers[2]);
    }

    public function testGetIdsReturnsKeyArray(): void
    {
        $provider1 = $this->createMock(ProviderInterface::class);
        $provider1->method('getId')->willReturn('openai');

        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('getId')->willReturn('anthropic');

        $collection = new ProviderCollection([$provider1, $provider2]);

        $ids = $collection->getIds();

        $this->assertIsArray($ids);
        $this->assertContains('openai', $ids);
        $this->assertContains('anthropic', $ids);
        $this->assertCount(2, $ids);
    }

    public function testArrayAccessInterface(): void
    {
        $provider1 = $this->createMock(ProviderInterface::class);
        $provider1->method('getId')->willReturn('openai');

        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('getId')->willReturn('anthropic');

        $collection = new ProviderCollection();

        // Test offsetSet with key
        $collection['openai'] = $provider1;
        $this->assertTrue($collection->has('openai'));
        $this->assertSame($provider1, $collection['openai']);

        // Test offsetSet without key (should use provider's ID)
        $collection[] = $provider2;
        $this->assertTrue(isset($collection['anthropic']));
        $this->assertSame($provider2, $collection['anthropic']);

        // Test offsetExists
        $this->assertFalse(isset($collection['gemini']));

        // Test offsetGet
        $this->assertSame($provider1, $collection['openai']);
        $this->assertNull($collection['nonexistent']);

        // Test offsetUnset
        unset($collection['openai']);
        $this->assertFalse($collection->has('openai'));
        $this->assertCount(1, $collection);
    }

    public function testArrayAccessThrowsExceptionForInvalidValue(): void
    {
        $collection = new ProviderCollection();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be an instance of ProviderInterface');

        /** @phpstan-ignore-next-line This is the tested behavior */
        $collection['test'] = 'not a provider';
    }

    public function testToString(): void
    {
        $provider1 = $this->createMock(ProviderInterface::class);
        $provider1->method('getId')->willReturn('openai');

        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('getId')->willReturn('anthropic');

        $provider3 = $this->createMock(ProviderInterface::class);
        $provider3->method('getId')->willReturn('gemini');

        $collection = new ProviderCollection([$provider1, $provider2, $provider3]);

        // Test __toString
        $this->assertEquals('openai, anthropic, gemini', (string) $collection);

        // Test with implode (should use __toString automatically)
        $this->assertEquals('openai, anthropic, gemini', implode(', ', [$collection]));

        // Test with empty collection
        $emptyCollection = new ProviderCollection();
        $this->assertEquals('', (string) $emptyCollection);
        $this->assertEquals('', implode(', ', [$emptyCollection]));
    }

    public function testCollectionCanBeUsedDirectlyAsArray(): void
    {
        $provider1 = $this->createMock(ProviderInterface::class);
        $provider1->method('getId')->willReturn('openai');

        $provider2 = $this->createMock(ProviderInterface::class);
        $provider2->method('getId')->willReturn('anthropic');

        $collection = new ProviderCollection([$provider1, $provider2]);

        // Should work like an array in foreach
        $providers = [];
        foreach ($collection as $id => $provider) {
            $providers[$id] = $provider;
        }

        $this->assertCount(2, $providers);
        $this->assertArrayHasKey('openai', $providers);
        $this->assertArrayHasKey('anthropic', $providers);
        $this->assertSame($provider1, $providers['openai']);
        $this->assertSame($provider2, $providers['anthropic']);

        // Should work with array access
        $this->assertSame($provider1, $collection['openai']);
        $this->assertSame($provider2, $collection['anthropic']);
    }
}
