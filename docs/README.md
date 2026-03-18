# My Blog Application Documentation

Welcome to the documentation for the My Blog Laravel application.

## Quick Links

| Document | Description |
|----------|-------------|
| [Database Schema](db.md) | Table structures, fields, and relationships |
| [Models](models.md) | Eloquent models, relationships, and usage |
| [Enums](enums.md) | PostStatus enum and Filament integration |
| [Factories](factories.md) | Testing factories and seeding |
| [PowerJoins](power-joins.md) | Advanced join query examples |

---

## Overview

A Laravel 13 blog application with Filament v5 admin panel, featuring a clean architecture for managing posts, categories, and tags.

## Tech Stack

- **PHP**: 8.4.16
- **Laravel**: 13.x
- **Filament**: v5 (Admin Panel)
- **Livewire**: v3
- **Tailwind CSS**: v4
- **Database**: PostgreSQL

## Key Packages

| Package | Purpose |
|---------|---------|
| `filament/filament` | Admin panel framework |
| `kirschbaum-development/eloquent-power-joins` | Advanced Eloquent joins |
| `laravel/sanctum` | API authentication |
| `laravel/scout` | Full-text search |

## Project Structure

```
app/
├── Models/           # Eloquent models with relationships
├── PostStatus.php    # Enum for post statuses (Filament-ready)
database/
├── migrations/       # Database schema
├── factories/        # Model factories for testing
└── seeders/          # Database seeders
```

## Features

- **Posts**: Create, edit, and manage blog posts with Markdown content
- **Categories**: Organize posts into categories (one per post)
- **Tags**: Flexible tagging system (many-to-many)
- **Soft Deletes**: All models support soft deletion
- **Slug-based URLs**: SEO-friendly URLs using slugs
- **Post Statuses**: Draft, Published, Archived workflow
- **PowerJoins**: Efficient relationship-based joins

## Quick Start

```bash
# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate --seed

# Development server
composer run dev
```

## Access

- **App**: http://my-blog.test (via Laravel Herd)
- **Admin Panel**: http://my-blog.test/admin

## Documentation

- [Database Schema](db.md) - Table structures and relationships
- [Models](models.md) - Model relationships and methods
- [Enums](enums.md) - Enum classes and Filament integration
- [Factories](factories.md) - Testing data factories
- [PowerJoins](power-joins.md) - Advanced join query examples
