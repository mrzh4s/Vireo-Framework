<?php

namespace Framework\Validation\Rules;

use DateTime;

/**
 * AfterDate Rule - Value must be a date after a given date
 */
class AfterDate implements Rule
{
    public function passes(string $field, mixed $value, array $parameters, array $data): bool
    {
        if ($value === null || $value === '') {
            return true; // Let 'required' rule handle empty values
        }

        $compareDate = $parameters[0] ?? null;

        if ($compareDate === null) {
            return false;
        }

        $valueTimestamp = $value instanceof DateTime ? $value->getTimestamp() : strtotime($value);
        $compareTimestamp = $compareDate instanceof DateTime ? $compareDate->getTimestamp() : strtotime($compareDate);

        if ($valueTimestamp === false || $compareTimestamp === false) {
            return false;
        }

        return $valueTimestamp > $compareTimestamp;
    }

    public function message(string $field, array $parameters): string
    {
        $date = $parameters[0] ?? 'specified date';
        return "The {$field} must be a date after {$date}.";
    }
}
