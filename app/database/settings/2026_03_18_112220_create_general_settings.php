<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // General
        $this->migrator->add('general.site_name', 'My Blog');
        $this->migrator->add('general.site_tagline', 'Thoughts & Stories');
        $this->migrator->add('general.site_description', 'A personal collection of reflections, short stories, and ideas.');

        // Arabic General
        $this->migrator->add('general.site_name_ar', 'مدونتي');
        $this->migrator->add('general.site_tagline_ar', 'أفكار وقصص');
        $this->migrator->add('general.site_description_ar', 'مجموعة شخصية من التأملات والقصص القصيرة والأفكار.');

        // Hero Section
        $this->migrator->add('general.hero_welcome', 'Welcome to my space');
        $this->migrator->add('general.hero_title', 'Thoughts & Stories');
        $this->migrator->add('general.hero_description', 'A personal collection of reflections, short stories, and ideas. Exploring life, technology, and everything in between.');
        $this->migrator->add('general.hero_read_posts', 'Read Posts');
        $this->migrator->add('general.hero_about_me', 'About Me');

        // Arabic Hero Section
        $this->migrator->add('general.hero_welcome_ar', 'مرحباً بك في مدونتي');
        $this->migrator->add('general.hero_title_ar', 'أفكار وقصص');
        $this->migrator->add('general.hero_description_ar', 'مجموعة شخصية من التأملات والقصص القصيرة والأفكار. استكشاف الحياة والتكنولوجيا وكل شيء بينهما.');
        $this->migrator->add('general.hero_read_posts_ar', 'اقرأ المقالات');
        $this->migrator->add('general.hero_about_me_ar', 'عنّي');

        // About Section
        $this->migrator->add('general.about_title', 'About Me');
        $this->migrator->add('general.about_subtitle', 'Writer, Thinker, Dreamer');
        $this->migrator->add('general.about_description_1', 'Welcome to my corner of the internet. I\'m a passionate writer who loves to explore the depths of human experience through stories and reflections.');
        $this->migrator->add('general.about_description_2', 'This blog is where I share my journey — from short fiction that transports you to other worlds, to quiet moments of mindfulness that ground us in the present.');
        $this->migrator->add('general.about_description_3', 'When I\'m not writing, you\'ll find me exploring nature, diving into tech, or simply enjoying a good cup of coffee while watching the rain.');
        $this->migrator->add('general.about_image', null);

        // Arabic About Section
        $this->migrator->add('general.about_title_ar', 'عنّي');
        $this->migrator->add('general.about_subtitle_ar', 'كاتب، مفكر، حالم');
        $this->migrator->add('general.about_description_1_ar', 'مرحباً بك في زاويتي من الإنترنت. أنا كاتب شغوف أحب استكشاف أعماق التجربة الإنسانية من خلال القصص والتأملات.');
        $this->migrator->add('general.about_description_2_ar', 'هذه المدونة هي المكان الذي أشارك فيه رحلتي — من الأعمال الخيالية القصيرة التي تنقلك إلى عوالم أخرى، إلى لحظات التأمل الهادئة التي تربطنا بالحاضر.');
        $this->migrator->add('general.about_description_3_ar', 'عندما لا أكون أكتب، ستجدني أستكشف الطبيعة أو أتعمق في التكنولوجيا، أو ببساطة أستمتع بفنجان قهوة جيد بينما أشاهب المطر.');

        // Posts Section
        $this->migrator->add('general.posts_title', 'Latest Posts');
        $this->migrator->add('general.posts_description', 'Thoughts and stories from my journey.');
        $this->migrator->add('general.posts_search_placeholder', 'Search posts...');
        $this->migrator->add('general.posts_read_more', 'Read more →');
        $this->migrator->add('general.posts_back_to_posts', '← Back to posts');
        $this->migrator->add('general.posts_related_posts', 'Related Posts');
        $this->migrator->add('general.posts_last_updated', 'Last updated');
        $this->migrator->add('general.posts_share_twitter', 'Share on Twitter');
        $this->migrator->add('general.posts_no_posts', 'No posts found.');
        $this->migrator->add('general.posts_uncategorized', 'Uncategorized');
        $this->migrator->add('general.posts_filtered_by', 'Filtered by:');
        $this->migrator->add('general.posts_clear_filters', 'Clear all');
        $this->migrator->add('general.posts_load_more', 'Load more');
        $this->migrator->add('general.posts_loading', 'Loading...');

        // Arabic Posts Section
        $this->migrator->add('general.posts_title_ar', 'أحدث المقالات');
        $this->migrator->add('general.posts_description_ar', 'أفكار وقصص من رحلتي.');
        $this->migrator->add('general.posts_search_placeholder_ar', 'ابحث في المقالات...');
        $this->migrator->add('general.posts_read_more_ar', 'اقرأ المزيد ←');
        $this->migrator->add('general.posts_back_to_posts_ar', '→ العودة للمقالات');
        $this->migrator->add('general.posts_related_posts_ar', 'مقالات ذات صلة');
        $this->migrator->add('general.posts_last_updated_ar', 'آخر تحديث');
        $this->migrator->add('general.posts_share_twitter_ar', 'شارك على تويتر');
        $this->migrator->add('general.posts_no_posts_ar', 'لا توجد مقالات.');
        $this->migrator->add('general.posts_uncategorized_ar', 'غير مصنف');
        $this->migrator->add('general.posts_filtered_by_ar', 'تم التصفية حسب:');
        $this->migrator->add('general.posts_clear_filters_ar', 'مسح الكل');
        $this->migrator->add('general.posts_load_more_ar', 'تحميل المزيد');
        $this->migrator->add('general.posts_loading_ar', 'جاري التحميل...');

        // Footer
        $this->migrator->add('general.footer_copyright', 'All rights reserved.');
        $this->migrator->add('general.footer_made_with', 'Made with');

        // Arabic Footer
        $this->migrator->add('general.footer_copyright_ar', 'جميع الحقوق محفوظة.');
        $this->migrator->add('general.footer_made_with_ar', 'صُنع بـ');

        // Navigation
        $this->migrator->add('general.nav_home', 'Home');
        $this->migrator->add('general.nav_about', 'About');
        $this->migrator->add('general.nav_posts', 'Posts');

        // Arabic Navigation
        $this->migrator->add('general.nav_home_ar', 'الرئيسية');
        $this->migrator->add('general.nav_about_ar', 'عني');
        $this->migrator->add('general.nav_posts_ar', 'المقالات');

        // Social Links
        $this->migrator->add('general.twitter_url', null);
        $this->migrator->add('general.github_url', null);
        $this->migrator->add('general.linkedin_url', null);

        // Stats
        $this->migrator->add('general.stats_posts_count', 50);
        $this->migrator->add('general.stats_categories_count', 5);
        $this->migrator->add('general.stats_readers_count', 1000);
    }
};
