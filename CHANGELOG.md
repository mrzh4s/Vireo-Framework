# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-02-01

### Added
- **Core Framework**
  - Bootstrap with auto-discovery
  - Configuration management with dot notation
  - Environment variables handler

- **HTTP Layer**
  - Router with automatic route discovery
  - Request/Response handling
  - Controller base class
  - Session management
  - cURL wrapper

- **Database**
  - Fluent Query Builder with MySQL, PostgreSQL, and SQLite support
  - Spatial Query Builder for PostGIS/geospatial applications
  - Migration system with rollback support
  - Database seeding with factories

- **ORM**
  - Grammar classes for MySQL, PostgreSQL, and SQLite
  - Relationship support
  - Eager loading

- **Cache System**
  - Cache manager with multiple drivers
  - Drivers: Array, Database, File, Memcache, Memcached, Redis
  - Session handlers for all cache drivers

- **Email System**
  - SMTP transport via Symfony Mailer
  - Email queue processing
  - Blade template integration
  - Open/click tracking
  - Attachment support

- **Validation**
  - 20+ built-in validation rules
  - File validation
  - Rate limiter
  - Sanitizer
  - Custom rule support

- **Security**
  - CSRF token protection
  - Secure cookie handling
  - Role-Based Access Control (RBAC) with hierarchy
  - Attribute-based permissions

- **Storage**
  - Storage manager with multiple drivers
  - Drivers: Local, S3, FTP, SFTP

- **View Rendering**
  - Blade template engine
  - Inertia.js integration (React, Vue, Svelte)

- **CLI Tools**
  - Console interface
  - 15+ built-in commands
  - Command registry
  - Argument parser
  - Colored output, tables, spinners

- **Logging**
  - Logger with multiple levels
  - Handlers: File, Database, Syslog

- **Helper Functions**
  - 21 helper files with global functions
  - Covers all major framework features

[1.0.0]: https://github.com/mrzh4s/vireo-framework/releases/tag/v1.0.0
