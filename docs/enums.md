# Enums

## PostStatus

**File**: `app/Enums/PostStatus.php`

Filament-ready enum implementing `HasLabel`, `HasColor`, and `HasIcon` interfaces for seamless admin panel integration.

### Cases

| Case | Value | Label | Color | Icon |
|------|-------|-------|-------|------|
| `Draft` | `draft` | Draft | gray | heroicon-m-pencil |
| `Published` | `published` | Published | success | heroicon-m-check-circle |
| `Archived` | `archived` | Archived | danger | heroicon-m-archive-box |

### Usage in Models

```php
use App\Enums\PostStatus;

// Cast in model
protected function casts(): array
{
    return [
        'status' => PostStatus::class,
    ];
}

// Usage
$post->status = PostStatus::Published;
$post->save();

// Comparison
if ($post->status === PostStatus::Published) {
    // Post is published
}

// Check cases
$isDraft = $post->status->is(PostStatus::Draft);
```

### Filament Integration

In Filament forms and tables:

```php
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Select;

// Table column with badge
TextColumn::make('status')
    ->badge()
    ->icon(fn (Post $record) => $record->status->getIcon())
    ->color(fn (Post $record) => $record->status->getColor());

// Form select
Select::make('status')
    ->options(PostStatus::class)
    ->native(false);
```

### Enum Methods

```php
// Get label
PostStatus::Published->getLabel(); // 'Published'

// Get color
PostStatus::Published->getColor(); // 'success'

// Get icon
PostStatus::Published->getIcon(); // 'heroicon-m-check-circle'

// Get all cases
$cases = PostStatus::cases();
// [PostStatus::Draft, PostStatus::Published, PostStatus::Archived]

// From value
$status = PostStatus::from('published'); // PostStatus::Published

// Try from (returns null if invalid)
$status = PostStatus::tryFrom('unknown'); // null
```

### Querying with Enums

```php
use App\PostStatus;

// Filter by status
$published = Post::where('status', PostStatus::Published)->get();

// Multiple statuses
$active = Post::whereIn('status', [
    PostStatus::Published,
    PostStatus::Draft,
])->get();

// Not archived
$notArchived = Post::where('status', '!=', PostStatus::Archived)->get();
```

### Filament Badge Example

```php
use Filament\Tables\Columns\TextColumn;

TextColumn::make('status')
    ->badge()
    ->formatStateUsing(fn (PostStatus $state): string => $state->getLabel())
    ->color(fn (PostStatus $state): string => $state->getColor())
    ->icon(fn (PostStatus $state): string => $state->getIcon());
```

This will render a colored badge with an icon:
- 🟠 **Draft** (gray pencil icon)
- 🟢 **Published** (green check icon)
- 🔴 **Archived** (red archive icon)
