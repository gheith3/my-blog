<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    // General
    public string $site_name;

    public string $site_tagline;

    public string $site_description;

    // Arabic General
    public string $site_name_ar;

    public string $site_tagline_ar;

    public string $site_description_ar;

    // Hero Section
    public string $hero_welcome;

    public string $hero_title;

    public string $hero_description;

    public string $hero_read_posts;

    public string $hero_about_me;

    // Arabic Hero Section
    public string $hero_welcome_ar;

    public string $hero_title_ar;

    public string $hero_description_ar;

    public string $hero_read_posts_ar;

    public string $hero_about_me_ar;

    // About Section
    public string $about_title;

    public string $about_subtitle;

    public ?string $about_description_1;

    public ?string $about_description_2;

    public ?string $about_description_3;

    public ?string $about_image;

    // Arabic About Section
    public string $about_title_ar;

    public string $about_subtitle_ar;

    public ?string $about_description_1_ar;

    public ?string $about_description_2_ar;

    public ?string $about_description_3_ar;

    // Posts Section
    public string $posts_title;

    public string $posts_description;

    public string $posts_search_placeholder;

    public string $posts_read_more;

    public string $posts_back_to_posts;

    public string $posts_related_posts;

    public string $posts_last_updated;

    public string $posts_share_twitter;

    public string $posts_no_posts;

    public string $posts_uncategorized;

    // Arabic Posts Section
    public string $posts_title_ar;

    public string $posts_description_ar;

    public string $posts_search_placeholder_ar;

    public string $posts_read_more_ar;

    public string $posts_back_to_posts_ar;

    public string $posts_related_posts_ar;

    public string $posts_last_updated_ar;

    public string $posts_share_twitter_ar;

    public string $posts_no_posts_ar;

    public string $posts_uncategorized_ar;

    // Footer
    public string $footer_copyright;

    public string $footer_made_with;

    // Arabic Footer
    public string $footer_copyright_ar;

    public string $footer_made_with_ar;

    // Navigation
    public string $nav_home;

    public string $nav_about;

    public string $nav_posts;

    // Arabic Navigation
    public string $nav_home_ar;

    public string $nav_about_ar;

    public string $nav_posts_ar;

    // Social Links
    public ?string $twitter_url;

    public ?string $github_url;

    public ?string $linkedin_url;

    // Stats
    public int $stats_posts_count;

    public int $stats_categories_count;

    public int $stats_readers_count;

    public static function group(): string
    {
        return 'general';
    }

    /**
     * Get setting based on current locale
     */
    public function get(string $key, ?string $locale = null): mixed
    {
        $locale = $locale ?? app()->getLocale();
        $suffix = $locale === 'ar' ? '_ar' : '';

        return $this->{$key.$suffix} ?? $this->{$key} ?? null;
    }
}
