<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DemoStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [Stat::make('Users', '12')->chart([1, 3, 2, 5])->color('success')];
    }
}
