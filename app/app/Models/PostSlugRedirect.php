<?php

namespace App\Models;

use Database\Factories\PostSlugRedirectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostSlugRedirect extends Model
{
    /** @use HasFactory<PostSlugRedirectFactory> */
    use HasFactory;

    protected $fillable = [
        'post_id',
        'old_slug',
    ];

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
