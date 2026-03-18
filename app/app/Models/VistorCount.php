<?php

namespace App\Models;

use Database\Factories\VistorCountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VistorCount extends Model
{
    /** @use HasFactory<VistorCountFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'visitor_counts';

    protected $fillable = [
        'post_id',
        'url',
        'path',
        'route_name',
        'ip_address',
        'user_agent',
        'session_id',
        'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Scope to get unique visitors by session
     */
    public function scopeUniqueBySession($query)
    {
        return $query->select('session_id')
            ->distinct();
    }

    /**
     * Scope to get visits for a specific post
     */
    public function scopeForPost($query, int $postId)
    {
        return $query->where('post_id', $postId);
    }

    /**
     * Scope to get visits within date range
     */
    public function scopeWithinPeriod($query, \DateTime $start, \DateTime $end)
    {
        return $query->whereBetween('visited_at', [$start, $end]);
    }
}
