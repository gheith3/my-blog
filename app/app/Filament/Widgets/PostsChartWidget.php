<?php

namespace App\Filament\Widgets;

use App\Models\Post;
use Filament\Widgets\ChartWidget;

class PostsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Posts Created';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $data = $this->getPostsPerMonth();

        return [
            'datasets' => [
                [
                    'label' => 'Posts Created',
                    'data' => $data['counts'],
                    'backgroundColor' => [
                        'rgba(245, 158, 11, 0.8)',
                    ],
                    'borderColor' => '#f59e0b',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Get posts created per month for the last 6 months
     */
    private function getPostsPerMonth(): array
    {
        $labels = [];
        $counts = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M Y');

            $count = Post::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $counts[] = $count;
        }

        return [
            'labels' => $labels,
            'counts' => $counts,
        ];
    }
}
