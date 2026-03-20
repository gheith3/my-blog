<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\VistorCount;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalVisitors = VistorCount::count();
        $uniqueVisitors = VistorCount::distinct('session_id')->count('session_id');

        return [
            Stat::make(__('filament.widgets.stats_overview.total_posts'), Post::count())
                ->description(Post::where('status', 'published')->count().' '.__('filament.widgets.stats_overview.published_posts'))
                ->descriptionIcon('heroicon-m-document-text')
                ->color('success'),

            Stat::make(__('filament.widgets.stats_overview.categories'), Category::count())
                ->description(__('filament.widgets.stats_overview.organized_content'))
                ->descriptionIcon('heroicon-m-folder')
                ->color('warning'),

            Stat::make(__('filament.widgets.stats_overview.tags'), Tag::count())
                ->description(__('filament.widgets.stats_overview.for_filtering'))
                ->descriptionIcon('heroicon-m-tag')
                ->color('info'),

            Stat::make(__('filament.widgets.stats_overview.total_visitors'), $totalVisitors)
                ->description($uniqueVisitors.' '.__('filament.widgets.stats_overview.unique_visitors'))
                ->descriptionIcon('heroicon-m-eye')
                ->color('primary'),
        ];
    }
}
