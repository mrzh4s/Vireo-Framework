<?php

namespace Vireo\Framework\Database\ORM;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use ArrayIterator;

/**
 * Collection class for query results
 *
 * Provides chainable methods for working with arrays of data
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /**
     * Collection items
     */
    protected array $items = [];

    /**
     * Create a new collection
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Get all items in the collection
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the first item
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    /**
     * Get the last item
     */
    public function last(): mixed
    {
        return end($this->items) ?: null;
    }

    /**
     * Get an item by key
     */
    public function get(int $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * Check if collection is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Check if collection is not empty
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Count the number of items
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Map over the collection
     */
    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items));
    }

    /**
     * Filter the collection
     */
    public function filter(callable $callback = null): static
    {
        if ($callback === null) {
            return new static(array_filter($this->items));
        }

        return new static(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Reduce the collection to a single value
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Get the values of a given key
     */
    public function pluck(string $key): static
    {
        $results = [];

        foreach ($this->items as $item) {
            if (is_object($item)) {
                $results[] = $item->$key ?? null;
            } elseif (is_array($item)) {
                $results[] = $item[$key] ?? null;
            }
        }

        return new static($results);
    }

    /**
     * Chunk the collection into smaller collections
     */
    public function chunk(int $size): static
    {
        $chunks = [];
        foreach (array_chunk($this->items, $size, true) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * Take the first N items
     */
    public function take(int $limit): static
    {
        if ($limit < 0) {
            return new static(array_slice($this->items, $limit));
        }

        return new static(array_slice($this->items, 0, $limit));
    }

    /**
     * Skip the first N items
     */
    public function skip(int $offset): static
    {
        return new static(array_slice($this->items, $offset));
    }

    /**
     * Get unique items
     */
    public function unique(string|callable|null $key = null): static
    {
        if ($key === null) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $exists = [];
        $results = [];

        foreach ($this->items as $item) {
            if (is_callable($key)) {
                $id = $key($item);
            } elseif (is_object($item)) {
                $id = $item->$key ?? null;
            } else {
                $id = $item[$key] ?? null;
            }

            if (!in_array($id, $exists, true)) {
                $exists[] = $id;
                $results[] = $item;
            }
        }

        return new static($results);
    }

    /**
     * Sort the collection
     */
    public function sort(callable $callback = null): static
    {
        $items = $this->items;

        if ($callback === null) {
            sort($items);
        } else {
            usort($items, $callback);
        }

        return new static($items);
    }

    /**
     * Sort the collection in descending order
     */
    public function sortDesc(callable $callback = null): static
    {
        return $this->sort($callback ? function ($a, $b) use ($callback) {
            return $callback($b, $a);
        } : null);
    }

    /**
     * Sort by key
     */
    public function sortBy(string|callable $key, bool $descending = false): static
    {
        $items = $this->items;

        uasort($items, function ($a, $b) use ($key, $descending) {
            if (is_callable($key)) {
                $aValue = $key($a);
                $bValue = $key($b);
            } elseif (is_object($a)) {
                $aValue = $a->$key ?? null;
                $bValue = $b->$key ?? null;
            } else {
                $aValue = $a[$key] ?? null;
                $bValue = $b[$key] ?? null;
            }

            $result = $aValue <=> $bValue;

            return $descending ? -$result : $result;
        });

        return new static(array_values($items));
    }

    /**
     * Sort by key descending
     */
    public function sortByDesc(string|callable $key): static
    {
        return $this->sortBy($key, true);
    }

    /**
     * Reverse the collection
     */
    public function reverse(): static
    {
        return new static(array_reverse($this->items));
    }

    /**
     * Group by key
     */
    public function groupBy(string|callable $key): static
    {
        $results = [];

        foreach ($this->items as $item) {
            if (is_callable($key)) {
                $groupKey = $key($item);
            } elseif (is_object($item)) {
                $groupKey = $item->$key ?? null;
            } else {
                $groupKey = $item[$key] ?? null;
            }

            $results[$groupKey][] = $item;
        }

        return new static(array_map(fn($items) => new static($items), $results));
    }

    /**
     * Key the collection by a key
     */
    public function keyBy(string|callable $key): static
    {
        $results = [];

        foreach ($this->items as $item) {
            if (is_callable($key)) {
                $keyValue = $key($item);
            } elseif (is_object($item)) {
                $keyValue = $item->$key ?? null;
            } else {
                $keyValue = $item[$key] ?? null;
            }

            $results[$keyValue] = $item;
        }

        return new static($results);
    }

    /**
     * Concatenate values
     */
    public function implode(string $glue, string|null $key = null): string
    {
        if ($key === null) {
            return implode($glue, $this->items);
        }

        return implode($glue, $this->pluck($key)->all());
    }

    /**
     * Check if an item exists
     */
    public function contains(mixed $key, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            if (is_callable($key)) {
                foreach ($this->items as $item) {
                    if ($key($item)) {
                        return true;
                    }
                }
                return false;
            }

            return in_array($key, $this->items, true);
        }

        return $this->contains(fn($item) =>
            (is_object($item) ? ($item->$key ?? null) : ($item[$key] ?? null)) === $value
        );
    }

    /**
     * Execute a callback over each item
     */
    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Convert collection to array
     */
    public function toArray(): array
    {
        return array_map(function ($item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }

            return $item;
        }, $this->items);
    }

    /**
     * Convert collection to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Get items for JSON serialization
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get an iterator for the items
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    /**
     * ArrayAccess: Determine if an item exists at an offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * ArrayAccess: Get an item at a given offset
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    /**
     * ArrayAccess: Set the item at a given offset
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * ArrayAccess: Unset the item at a given offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * Convert the collection to its string representation
     */
    public function __toString(): string
    {
        return $this->toJson();
    }
}
