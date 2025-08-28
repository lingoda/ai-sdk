<?php

declare(strict_types = 1);

namespace Lingoda\AiSdk\Provider;

use Lingoda\AiSdk\ProviderInterface;

/**
 * @implements \IteratorAggregate<string, ProviderInterface>
 * @implements \ArrayAccess<string, ProviderInterface>
 */
class ProviderCollection implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * @var ProviderInterface[]
     */
    private array $providers = [];

    /**
     * @param ProviderInterface[] $providers
     */
    public function __construct(array $providers = [])
    {
        foreach ($providers as $provider) {
            $this->add($provider);
        }
    }

    public function add(ProviderInterface $provider): void
    {
        $this->providers[$provider->getId()] = $provider;
    }

    /**
     * @return ProviderInterface[]
     */
    public function getProviders(): array
    {
        return array_values($this->providers);
    }

    /**
     * @return string[]
     */
    public function getIds(): array
    {
        return array_keys($this->providers);
    }

    public function has(string $providerId): bool
    {
        return isset($this->providers[$providerId]);
    }

    public function get(string $providerId): ?ProviderInterface
    {
        return $this->providers[$providerId] ?? null;
    }

    public function count(): int
    {
        return count($this->providers);
    }

    /**
     * @return \ArrayIterator<string, ProviderInterface>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->providers);
    }

    public function isEmpty(): bool
    {
        return empty($this->providers);
    }

    /**
     * ArrayAccess implementation
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->providers[$offset]);
    }

    /**
     * @param string $offset
     */
    public function offsetGet(mixed $offset): ?ProviderInterface
    {
        return $this->providers[$offset] ?? null;
    }

    /**
     * @param string|null $offset
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!$value instanceof ProviderInterface) {
            throw new \InvalidArgumentException('Value must be an instance of ProviderInterface');
        }

        if ($offset === null) {
            $this->add($value);
        } else {
            $this->providers[$offset] = $value;
        }
    }

    /**
     * @param string $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->providers[$offset]);
    }

    /**
     * Convert to string for use with implode()
     * Returns comma-separated list of provider IDs
     */
    public function __toString(): string
    {
        return implode(', ', $this->getIds());
    }
}
