<?php

namespace App\Http\Middleware;

use App\Models\Post;
use App\Models\VistorCount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackVisitor
{
    /**
     * Pages to exclude from tracking
     */
    protected array $excludedPaths = [
        'admin/*',
        'filament/*',
        'api/*',
        'livewire/*',
        '_debugbar/*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip tracking for excluded paths
        if ($this->shouldSkipTracking($request)) {
            return $response;
        }

        // Skip if user is authenticated (admin/staff)
        if ($request->user()) {
            return $response;
        }

        // Skip if already tracked in this session for this URL
        if ($this->hasAlreadyVisited($request)) {
            return $response;
        }

        // Get post ID if viewing a post
        $postId = $this->getPostIdFromRequest($request);

        // Create visitor record
        $this->recordVisit($request, $postId);

        // Mark as visited in session
        $this->markAsVisited($request);

        return $response;
    }

    /**
     * Check if this request should be skipped
     */
    protected function shouldSkipTracking(Request $request): bool
    {
        $path = $request->path();

        foreach ($this->excludedPaths as $excluded) {
            if ($request->is($excluded)) {
                return true;
            }
        }

        // Skip non-GET requests
        if (! $request->isMethod('GET')) {
            return true;
        }

        // Skip bots/crawlers
        if ($this->isBot($request)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user has already visited this URL in current session
     */
    protected function hasAlreadyVisited(Request $request): bool
    {
        $sessionKey = $this->getSessionKey($request);

        return session()->has($sessionKey);
    }

    /**
     * Mark URL as visited in session
     */
    protected function markAsVisited(Request $request): void
    {
        $sessionKey = $this->getSessionKey($request);

        // Store with timestamp, expire after 30 minutes for same page
        session()->put($sessionKey, now()->timestamp);
    }

    /**
     * Get session key for this URL
     */
    protected function getSessionKey(Request $request): string
    {
        $url = $request->fullUrl();

        return 'visited_'.md5($url);
    }

    /**
     * Get post ID from request if viewing a post
     */
    protected function getPostIdFromRequest(Request $request): ?int
    {
        // Check if route is posts.show
        if ($request->route()?->getName() === 'posts.show') {
            $slug = $request->route('slug');
            $post = Post::where('slug', $slug)->first();

            return $post?->id;
        }

        return null;
    }

    /**
     * Record the visit to database
     */
    protected function recordVisit(Request $request, ?int $postId): void
    {
        try {
            VistorCount::create([
                'post_id' => $postId,
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'session_id' => session()->getId(),
                'visited_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silently fail - don't break the request
            report($e);
        }
    }

    /**
     * Check if request is from a bot/crawler
     */
    protected function isBot(Request $request): bool
    {
        $userAgent = strtolower($request->userAgent() ?? '');

        $bots = [
            'bot', 'crawl', 'spider', 'slurp', 'search', 'google', 'bing',
            'yahoo', 'baidu', 'yandex', 'facebook', 'twitter', 'linkedin',
        ];

        foreach ($bots as $bot) {
            if (str_contains($userAgent, $bot)) {
                return true;
            }
        }

        return false;
    }
}
