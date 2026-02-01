<?php

namespace Vireo\Framework\Validation\Rules;

use Vireo\Framework\Database\DB;
use Exception;

/**
 * Exists Rule - Value must exist in database table
 * Usage: 'exists:users,id' or 'exists:users' (defaults to 'id' column)
 */
class Exists implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let 'required' rule handle empty values
        }

        $table = $parameters[0] ?? null;
        $column = $parameters[1] ?? 'id';

        if ($table === null) {
            return false;
        }

        try {
            $db = DB::connection();
            $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
            $stmt->execute([$value]);

            $count = (int) $stmt->fetchColumn();

            return $count > 0; // Must exist (count > 0)

        } catch (Exception $e) {
            error_log("Exists validation error: " . $e->getMessage());
            return false; // Fail validation if query fails
        }
    }

    public function message(string $field, array $parameters): string
    {
        return "The selected {$field} is invalid.";
    }
}
