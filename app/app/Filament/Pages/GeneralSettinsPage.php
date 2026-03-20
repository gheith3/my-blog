<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class GeneralSettinsPage extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string $settings = GeneralSettings::class;

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.settings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('filament.pages.general_settings.sections.site_info'))
                    ->description(__('filament.pages.general_settings.sections.site_info_description'))
                    ->schema([
                        TextInput::make('site_name')
                            ->label(__('filament.pages.general_settings.fields.site_name_en'))
                            ->required(),
                        TextInput::make('site_name_ar')
                            ->label(__('filament.pages.general_settings.fields.site_name_ar'))
                            ->required(),
                        TextInput::make('site_tagline')
                            ->label(__('filament.pages.general_settings.fields.tagline_en'))
                            ->required(),
                        TextInput::make('site_tagline_ar')
                            ->label(__('filament.pages.general_settings.fields.tagline_ar'))
                            ->required(),
                        Textarea::make('site_description')
                            ->label(__('filament.pages.general_settings.fields.site_description_en'))
                            ->rows(2)
                            ->required(),
                        Textarea::make('site_description_ar')
                            ->label(__('filament.pages.general_settings.fields.site_description_ar'))
                            ->rows(2)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make(__('filament.pages.general_settings.sections.hero'))
                    ->description(__('filament.pages.general_settings.sections.hero_description'))
                    ->schema([
                        TextInput::make('hero_welcome')
                            ->label(__('filament.pages.general_settings.fields.hero_welcome_en'))
                            ->required(),
                        TextInput::make('hero_welcome_ar')
                            ->label(__('filament.pages.general_settings.fields.hero_welcome_ar'))
                            ->required(),
                        TextInput::make('hero_title')
                            ->label(__('filament.pages.general_settings.fields.hero_title_en'))
                            ->required(),
                        TextInput::make('hero_title_ar')
                            ->label(__('filament.pages.general_settings.fields.hero_title_ar'))
                            ->required(),
                        Textarea::make('hero_description')
                            ->label(__('filament.pages.general_settings.fields.hero_description_en'))
                            ->rows(3)
                            ->required(),
                        Textarea::make('hero_description_ar')
                            ->label(__('filament.pages.general_settings.fields.hero_description_ar'))
                            ->rows(3)
                            ->required(),
                        TextInput::make('hero_read_posts')
                            ->label(__('filament.pages.general_settings.fields.hero_read_posts_en'))
                            ->required(),
                        TextInput::make('hero_read_posts_ar')
                            ->label(__('filament.pages.general_settings.fields.hero_read_posts_ar'))
                            ->required(),
                        TextInput::make('hero_about_me')
                            ->label(__('filament.pages.general_settings.fields.hero_about_me_en'))
                            ->required(),
                        TextInput::make('hero_about_me_ar')
                            ->label(__('filament.pages.general_settings.fields.hero_about_me_ar'))
                            ->required(),
                    ])
                    ->columns(2),

                Section::make(__('filament.pages.general_settings.sections.about'))
                    ->description(__('filament.pages.general_settings.sections.about_description'))
                    ->schema([
                        FileUpload::make('about_image')
                            ->label(__('filament.pages.general_settings.fields.about_image'))
                            ->image()
                            ->directory('about')
                            ->maxSize(2048),
                        TextInput::make('about_title')
                            ->label(__('filament.pages.general_settings.fields.about_title_en'))
                            ->required(),
                        TextInput::make('about_title_ar')
                            ->label(__('filament.pages.general_settings.fields.about_title_ar'))
                            ->required(),
                        TextInput::make('about_subtitle')
                            ->label(__('filament.pages.general_settings.fields.about_subtitle_en'))
                            ->required(),
                        TextInput::make('about_subtitle_ar')
                            ->label(__('filament.pages.general_settings.fields.about_subtitle_ar'))
                            ->required(),
                        Textarea::make('about_description_1')
                            ->label(__('filament.pages.general_settings.fields.about_description_1_en'))
                            ->rows(3),
                        Textarea::make('about_description_1_ar')
                            ->label(__('filament.pages.general_settings.fields.about_description_1_ar'))
                            ->rows(3),
                        Textarea::make('about_description_2')
                            ->label(__('filament.pages.general_settings.fields.about_description_2_en'))
                            ->rows(3),
                        Textarea::make('about_description_2_ar')
                            ->label(__('filament.pages.general_settings.fields.about_description_2_ar'))
                            ->rows(3),
                        Textarea::make('about_description_3')
                            ->label(__('filament.pages.general_settings.fields.about_description_3_en'))
                            ->rows(3),
                        Textarea::make('about_description_3_ar')
                            ->label(__('filament.pages.general_settings.fields.about_description_3_ar'))
                            ->rows(3),
                    ])
                    ->columns(2),

                Section::make(__('filament.pages.general_settings.sections.posts'))
                    ->description(__('filament.pages.general_settings.sections.posts_description'))
                    ->schema([
                        TextInput::make('posts_title')
                            ->label(__('filament.pages.general_settings.fields.posts_title_en'))
                            ->required(),
                        TextInput::make('posts_title_ar')
                            ->label(__('filament.pages.general_settings.fields.posts_title_ar'))
                            ->required(),
                        Textarea::make('posts_description')
                            ->label(__('filament.pages.general_settings.fields.posts_description_en'))
                            ->rows(2),
                        Textarea::make('posts_description_ar')
                            ->label(__('filament.pages.general_settings.fields.posts_description_ar'))
                            ->rows(2),
                        TextInput::make('posts_search_placeholder')
                            ->label(__('filament.pages.general_settings.fields.posts_search_placeholder_en')),
                        TextInput::make('posts_search_placeholder_ar')
                            ->label(__('filament.pages.general_settings.fields.posts_search_placeholder_ar')),
                        TextInput::make('posts_read_more')
                            ->label(__('filament.pages.general_settings.fields.posts_read_more_en')),
                        TextInput::make('posts_read_more_ar')
                            ->label(__('filament.pages.general_settings.fields.posts_read_more_ar')),
                        TextInput::make('posts_back_to_posts')
                            ->label(__('filament.pages.general_settings.fields.posts_back_to_posts_en')),
                        TextInput::make('posts_back_to_posts_ar')
                            ->label(__('filament.pages.general_settings.fields.posts_back_to_posts_ar')),
                        TextInput::make('posts_related_posts')
                            ->label(__('filament.pages.general_settings.fields.posts_related_posts_en')),
                        TextInput::make('posts_related_posts_ar')
                            ->label(__('filament.pages.general_settings.fields.posts_related_posts_ar')),
                        TextInput::make('posts_last_updated')
                            ->label(__('filament.pages.general_settings.fields.posts_last_updated_en')),
                        TextInput::make('posts_last_updated_ar')
                            ->label(__('filament.pages.general_settings.fields.posts_last_updated_ar')),
                        TextInput::make('posts_share_twitter')
                            ->label(__('filament.pages.general_settings.fields.posts_share_twitter_en')),
                        TextInput::make('posts_share_twitter_ar')
                            ->label(__('filament.pages.general_settings.fields.posts_share_twitter_ar')),
                        TextInput::make('posts_no_posts')
                            ->label(__('filament.pages.general_settings.fields.posts_no_posts_en')),
                        TextInput::make('posts_no_posts_ar')
                            ->label(__('filament.pages.general_settings.fields.posts_no_posts_ar')),
                        TextInput::make('posts_uncategorized')
                            ->label(__('filament.pages.general_settings.fields.posts_uncategorized_en')),
                        TextInput::make('posts_uncategorized_ar')
                            ->label(__('filament.pages.general_settings.fields.posts_uncategorized_ar')),
                    ])
                    ->columns(2),

                Section::make(__('filament.pages.general_settings.sections.navigation'))
                    ->description(__('filament.pages.general_settings.sections.navigation_description'))
                    ->schema([
                        TextInput::make('nav_home')
                            ->label(__('filament.pages.general_settings.fields.nav_home_en'))
                            ->required(),
                        TextInput::make('nav_home_ar')
                            ->label(__('filament.pages.general_settings.fields.nav_home_ar'))
                            ->required(),
                        TextInput::make('nav_about')
                            ->label(__('filament.pages.general_settings.fields.nav_about_en'))
                            ->required(),
                        TextInput::make('nav_about_ar')
                            ->label(__('filament.pages.general_settings.fields.nav_about_ar'))
                            ->required(),
                        TextInput::make('nav_posts')
                            ->label(__('filament.pages.general_settings.fields.nav_posts_en'))
                            ->required(),
                        TextInput::make('nav_posts_ar')
                            ->label(__('filament.pages.general_settings.fields.nav_posts_ar'))
                            ->required(),
                    ])
                    ->columns(2),

                Section::make(__('filament.pages.general_settings.sections.footer'))
                    ->description(__('filament.pages.general_settings.sections.footer_description'))
                    ->schema([
                        TextInput::make('footer_copyright')
                            ->label(__('filament.pages.general_settings.fields.footer_copyright_en')),
                        TextInput::make('footer_copyright_ar')
                            ->label(__('filament.pages.general_settings.fields.footer_copyright_ar')),
                        TextInput::make('footer_made_with')
                            ->label(__('filament.pages.general_settings.fields.footer_made_with_en')),
                        TextInput::make('footer_made_with_ar')
                            ->label(__('filament.pages.general_settings.fields.footer_made_with_ar')),
                    ])
                    ->columns(2),

                Section::make(__('filament.pages.general_settings.sections.social'))
                    ->description(__('filament.pages.general_settings.sections.social_description'))
                    ->schema([
                        TextInput::make('twitter_url')
                            ->label(__('filament.pages.general_settings.fields.twitter_url'))
                            ->url()
                            ->placeholder('https://twitter.com/username'),
                        TextInput::make('github_url')
                            ->label(__('filament.pages.general_settings.fields.github_url'))
                            ->url()
                            ->placeholder('https://github.com/username'),
                        TextInput::make('linkedin_url')
                            ->label(__('filament.pages.general_settings.fields.linkedin_url'))
                            ->url()
                            ->placeholder('https://linkedin.com/in/username'),
                    ])
                    ->columns(1),

                Section::make(__('filament.pages.general_settings.sections.stats'))
                    ->description(__('filament.pages.general_settings.sections.stats_description'))
                    ->schema([
                        TextInput::make('stats_posts_count')
                            ->label(__('filament.pages.general_settings.fields.stats_posts_count'))
                            ->numeric()
                            ->default(50),
                        TextInput::make('stats_categories_count')
                            ->label(__('filament.pages.general_settings.fields.stats_categories_count'))
                            ->numeric()
                            ->default(5),
                        TextInput::make('stats_readers_count')
                            ->label(__('filament.pages.general_settings.fields.stats_readers_count'))
                            ->numeric()
                            ->default(1000),
                    ])
                    ->columns(3),
            ]);
    }
}
