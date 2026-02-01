<?php

namespace Framework\Cli;

/**
 * Table - ASCII table formatter
 *
 * Renders tabular data in a formatted ASCII table with borders,
 * dynamic column widths, and proper alignment.
 */
class Table
{
    /**
     * Output instance
     */
    private Output $output;

    /**
     * Table style
     */
    private string $style = 'default';

    /**
     * Create Table instance
     *
     * @param Output $output Output instance
     */
    public function __construct(Output $output)
    {
        $this->output = $output;
    }

    /**
     * Render a table
     *
     * @param array<string> $headers Column headers
     * @param array<array<string>> $rows Table rows
     * @return void
     */
    public function render(array $headers, array $rows): void
    {
        if (empty($headers)) {
            return;
        }

        // Calculate column widths
        $widths = $this->calculateColumnWidths($headers, $rows);

        // Render top border
        $this->renderBorder($widths, 'top');

        // Render headers
        $this->renderRow($headers, $widths, true);

        // Render separator
        $this->renderBorder($widths, 'middle');

        // Render rows
        foreach ($rows as $row) {
            // Ensure row has same number of columns as headers
            $row = array_pad($row, count($headers), '');
            $this->renderRow($row, $widths, false);
        }

        // Render bottom border
        $this->renderBorder($widths, 'bottom');
    }

    /**
     * Calculate column widths
     *
     * @param array<string> $headers Headers
     * @param array<array<string>> $rows Rows
     * @return array<int> Column widths
     */
    private function calculateColumnWidths(array $headers, array $rows): array
    {
        $widths = array_map('strlen', $headers);

        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $cellLength = strlen((string)$cell);
                if (isset($widths[$index]) && $cellLength > $widths[$index]) {
                    $widths[$index] = $cellLength;
                } elseif (!isset($widths[$index])) {
                    $widths[$index] = $cellLength;
                }
            }
        }

        return $widths;
    }

    /**
     * Render a border line
     *
     * @param array<int> $widths Column widths
     * @param string $position Border position: 'top', 'middle', 'bottom'
     * @return void
     */
    private function renderBorder(array $widths, string $position): void
    {
        $parts = [];

        foreach ($widths as $width) {
            $parts[] = str_repeat('-', $width + 2);
        }

        $left = match ($position) {
            'top' => '┌',
            'middle' => '├',
            'bottom' => '└',
            default => '├',
        };

        $right = match ($position) {
            'top' => '┐',
            'middle' => '┤',
            'bottom' => '┘',
            default => '┤',
        };

        $join = match ($position) {
            'top' => '┬',
            'middle' => '┼',
            'bottom' => '┴',
            default => '┼',
        };

        $this->output->writeln($left . implode($join, $parts) . $right);
    }

    /**
     * Render a table row
     *
     * @param array<string> $cells Cell values
     * @param array<int> $widths Column widths
     * @param bool $isHeader Is this a header row
     * @return void
     */
    private function renderRow(array $cells, array $widths, bool $isHeader): void
    {
        $parts = [];

        foreach ($cells as $index => $cell) {
            $width = $widths[$index] ?? 0;
            $cellValue = (string)$cell;
            $padding = str_repeat(' ', $width - strlen($cellValue));

            if ($isHeader) {
                $parts[] = ' ' . $this->output->getColor()->bold($cellValue) . $padding . ' ';
            } else {
                $parts[] = ' ' . $cellValue . $padding . ' ';
            }
        }

        $this->output->writeln('│' . implode('│', $parts) . '│');
    }

    /**
     * Set table style
     *
     * @param string $style Style name
     * @return self
     */
    public function setStyle(string $style): self
    {
        $this->style = $style;
        return $this;
    }
}
