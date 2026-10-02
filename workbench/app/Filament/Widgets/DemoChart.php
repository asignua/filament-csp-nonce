<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class DemoChart extends ChartWidget
{
    protected ?string $heading = 'Demo';

    protected function getData(): array
    {
        return ['datasets' => [['label' => 'Users', 'data' => [1, 3, 2, 5]]], 'labels' => ['a', 'b', 'c', 'd']];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
