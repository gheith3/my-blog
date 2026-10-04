<?php

namespace App\Http\Middleware;

use App\Models\Post;
use App\Models\PostSlugRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permanently redirect renamed post slugs (Blog MCP v2, section 5): when the
 * requested slug is not a live post but is recorded in post_slug_redirects,
 * send a 301 to the post's current URL. Unknown slugs fall through to the
 * normal 404.
 */
class RedirectOldPostSlugs
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');

        if (! is_string($slug) || Post::where('slug', $slug)->exists()) {
            return $next($request);
        }

        $post = PostSlugRedirect::where('old_slug', $slug)->first()?->post;

        if ($post === null) {
            return $next($request);
        }

        return redirect()->route('posts.show', $post->slug, 301);
    }
}
