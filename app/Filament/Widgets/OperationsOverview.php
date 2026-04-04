<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class OperationsOverview extends Widget
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.widgets.operations-overview';

    protected int|string|array $columnSpan = 'full';
}
