<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\RevisionSource;
use App\Enums\RevisionState;
use Database\Factories\PostRevisionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostRevision extends Model
{
    /** @use HasFactory<PostRevisionFactory> */
    use HasFactory, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'post_id',
        'version',
        'base_version',
        'state',
        'source',
        'client_name',
        'note',
        'unguarded',
        'title',
        'content_html',
        'excerpt',
        'slug',
        'status',
        'edits',
        'decided_at',
        'decided_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'base_version' => 'integer',
            'state' => RevisionState::class,
            'source' => RevisionSource::class,
            'unguarded' => 'boolean',
            'status' => PostStatus::class,
            'edits' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->state === RevisionState::Pending;
    }
}
