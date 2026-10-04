<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Comments\CreateComment;
use App\Mcp\Tools\Comments\DeleteComment;
use App\Mcp\Tools\Comments\GetComment;
use App\Mcp\Tools\Comments\ListComments;
use App\Mcp\Tools\Comments\UpdateComment;
use App\Mcp\Tools\Posts\CreatePost;
use App\Mcp\Tools\Posts\DeletePost;
use App\Mcp\Tools\Posts\GetPost;
use App\Mcp\Tools\Posts\ListPosts;
use App\Mcp\Tools\Posts\UpdatePost;
use App\Mcp\Tools\Taxonomy\ListCategories;
use App\Mcp\Tools\Taxonomy\ListTags;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Blog')]
#[Version('0.1.0')]
#[Instructions('Manage the blog: list, read, create, edit, and delete posts, and list, read, approve, edit, and delete comments. Start with list-categories and list-tags to get the ids that create-post and update-post need.')]
class BlogServer extends Server
{
    protected array $tools = [
        ListPosts::class,
        GetPost::class,
        CreatePost::class,
        UpdatePost::class,
        DeletePost::class,
        ListComments::class,
        GetComment::class,
        CreateComment::class,
        UpdateComment::class,
        DeleteComment::class,
        ListCategories::class,
        ListTags::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
