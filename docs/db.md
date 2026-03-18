1. The Core Tables

- categories
As discussed, this holds your main buckets (Short Stories, Reflections, etc.).

id (Primary Key)

name (String) - e.g., "Short Stories"

slug (String, Unique) - e.g., "short-stories" (Crucial for clean URLs!)

description (Text, Nullable)

- tags
This holds all the specific keywords (sci-fi, rain, mindfulness).

id (Primary Key)

name (String)

slug (String, Unique)

2. The Heavy Lifter: The Posts Table

- posts
This is the heart of your database where your stories and thoughts will live.

id (Primary Key)

user_id (Foreign Key -> users.id)

category_id (Foreign Key -> categories.id) - This enforces the "one category per post" rule.

title (String)

slug (String, Unique) - e.g., "my-walk-in-the-rain"

content (Text) - I highly recommend storing this as Markdown rather than raw HTML. It is much safer and easier to edit.

status (String or Enum) - e.g., 'draft', 'published', 'archived'. (You don't want every half-finished thought going live immediately!)

published_at (Timestamp, Nullable)

created_at (Timestamp)

updated_at (Timestamp)

3. The Join Table (Many-to-Many)
Because one post can have many tags, and one tag can belong to many posts, you cannot just put a tag_id inside the posts table. You need a join table to connect them.

- post_tags

post_id (Foreign Key -> posts.id)

tag_id (Foreign Key -> tags.id)

(Primary Key is a composite of post_id and tag_id)

## Key Developer Considerations

### Slugs
Slugs are mandatory: You don't want URLs like `yourwebsite.com/post/8475`. You want `yourwebsite.com/reflections/my-walk-in-the-rain`. The slug columns make this happen.

### Indexing
Database indexes are added to slug columns (posts, categories, tags tables). Since URLs search by slug, not ID, indexes make lookups blazing fast.

### Cascading Deletes
Foreign keys in `post_tags` are configured to cascade on delete, preventing orphaned data.

### Soft Deletes
All tables include `deleted_at` columns for soft deletion support.

---

## Migrations

Migration files:
- `create_categories_table.php`
- `create_tags_table.php`
- `create_posts_table.php`
- `create_post_tag_table.php` (pivot)