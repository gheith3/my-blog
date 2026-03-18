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
            Stat::make('Total Posts', Post::count())
                ->description(Post::where('status', 'published')->count().' published')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('success'),

            Stat::make('Categories', Category::count())
                ->description('Organized content')
                ->descriptionIcon('heroicon-m-folder')
                ->color('warning'),

            Stat::make('Tags', Tag::count())
                ->description('For filtering')
                ->descriptionIcon('heroicon-m-tag')
                ->color('info'),

            Stat::make('Total Visitors', $totalVisitors)
                ->description($uniqueVisitors.' unique')
                ->descriptionIcon('heroicon-m-eye')
                ->color('primary'),
        ];
    }
}
