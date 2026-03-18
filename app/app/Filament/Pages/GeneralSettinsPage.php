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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Site Information')
                    ->description('Basic site details and branding')
                    ->schema([
                        TextInput::make('site_name')
                            ->label('Site Name (English)')
                            ->required(),
                        TextInput::make('site_name_ar')
                            ->label('Site Name (Arabic)')
                            ->required(),
                        TextInput::make('site_tagline')
                            ->label('Tagline (English)')
                            ->required(),
                        TextInput::make('site_tagline_ar')
                            ->label('Tagline (Arabic)')
                            ->required(),
                        Textarea::make('site_description')
                            ->label('Site Description (English)')
                            ->rows(2)
                            ->required(),
                        Textarea::make('site_description_ar')
                            ->label('Site Description (Arabic)')
                            ->rows(2)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Hero Section')
                    ->description('Homepage hero area content')
                    ->schema([
                        TextInput::make('hero_welcome')
                            ->label('Welcome Text (English)')
                            ->required(),
                        TextInput::make('hero_welcome_ar')
                            ->label('Welcome Text (Arabic)')
                            ->required(),
                        TextInput::make('hero_title')
                            ->label('Title (English)')
                            ->required(),
                        TextInput::make('hero_title_ar')
                            ->label('Title (Arabic)')
                            ->required(),
                        Textarea::make('hero_description')
                            ->label('Description (English)')
                            ->rows(3)
                            ->required(),
                        Textarea::make('hero_description_ar')
                            ->label('Description (Arabic)')
                            ->rows(3)
                            ->required(),
                        TextInput::make('hero_read_posts')
                            ->label('Read Posts Button (English)')
                            ->required(),
                        TextInput::make('hero_read_posts_ar')
                            ->label('Read Posts Button (Arabic)')
                            ->required(),
                        TextInput::make('hero_about_me')
                            ->label('About Me Button (English)')
                            ->required(),
                        TextInput::make('hero_about_me_ar')
                            ->label('About Me Button (Arabic)')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('About Section')
                    ->description('About page content and personal image')
                    ->schema([
                        FileUpload::make('about_image')
                            ->label('Personal Image')
                            ->image()
                            ->directory('about')
                            ->maxSize(2048),
                        TextInput::make('about_title')
                            ->label('Title (English)')
                            ->required(),
                        TextInput::make('about_title_ar')
                            ->label('Title (Arabic)')
                            ->required(),
                        TextInput::make('about_subtitle')
                            ->label('Subtitle (English)')
                            ->required(),
                        TextInput::make('about_subtitle_ar')
                            ->label('Subtitle (Arabic)')
                            ->required(),
                        Textarea::make('about_description_1')
                            ->label('Paragraph 1 (English)')
                            ->rows(3),
                        Textarea::make('about_description_1_ar')
                            ->label('Paragraph 1 (Arabic)')
                            ->rows(3),
                        Textarea::make('about_description_2')
                            ->label('Paragraph 2 (English)')
                            ->rows(3),
                        Textarea::make('about_description_2_ar')
                            ->label('Paragraph 2 (Arabic)')
                            ->rows(3),
                        Textarea::make('about_description_3')
                            ->label('Paragraph 3 (English)')
                            ->rows(3),
                        Textarea::make('about_description_3_ar')
                            ->label('Paragraph 3 (Arabic)')
                            ->rows(3),
                    ])
                    ->columns(2),

                Section::make('Posts Section')
                    ->description('Blog posts listing page text')
                    ->schema([
                        TextInput::make('posts_title')
                            ->label('Title (English)')
                            ->required(),
                        TextInput::make('posts_title_ar')
                            ->label('Title (Arabic)')
                            ->required(),
                        Textarea::make('posts_description')
                            ->label('Description (English)')
                            ->rows(2),
                        Textarea::make('posts_description_ar')
                            ->label('Description (Arabic)')
                            ->rows(2),
                        TextInput::make('posts_search_placeholder')
                            ->label('Search Placeholder (English)'),
                        TextInput::make('posts_search_placeholder_ar')
                            ->label('Search Placeholder (Arabic)'),
                        TextInput::make('posts_read_more')
                            ->label('Read More Text (English)'),
                        TextInput::make('posts_read_more_ar')
                            ->label('Read More Text (Arabic)'),
                        TextInput::make('posts_back_to_posts')
                            ->label('Back to Posts (English)'),
                        TextInput::make('posts_back_to_posts_ar')
                            ->label('Back to Posts (Arabic)'),
                        TextInput::make('posts_related_posts')
                            ->label('Related Posts (English)'),
                        TextInput::make('posts_related_posts_ar')
                            ->label('Related Posts (Arabic)'),
                        TextInput::make('posts_last_updated')
                            ->label('Last Updated (English)'),
                        TextInput::make('posts_last_updated_ar')
                            ->label('Last Updated (Arabic)'),
                        TextInput::make('posts_share_twitter')
                            ->label('Share on Twitter (English)'),
                        TextInput::make('posts_share_twitter_ar')
                            ->label('Share on Twitter (Arabic)'),
                        TextInput::make('posts_no_posts')
                            ->label('No Posts Message (English)'),
                        TextInput::make('posts_no_posts_ar')
                            ->label('No Posts Message (Arabic)'),
                        TextInput::make('posts_uncategorized')
                            ->label('Uncategorized (English)'),
                        TextInput::make('posts_uncategorized_ar')
                            ->label('Uncategorized (Arabic)'),
                    ])
                    ->columns(2),

                Section::make('Navigation')
                    ->description('Menu items text')
                    ->schema([
                        TextInput::make('nav_home')
                            ->label('Home (English)')
                            ->required(),
                        TextInput::make('nav_home_ar')
                            ->label('Home (Arabic)')
                            ->required(),
                        TextInput::make('nav_about')
                            ->label('About (English)')
                            ->required(),
                        TextInput::make('nav_about_ar')
                            ->label('About (Arabic)')
                            ->required(),
                        TextInput::make('nav_posts')
                            ->label('Posts (English)')
                            ->required(),
                        TextInput::make('nav_posts_ar')
                            ->label('Posts (Arabic)')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Footer')
                    ->description('Footer content')
                    ->schema([
                        TextInput::make('footer_copyright')
                            ->label('Copyright Text (English)'),
                        TextInput::make('footer_copyright_ar')
                            ->label('Copyright Text (Arabic)'),
                        TextInput::make('footer_made_with')
                            ->label('Made With Text (English)'),
                        TextInput::make('footer_made_with_ar')
                            ->label('Made With Text (Arabic)'),
                    ])
                    ->columns(2),

                Section::make('Social Links')
                    ->description('Social media URLs (optional)')
                    ->schema([
                        TextInput::make('twitter_url')
                            ->label('Twitter URL')
                            ->url()
                            ->placeholder('https://twitter.com/username'),
                        TextInput::make('github_url')
                            ->label('GitHub URL')
                            ->url()
                            ->placeholder('https://github.com/username'),
                        TextInput::make('linkedin_url')
                            ->label('LinkedIn URL')
                            ->url()
                            ->placeholder('https://linkedin.com/in/username'),
                    ])
                    ->columns(1),

                Section::make('Stats')
                    ->description('Displayed statistics on homepage')
                    ->schema([
                        TextInput::make('stats_posts_count')
                            ->label('Posts Count')
                            ->numeric()
                            ->default(50),
                        TextInput::make('stats_categories_count')
                            ->label('Categories Count')
                            ->numeric()
                            ->default(5),
                        TextInput::make('stats_readers_count')
                            ->label('Readers Count')
                            ->numeric()
                            ->default(1000),
                    ])
                    ->columns(3),
            ]);
    }
}
