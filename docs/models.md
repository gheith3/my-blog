# Models

## Overview

All models use:
- **SoftDeletes**: For safe data removal
- **HasFactory**: For testing with factories
- **Type-safe relationships**: With generic annotations

---

## Post

**File**: `app/Models/Post.php`

### Attributes

| Column | Type | Description |
|--------|------|-------------|
| `user_id` | foreignId | Author of the post |
| `category_id` | foreignId | Category (one per post) |
| `title` | string | Post title |
| `slug` | string | URL-friendly identifier (unique) |
| `content` | text | Markdown content |
| `status` | PostStatus enum | draft, published, archived |
| `published_at` | datetime | Publication date |

### Relationships

```php
// Belongs to
$post->user();      // User
$post->category();  // Category

// Belongs to many
$post->tags();      // Tag[]
```

### Methods

```php
// Check if post is published
$post->isPublished(); // bool
```

### Usage Examples

```php
// Create a published post
$post = Post::factory()->published()->create([
    'title' => 'My Story',
    'content' => '# Hello World',
]);

// Attach tags
$post->tags()->attach([$tag1->id, $tag2->id]);

// Get posts with relationships
$posts = Post::with(['user', 'category', 'tags'])
    ->where('status', PostStatus::Published)
    ->get();
```

---

## Category

**File**: `app/Models/Category.php`

### Attributes

| Column | Type | Description |
|--------|------|-------------|
| `name` | string | Category name |
| `slug` | string | URL-friendly identifier (unique) |
| `description` | text | Optional description |

### Relationships

```php
// Has many
$category->posts(); // Post[]
```

### Usage Examples

```php
// Create category
$category = Category::create([
    'name' => 'Short Stories',
    'slug' => 'short-stories',
]);

// Get posts in category
$posts = $category->posts()->published()->get();
```

---

## Tag

**File**: `app/Models/Tag.php`

### Attributes

| Column | Type | Description |
|--------|------|-------------|
| `name` | string | Tag name |
| `slug` | string | URL-friendly identifier (unique) |

### Relationships

```php
// Belongs to many
$tag->posts(); // Post[]
```

### Usage Examples

```php
// Create tag
$tag = Tag::create([
    'name' => 'Sci-Fi',
    'slug' => 'sci-fi',
]);

// Get posts with this tag
$posts = $tag->posts()->published()->get();

// Get popular tags with post count
$popularTags = Tag::withCount('posts')
    ->orderByDesc('posts_count')
    ->limit(10)
    ->get();
```

---

## User

**File**: `app/Models/User.php`

### Attributes

| Column | Type | Description |
|--------|------|-------------|
| `name` | string | User name |
| `email` | string | Email (unique) |
| `password` | string | Hashed password |

### Relationships

```php
// Has many
$user->posts(); // Post[]
```

### Usage Examples

```php
// Get user's posts
$posts = $user->posts()->latest()->get();

// Get published posts count
$publishedCount = $user->posts()
    ->where('status', PostStatus::Published)
    ->count();
```

---

## Soft Deletes

All models support soft deletion. Deleted records are retained in the database with a `deleted_at` timestamp.

```php
// Soft delete
$post->delete();

// Restore
$post->restore();

// Force delete (permanent)
$post->forceDelete();

// Query including soft deleted
Post::withTrashed()->get();

// Query only soft deleted
Post::onlyTrashed()->get();
```

---

## Eager Loading

Always eager load relationships to avoid N+1 queries:

```php
// Good - single query with joins
$posts = Post::with(['user', 'category', 'tags'])->get();

// Bad - causes N+1 queries
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->user->name; // Additional query per post
}
```
