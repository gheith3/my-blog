<?php

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'ar_name',
        'slug',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = str($tag->name)->slug();
            }
        });

        static::updating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = str($tag->name)->slug();
            }
        });
    }

    /**
     * Get localized name based on current locale
     */
    public function getLocalizedName(): string
    {
        if (app()->getLocale() === 'ar' && ! empty($this->ar_name)) {
            return $this->ar_name;
        }

        return $this->name;
    }

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }
}
