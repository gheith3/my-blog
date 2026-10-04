<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RevisionSource: string implements HasColor, HasLabel
{
    case Dashboard = 'dashboard';

    case Mcp = 'mcp';

    case Api = 'api';

    case Backfill = 'backfill';

    public function getLabel(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard',
            self::Mcp => 'MCP',
            self::Api => 'API',
            self::Backfill => 'Backfill',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Dashboard => 'info',
            self::Mcp => 'warning',
            self::Api => 'gray',
            self::Backfill => 'gray',
        };
    }
}
