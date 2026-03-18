# PowerJoins

This application uses `kirschbaum-development/eloquent-power-joins` v4.x for efficient relationship-based joins.

> **Note**: v4.x uses Laravel macros - no trait needed. Methods are available on all Eloquent models automatically.

---

## Basic Joins

### joinRelationship()

Join tables using relationship names instead of manual join clauses:

```php
use App\Models\Post;

// Without PowerJoins
Post::select('posts.*')
    ->join('categories', 'posts.category_id', '=', 'categories.id')
    ->where('categories.slug', 'short-stories');

// With PowerJoins
Post::joinRelationship('category')
    ->where('categories.slug', 'short-stories');
```

### Join Types

```php
// Inner join (default)
Post::joinRelationship('category');

// Left join
Post::leftJoinRelationship('category');

// Right join
Post::rightJoinRelationship('category');
```

---

## Nested Joins

Join through multiple relationships using dot notation:

```php
// Get posts with their category's posts (nested)
Post::joinRelationship('category.posts');

// Get posts with user and category
Post::joinRelationship('user')
    ->joinRelationship('category');
```

---

## Joins with Conditions

### Basic Callback

```php
use App\PostStatus;

Post::joinRelationship('category', function ($join) {
    $join->where('categories.slug', 'reflections');
});
```

### Using Model Scopes

You can use model scopes inside join callbacks:

```php
// Assuming Post has a scopePublished() method
Post::joinRelationship('category', function ($join) {
    // Note: $join instance can access Post scopes
    $join->where('posts.status', 'published');
});
```

### Nested Conditions

```php
Post::joinRelationship('category.posts', [
    'category' => function ($join) {
        $join->where('categories.slug', 'short-stories');
    },
    'posts' => function ($join) {
        $join->where('posts.status', 'published');
    },
]);
```

---

## BelongsToMany Joins

For many-to-many relationships (Post ↔ Tag), both tables are joined:

```php
// Joins: posts -> post_tag (pivot) -> tags
Post::joinRelationship('tags');

// With conditions on pivot and related table
Post::joinRelationship('tags', [
    'tags' => [
        'tags' => function ($join) {
            $join->where('tags.slug', 'sci-fi');
        },
        'post_tag' => function ($join) {
            // Conditions on pivot table
        },
    ],
]);
```

---

## Aliases

When joining the same table multiple times:

```php
// Using automatic aliasing
Post::joinRelationshipUsingAlias('category.parent');

// With custom alias
Post::joinRelationshipUsingAlias('category', 'cat');

// In nested joins
Post::joinRelationship('category.posts', [
    'category' => function ($join) {
        $join->as('cat');
    },
    'posts' => function ($join) {
        $join->as('cat_posts');
    },
]);
```

---

## Querying Relationship Existence

Use joins instead of `where exists` for better performance:

### Laravel Native (where exists)

```php
Post::has('tags');                      // Has any tags
Post::has('tags', '>', 3);              // Has more than 3 tags
Post::whereHas('tags', function ($q) {  // Has specific tag
    $q->where('slug', 'sci-fi');
});
Post::doesntHave('tags');               // Has no tags
```

### PowerJoins Equivalent (using joins)

```php
Post::powerJoinHas('tags');
Post::powerJoinHas('tags', '>', 3);
Post::powerJoinWhereHas('tags', function ($join) {
    $join->where('tags.slug', 'sci-fi');
});
Post::powerJoinDoesntHave('tags');
```

### Nested Existence

```php
// Posts where author's posts have comments
Post::powerJoinHas('user.posts.comments');
```

---

## Ordering

### Order by Related Column

```php
// Order posts by category name
Post::orderByPowerJoins('category.name')->get();

// Order by user name
Post::orderByPowerJoins('user.name', 'desc')->get();

// Order by tag name (through many-to-many)
Post::orderByPowerJoins('tags.name')->get();
```

### Order by Aggregates

```php
use App\Models\Category;

// Categories with most posts (highest count first)
Category::orderByPowerJoinsCount('posts.id', 'desc')->get();

// Order by average
Category::orderByPowerJoinsAvg('posts.rating', 'desc')->get();

// Order by sum
Category::orderByPowerJoinsSum('posts.views', 'desc')->get();

// Left join variants (include categories with no posts)
Category::orderByLeftPowerJoinsCount('posts.id', 'desc')->get();
```

---

## Soft Deletes

PowerJoins automatically handles soft deletes:

```php
// Automatically excludes soft deleted records
Post::joinRelationship('category');

// Include soft deleted in join
Post::joinRelationship('category', function ($join) {
    $join->withTrashed();
});

// Only soft deleted
Post::joinRelationship('category', function ($join) {
    $join->onlyTrashed();
});
```

---

## Real-World Examples

### Get Published Posts with Category and Author

```php
use App\Models\Post;
use App\PostStatus;

$posts = Post::select([
        'posts.*',
        'categories.name as category_name',
        'categories.slug as category_slug',
        'users.name as author_name',
    ])
    ->joinRelationship('category')
    ->joinRelationship('user')
    ->where('posts.status', PostStatus::Published)
    ->whereNotNull('posts.published_at')
    ->orderByDesc('posts.published_at')
    ->paginate(10);
```

### Get Posts by Tag with Efficient Join

```php
$posts = Post::select('posts.*')
    ->joinRelationship('tags', function ($join) {
        $join->where('tags.slug', 'sci-fi');
    })
    ->where('posts.status', PostStatus::Published)
    ->distinct()
    ->get();
```

### Categories with Post Count (Ordered)

```php
$categories = Category::select([
        'categories.*',
        DB::raw('COUNT(posts.id) as posts_count'),
    ])
    ->leftJoinRelationship('posts')
    ->whereNull('posts.deleted_at') // Respect soft deletes
    ->groupBy('categories.id')
    ->orderByDesc('posts_count')
    ->get();
```

### Get Popular Tags with Post Count

```php
$tags = Tag::select([
        'tags.*',
        DB::raw('COUNT(DISTINCT posts.id) as posts_count'),
    ])
    ->joinRelationship('posts')
    ->where('posts.status', PostStatus::Published)
    ->groupBy('tags.id')
    ->orderByDesc('posts_count')
    ->limit(10)
    ->get();
```

---

## Performance Tips

1. **Use joins over `where exists` for large datasets**:
   ```php
   // Better for large tables
   Post::powerJoinHas('tags');
   
   // vs
   Post::has('tags'); // Uses where exists
   ```

2. **Select specific columns** to avoid data conflicts:
   ```php
   Post::select(['posts.*', 'categories.name'])
       ->joinRelationship('category');
   ```

3. **Use `distinct()` with many-to-many joins**:
   ```php
   Post::select('posts.*')
       ->joinRelationship('tags')
       ->distinct()
       ->get();
   ```

4. **Add indexes on foreign keys and slug columns** (already done in migrations)
