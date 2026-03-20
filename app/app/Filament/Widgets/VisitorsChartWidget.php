<?php

namespace App\Filament\Widgets;

use App\Models\VistorCount;
use Filament\Widgets\ChartWidget;

class VisitorsChartWidget extends ChartWidget
{
    protected ?string $heading = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        return __('filament.widgets.visitors_chart.heading');
    }

    protected function getData(): array
    {
        $data = $this->getVisitorsPerDay();

        return [
            'datasets' => [
                [
                    'label' => __('filament.widgets.visitors_chart.total_visits'),
                    'data' => $data['total'],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                ],
                [
                    'label' => __('filament.widgets.visitors_chart.unique_visitors'),
                    'data' => $data['unique'],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * Get visitor data for the last 30 days
     */
    private function getVisitorsPerDay(): array
    {
        $labels = [];
        $totalData = [];
        $uniqueData = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('M d');

            // Total visits for this day
            $totalCount = VistorCount::whereDate('visited_at', $date)->count();
            $totalData[] = $totalCount;

            // Unique visitors for this day
            $uniqueCount = VistorCount::whereDate('visited_at', $date)
                ->distinct('session_id')
                ->count('session_id');
            $uniqueData[] = $uniqueCount;
        }

        return [
            'labels' => $labels,
            'total' => $totalData,
            'unique' => $uniqueData,
        ];
    }
}
