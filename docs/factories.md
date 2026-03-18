# Factories

All models have corresponding factories for testing and seeding.

---

## PostFactory

**File**: `database/factories/PostFactory.php`

### Default State

```php
$post = Post::factory()->create();
// Creates a post with random status and published_at
```

### States

| State | Description |
|-------|-------------|
| `published()` | Status = Published, with published_at date |
| `draft()` | Status = Draft, published_at = null |
| `archived()` | Status = Archived, published_at = null |
| `trashed()` | Soft deleted with deleted_at date |

### Usage Examples

```php
use App\Models\Post;
use App\PostStatus;

// Published post
$published = Post::factory()->published()->create();

// Draft post
$draft = Post::factory()->draft()->create();

// Archived post
$archived = Post::factory()->archived()->create();

// Soft deleted post
$deleted = Post::factory()->trashed()->create();

// Published post with specific title
$post = Post::factory()->published()->create([
    'title' => 'My Specific Title',
]);

// Multiple posts
$posts = Post::factory()->count(10)->published()->create();

// Post with relationships
$post = Post::factory()
    ->for(User::factory()->create())
    ->for(Category::factory()->create())
    ->has(Tag::factory()->count(3))
    ->published()
    ->create();
```

---

## CategoryFactory

**File**: `database/factories/CategoryFactory.php`

### Default State

```php
$category = Category::factory()->create();
// name = "Short Stories" (unique, 2 words)
// slug = "short-stories"
// description = random paragraph or null
```

### States

| State | Description |
|-------|-------------|
| `trashed()` | Soft deleted with deleted_at date |

### Usage Examples

```php
use App\Models\Category;

// Create category
$category = Category::factory()->create();

// Create with specific name
$category = Category::factory()->create([
    'name' => 'Reflections',
    'slug' => 'reflections',
]);

// Create soft deleted category
$deleted = Category::factory()->trashed()->create();

// Create with posts
$category = Category::factory()
    ->has(Post::factory()->count(5))
    ->create();
```

---

## TagFactory

**File**: `database/factories/TagFactory.php`

### Default State

```php
$tag = Tag::factory()->create();
// name = "scifi" (unique word)
// slug = "scifi"
```

### States

| State | Description |
|-------|-------------|
| `trashed()` | Soft deleted with deleted_at date |

### Usage Examples

```php
use App\Models\Tag;

// Create tag
$tag = Tag::factory()->create();

// Create with specific name
$tag = Tag::factory()->create([
    'name' => 'Mindfulness',
    'slug' => 'mindfulness',
]);

// Create multiple tags
$tags = Tag::factory()->count(10)->create();

// Create tags with posts
$tag = Tag::factory()
    ->has(Post::factory()->count(3))
    ->create();
```

---

## UserFactory

**File**: `database/factories/UserFactory.php` (Laravel default)

### Usage Examples

```php
use App\Models\User;

// Create user
$user = User::factory()->create();

// Create with posts
$user = User::factory()
    ->has(Post::factory()->count(5))
    ->create();
```

---

## Seeding with Factories

### DatabaseSeeder

```php
// database/seeders/DatabaseSeeder.php

public function run(): void
{
    // Create users
    $users = User::factory(10)->create();
    
    // Create categories
    $categories = Category::factory(5)->create();
    
    // Create tags
    $tags = Tag::factory(20)->create();
    
    // Create posts with random relationships
    Post::factory(50)
        ->recycle($users)
        ->recycle($categories)
        ->create()
        ->each(function ($post) use ($tags) {
            $post->tags()->attach(
                $tags->random(rand(1, 5))->pluck('id')
            );
        });
}
```

### Testing with Factories

```php
use App\Models\Post;
use App\PostStatus;

class PostTest extends TestCase
{
    public function test_published_posts_are_visible()
    {
        $post = Post::factory()->published()->create();
        
        $response = $this->get('/posts/' . $post->slug);
        
        $response->assertOk();
        $response->assertSee($post->title);
    }
    
    public function test_draft_posts_are_not_visible()
    {
        $post = Post::factory()->draft()->create();
        
        $response = $this->get('/posts/' . $post->slug);
        
        $response->assertNotFound();
    }
}
```

---

## Factory Best Practices

1. **Use states for common scenarios**:
   ```php
   // Good
   Post::factory()->published()->create();
   
   // Avoid
   Post::factory()->create(['status' => 'published', 'published_at' => now()]);
   ```

2. **Recycle models for relationships**:
   ```php
   $user = User::factory()->create();
   
   Post::factory(10)
       ->recycle($user)
       ->create();
   ```

3. **Use `make()` instead of `create()` when you don't need persistence**:
   ```php
   $post = Post::factory()->make(); // Not saved to DB
   ```
