<?php

namespace Framework\Validation\Rules;

use Framework\Database\DB;
use Exception;

/**
 * Unique Rule - Value must be unique in database table
 * Usage: 'unique:users,email' or 'unique:users,email,10' (ignore ID 10)
 */
class Unique implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let 'required' rule handle empty values
        }

        $table = $parameters[0] ?? null;
        $column = $parameters[1] ?? $field;
        $ignoreId = $parameters[2] ?? null;

        if ($table === null) {
            return false;
        }

        try {
            $db = DB::connection();

            if ($ignoreId !== null) {
                // Ignore a specific ID (useful for updates)
                $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ? AND id != ?");
                $stmt->execute([$value, $ignoreId]);
            } else {
                $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
                $stmt->execute([$value]);
            }

            $count = (int) $stmt->fetchColumn();

            return $count === 0; // Must be unique (count = 0)

        } catch (Exception $e) {
            error_log("Unique validation error: " . $e->getMessage());
            return false; // Fail validation if query fails
        }
    }

    public function message(string $field, array $parameters): string
    {
        return "The {$field} has already been taken.";
    }
}
