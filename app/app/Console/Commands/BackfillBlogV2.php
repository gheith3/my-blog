<?php

namespace App\Console\Commands;

use App\Enums\RevisionSource;
use App\Enums\RevisionState;
use App\Models\Post;
use App\Models\PostRevision;
use App\Services\PostService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('blog:backfill-v2')]
#[Description('Backfill excerpts and baseline revisions for posts that predate Blog MCP v2')]
class BackfillBlogV2 extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PostService $postService): int
    {
        $excerptsFilled = 0;
        $baselinesWritten = 0;

        Post::withTrashed()
            ->where(function ($query) {
                $query->whereNull('excerpt')
                    ->orWhereDoesntHave('revisions');
            })
            ->chunkById(100, function ($posts) use ($postService, &$excerptsFilled, &$baselinesWritten) {
                foreach ($posts as $post) {
                    if ($post->excerpt === null || trim($post->excerpt) === '') {
                        $post->excerpt = $postService->generateExcerpt($post->content);
                        $post->saveQuietly();
                        $excerptsFilled++;
                    }

                    if (! PostRevision::where('post_id', $post->id)->exists()) {
                        PostRevision::create([
                            'post_id' => $post->id,
                            'version' => $post->version,
                            'state' => RevisionState::Applied,
                            'source' => RevisionSource::Backfill,
                            'client_name' => null,
                            'note' => 'v2 baseline backfill',
                            'unguarded' => false,
                            'title' => $post->title,
                            'content_html' => $post->content,
                            'excerpt' => $post->excerpt,
                            'slug' => $post->slug,
                            'status' => $post->status,
                        ]);
                        $baselinesWritten++;
                    }
                }
            });

        $this->info("Backfill complete: {$excerptsFilled} excerpts generated, {$baselinesWritten} baseline revisions written.");

        return self::SUCCESS;
    }
}
