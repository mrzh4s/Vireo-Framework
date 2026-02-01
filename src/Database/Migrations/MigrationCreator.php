<?php

namespace Framework\Database\Migrations;

use Exception;

/**
 * Migration Creator
 *
 * Generates migration files from stubs/templates
 */
class MigrationCreator
{
    /**
     * Migrations directory
     */
    private string $migrationsPath;

    public function __construct()
    {
        $this->migrationsPath = ROOT_PATH . '/Infrastructure/Persistence/Migrations';
        $this->ensureMigrationsDirectory();
    }

    /**
     * Create migrations directory if it doesn't exist
     */
    private function ensureMigrationsDirectory(): void
    {
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }
    }

    /**
     * Create a new migration file
     */
    public function create(string $name, ?string $table = null, bool $create = false): array
    {
        $fileName = $this->generateFileName($name);
        $filePath = $this->migrationsPath . '/' . $fileName;

        if (file_exists($filePath)) {
            return [
                'success' => false,
                'error' => 'Migration already exists'
            ];
        }

        $className = $this->getClassName($name);
        $stub = $this->getStub($table, $create);
        $content = $this->populateStub($stub, $className, $table);

        if (file_put_contents($filePath, $content) === false) {
            return [
                'success' => false,
                'error' => 'Failed to create migration file'
            ];
        }

        return [
            'success' => true,
            'file' => $fileName,
            'path' => $filePath,
            'class' => $className
        ];
    }

    /**
     * Generate timestamped filename
     */
    private function generateFileName(string $name): string
    {
        $timestamp = date('Y_m_d_His');
        $name = $this->sanitizeName($name);
        return "{$timestamp}_{$name}.php";
    }

    /**
     * Get class name from migration name
     */
    private function getClassName(string $name): string
    {
        $name = $this->sanitizeName($name);
        $parts = explode('_', $name);
        return implode('', array_map('ucfirst', $parts));
    }

    /**
     * Sanitize migration name
     */
    private function sanitizeName(string $name): string
    {
        // Convert spaces to underscores
        $name = str_replace(' ', '_', $name);

        // Remove special characters
        $name = preg_replace('/[^a-zA-Z0-9_]/', '', $name);

        // Convert to snake_case
        $name = strtolower($name);

        return $name;
    }

    /**
     * Get appropriate stub template
     */
    private function getStub(?string $table, bool $create): string
    {
        if ($table !== null) {
            return $create ? $this->getCreateTableStub() : $this->getUpdateTableStub();
        }

        return $this->getBlankStub();
    }

    /**
     * Populate stub with variables
     */
    private function populateStub(string $stub, string $className, ?string $table): string
    {
        $replacements = [
            '{{class}}' => $className,
            '{{table}}' => $table ?? 'table_name',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    /**
     * Get blank migration stub
     */
    private function getBlankStub(): string
    {
        return <<<'PHP'
<?php

use Framework\Database\Migrations\Migration;
use Framework\Database\Migrations\Schema;
use Framework\Database\Migrations\Blueprint;

/**
 * Migration: {{class}}
 */
class {{class}} extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        //
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        //
    }
}
PHP;
    }

    /**
     * Get create table stub
     */
    private function getCreateTableStub(): string
    {
        return <<<'PHP'
<?php

use Framework\Database\Migrations\Migration;
use Framework\Database\Migrations\Schema;
use Framework\Database\Migrations\Blueprint;

/**
 * Migration: {{class}}
 */
class {{class}} extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        Schema::create('{{table}}', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        Schema::dropIfExists('{{table}}');
    }
}
PHP;
    }

    /**
     * Get update table stub
     */
    private function getUpdateTableStub(): string
    {
        return <<<'PHP'
<?php

use Framework\Database\Migrations\Migration;
use Framework\Database\Migrations\Schema;
use Framework\Database\Migrations\Blueprint;

/**
 * Migration: {{class}}
 */
class {{class}} extends Migration
{
    /**
     * Run the migration
     */
    public function up(): void
    {
        Schema::table('{{table}}', function (Blueprint $table) {
            //
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        Schema::table('{{table}}', function (Blueprint $table) {
            //
        });
    }
}
PHP;
    }

    /**
     * Get migration path
     */
    public function getMigrationsPath(): string
    {
        return $this->migrationsPath;
    }
}
