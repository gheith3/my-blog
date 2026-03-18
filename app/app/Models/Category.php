<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'ar_name',
        'slug',
        'description',
        'ar_description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => 'string',
            'ar_description' => 'string',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = str($category->name)->slug();
            }
        });

        static::updating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = str($category->name)->slug();
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
     * Get localized description based on current locale
     */
    public function getLocalizedDescription(): ?string
    {
        if (app()->getLocale() === 'ar' && ! empty($this->ar_description)) {
            return $this->ar_description;
        }

        return $this->description;
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
