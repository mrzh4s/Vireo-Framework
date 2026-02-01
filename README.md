# Vireo Framework

A modern PHP framework implementing **Vertical Slice Architecture** with Inertia.js integration. Zero external dependencies, auto-discovery, and built-in RBAC.

## Features

- **Vertical Slice Architecture** - Organize code by feature, not by layer
- **Inertia.js Integration** - First-class support for React, Vue, and Svelte
- **Minimal Dependencies** - Only requires `symfony/mailer` for email
- **Built-in RBAC** - Role-based access control with hierarchy and attribute-based permissions
- **Fluent Query Builder** - Intuitive ORM with support for MySQL, PostgreSQL, and SQLite
- **PostGIS/Spatial Support** - Built-in spatial query builder for geospatial applications
- **Migration System** - Database versioning with rollback support
- **CLI Tools** - Powerful command-line interface for scaffolding and management
- **Validation** - Comprehensive validation rules with custom rule support
- **Session & Cookie Management** - Secure session handling out of the box
- **CSRF Protection** - Built-in cross-site request forgery protection
- **Blade-like Views** - Familiar templating syntax
- **File Storage** - Local and FTP storage drivers
- **Logging** - Configurable logging with multiple handlers

## Requirements

- PHP 8.4 or higher
- Composer

## Installation

```bash
composer require vireo/framework
```

## Quick Start

### Routing

Routes are automatically discovered from your `pages` directory following the Vertical Slice Architecture pattern.

```php
// Using helper functions
route('users.show', ['id' => 123]);  // Generate URL
redirect('dashboard');                // Redirect to named route
```

### Controllers

```php
<?php

namespace App\Features\Users;

use Framework\Http\Controller;

class ShowUser extends Controller
{
    public function __invoke(int $id)
    {
        $user = table('users')->where('id', $id)->first();

        // Return JSON for API requests
        if ($this->isApi()) {
            return $this->json(['user' => $user]);
        }

        // Return Inertia response for web
        return $this->inertia('Users/Show', ['user' => $user]);
    }
}
```

### Database Queries

```php
// Simple queries
$users = table('users')
    ->where('active', true)
    ->orderBy('created_at', 'desc')
    ->get();

// Joins and relationships
$posts = table('posts')
    ->join('users', 'posts.user_id', '=', 'users.id')
    ->select('posts.*', 'users.name as author')
    ->get();

// Spatial queries (PostGIS)
$nearby = spatial('locations')
    ->withinDistance('geom', $point, 1000)
    ->get();
```

### Validation

```php
$errors = validate($_POST, [
    'email' => ['required', 'email', 'unique:users,email'],
    'password' => ['required', 'min:8', 'confirmed'],
    'age' => ['numeric', 'min:18'],
]);

if (!empty($errors)) {
    return $this->validationError($errors);
}
```

### Inertia.js Integration

```php
// Render an Inertia component
inertia('Dashboard/Index', [
    'stats' => $stats,
    'recentActivity' => inertia_lazy(fn() => $this->getRecentActivity()),
]);

// Flash messages
inertia_flash('success', 'Profile updated successfully!');

// Validation errors
inertia_errors(['email' => 'This email is already taken']);
```

### Permissions (RBAC)

```php
// Check permissions
if (can('users.edit')) {
    // User can edit users
}

// Check with attribute
if (can('projects.edit', null, 'department', 'engineering')) {
    // User can edit projects in engineering department
}

// Multiple permission check
if (can_any(['users.view', 'users.edit'])) {
    // User has at least one permission
}
```

### Migrations

```bash
# Create a migration
php vireo make:migration create_users_table

# Run migrations
php vireo migrate

# Rollback last migration
php vireo migrate:rollback

# Fresh migration (drop all and re-run)
php vireo migrate:fresh
```

```php
<?php

use Framework\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $this->drop('users');
    }
};
```

### CLI Commands

```bash
# List all commands
php vireo list

# Create a new feature
php vireo make:feature UserProfile

# Create a middleware
php vireo make:middleware AuthMiddleware

# Create a seeder
php vireo make:seeder UsersSeeder

# Run seeders
php vireo db:seed

# Start development server
php vireo serve

# Clear cache
php vireo cache:clear
```

### Configuration

Configuration files are loaded from the `config` directory:

```php
// Get config value
$appName = config('app.name');
$dbHost = config('database.connections.mysql.host');

// Get with default
$debug = config('app.debug', false);
```

### Environment Variables

```php
// Get environment variable
$apiKey = env('API_KEY');

// With default value
$debug = env('APP_DEBUG', false);
```

### Sessions

```php
// Set session value
session_set('user_id', 123);

// Get session value
$userId = session_get('user_id');

// Check if exists
if (session_has('user_id')) {
    // ...
}

// Remove session value
session_forget('user_id');
```

### Logging

```php
// Log messages at different levels
log_debug('Debug information', ['context' => $data]);
log_info('User logged in', ['user_id' => $userId]);
log_warning('Deprecated feature used');
log_error('Something went wrong', ['exception' => $e->getMessage()]);
```

### Storage

```php
// Store a file
storage()->put('uploads/avatar.jpg', $fileContents);

// Get file contents
$contents = storage()->get('uploads/avatar.jpg');

// Check if file exists
if (storage()->exists('uploads/avatar.jpg')) {
    // ...
}

// Delete a file
storage()->delete('uploads/avatar.jpg');
```

## Directory Structure

```
app/
├── Features/           # Vertical slices organized by feature
│   ├── Auth/
│   │   ├── Login.php
│   │   ├── Register.php
│   │   └── Logout.php
│   └── Users/
│       ├── Index.php
│       ├── Show.php
│       └── Store.php
├── Middleware/
└── Models/
config/
├── app.php
├── database.php
└── Permissions.php
database/
├── migrations/
└── seeds/
resources/
└── views/
storage/
└── logs/
```

## Configuration Files

### Permissions Configuration

```php
// config/Permissions.php
return [
    'roles' => [
        'admin' => ['manager', 'user'],
        'manager' => ['user'],
        'user' => [],
    ],
    'permissions' => [
        'users.view' => '*',                    // Public access
        'users.create' => ['admin', 'manager'],
        'users.edit' => ['admin', 'manager'],
        'users.delete' => ['admin'],
    ],
    'super_admin' => [
        'roles' => ['superadmin'],
        'bypass_all' => true,
    ],
];
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Author

- **mrzh4s** - [GitHub](https://github.com/mrzh4s)

## Support

- [GitHub Issues](https://github.com/mrzh4s/vireo-framework/issues)
- [Documentation](https://github.com/mrzh4s/vireo-framework#readme)