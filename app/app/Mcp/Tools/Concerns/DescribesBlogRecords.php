<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Str;

/**
 * Shared text rendering so every post and comment tool describes records
 * the same way, and the agent sees the same ids everywhere.
 */
trait DescribesBlogRecords
{
    protected function describePost(Post $post): string
    {
        return sprintf(
            '- [%d] %s — slug "%s", %s, category [%d] %s, updated %s',
            $post->id,
            $post->title,
            $post->slug,
            $post->status->value,
            $post->category_id,
            $post->category->name,
            $post->updated_at->toDateTimeString(),
        );
    }

    protected function postDetails(Post $post): string
    {
        $post->loadMissing(['category', 'tags']);

        $tags = $post->tags->map(fn (Tag $tag): string => "[{$tag->id}] {$tag->name}")->implode(', ') ?: 'none';

        return implode("\n", [
            "Post [{$post->id}]: {$post->title}",
            "Slug: {$post->slug}",
            "Status: {$post->status->value}",
            'Published at: '.($post->published_at?->toDateTimeString() ?? 'not published'),
            "Category: [{$post->category_id}] {$post->category->name}",
            "Tags: {$tags}",
            'Thumbnail: '.($post->thumbnail ?? 'none'),
            'URL: '.route('posts.show', $post->slug),
            '',
            $post->content,
        ]);
    }

    protected function describeComment(Comment $comment): string
    {
        return sprintf(
            '- [%d] on post %d, by %s, %s, %s — %s',
            $comment->id,
            $comment->commentable_id,
            $comment->name,
            $comment->is_approved ? 'approved' : 'pending',
            $comment->parent_id ? "reply to [{$comment->parent_id}]" : 'top-level',
            Str::limit(str_replace("\n", ' ', $comment->content), 80),
        );
    }
}
