<?php

namespace Framework\Database\Seeds;

/**
 * Factory Class
 *
 * Simple factory for generating test data
 * Can be extended with Faker library for more realistic data
 */
class Factory
{
    /**
     * Factory definitions
     */
    private static array $definitions = [];

    /**
     * Define a factory
     */
    public static function define(string $name, callable $callback): void
    {
        static::$definitions[$name] = $callback;
    }

    /**
     * Make a single instance (returns array, doesn't save to DB)
     */
    public static function make(string $name, array $overrides = []): array
    {
        if (!isset(static::$definitions[$name])) {
            throw new \Exception("Factory not defined: {$name}");
        }

        $data = call_user_func(static::$definitions[$name]);

        return array_merge($data, $overrides);
    }

    /**
     * Make multiple instances
     */
    public static function makeMany(string $name, int $count, array $overrides = []): array
    {
        $results = [];

        for ($i = 0; $i < $count; $i++) {
            $results[] = static::make($name, $overrides);
        }

        return $results;
    }

    /**
     * Generate random string
     */
    public static function randomString(int $length = 10): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';

        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $string;
    }

    /**
     * Generate random email
     */
    public static function randomEmail(): string
    {
        return strtolower(static::randomString(8)) . '@example.com';
    }

    /**
     * Generate random number
     */
    public static function randomNumber(int $min = 1, int $max = 100): int
    {
        return rand($min, $max);
    }

    /**
     * Generate random decimal
     */
    public static function randomDecimal(int $min = 0, int $max = 1000, int $decimals = 2): float
    {
        return round($min + mt_rand() / mt_getrandmax() * ($max - $min), $decimals);
    }

    /**
     * Generate random date
     */
    public static function randomDate(string $format = 'Y-m-d H:i:s', string $start = '-1 year', string $end = 'now'): string
    {
        $startTimestamp = strtotime($start);
        $endTimestamp = strtotime($end);

        $randomTimestamp = rand($startTimestamp, $endTimestamp);

        return date($format, $randomTimestamp);
    }

    /**
     * Pick random element from array
     */
    public static function randomElement(array $array): mixed
    {
        return $array[array_rand($array)];
    }

    /**
     * Generate random boolean
     */
    public static function randomBoolean(): bool
    {
        return (bool) rand(0, 1);
    }

    /**
     * Generate UUID v4
     */
    public static function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    /**
     * Generate random paragraph
     */
    public static function randomText(int $sentences = 3): string
    {
        $texts = [
            'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
            'Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
            'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.',
            'Duis aute irure dolor in reprehenderit in voluptate velit esse.',
            'Excepteur sint occaecat cupidatat non proident, sunt in culpa.',
        ];

        $result = [];
        for ($i = 0; $i < $sentences; $i++) {
            $result[] = static::randomElement($texts);
        }

        return implode(' ', $result);
    }
}
