<?php

namespace App\Mcp\Servers;

use App\Mcp\Resources\StyleGuide;
use App\Mcp\Tools\Comments\CreateComment;
use App\Mcp\Tools\Comments\DeleteComment;
use App\Mcp\Tools\Comments\GetComment;
use App\Mcp\Tools\Comments\ListComments;
use App\Mcp\Tools\Comments\UpdateComment;
use App\Mcp\Tools\Posts\CreatePost;
use App\Mcp\Tools\Posts\DeletePost;
use App\Mcp\Tools\Posts\EditPost;
use App\Mcp\Tools\Posts\GetPost;
use App\Mcp\Tools\Posts\GetPosts;
use App\Mcp\Tools\Posts\ListPosts;
use App\Mcp\Tools\Posts\SearchPosts;
use App\Mcp\Tools\Posts\UpdatePost;
use App\Mcp\Tools\Revisions\GetRevision;
use App\Mcp\Tools\Revisions\ListRevisions;
use App\Mcp\Tools\Revisions\RestoreRevision;
use App\Mcp\Tools\StyleGuide\GetStyleGuide;
use App\Mcp\Tools\Taxonomy\ListCategories;
use App\Mcp\Tools\Taxonomy\ListTags;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Blog')]
#[Version('2.0.0')]
#[Instructions('Manage the blog: list, read, create, edit, and delete posts, and list, read, approve, edit, and delete comments. Start with list-categories and list-tags to get the ids that create-post and update-post need. Before any write to post content, read the style guide and follow it. When proofreading, change only errors, never word choice.')]
class BlogServer extends Server
{
    protected array $tools = [
        ListPosts::class,
        GetPost::class,
        GetPosts::class,
        SearchPosts::class,
        CreatePost::class,
        UpdatePost::class,
        EditPost::class,
        DeletePost::class,
        ListRevisions::class,
        GetRevision::class,
        RestoreRevision::class,
        GetStyleGuide::class,
        ListComments::class,
        GetComment::class,
        CreateComment::class,
        UpdateComment::class,
        DeleteComment::class,
        ListCategories::class,
        ListTags::class,
    ];

    protected array $resources = [
        StyleGuide::class,
    ];

    protected array $prompts = [
        //
    ];
}
