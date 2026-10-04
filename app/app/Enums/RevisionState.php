<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RevisionState: string implements HasColor, HasIcon, HasLabel
{
    case Applied = 'applied';

    case Pending = 'pending';

    case Rejected = 'rejected';

    case Conflict = 'conflict';

    case Superseded = 'superseded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Applied => 'Applied',
            self::Pending => 'Pending',
            self::Rejected => 'Rejected',
            self::Conflict => 'Conflict',
            self::Superseded => 'Superseded',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Applied => 'success',
            self::Pending => 'warning',
            self::Rejected => 'danger',
            self::Conflict => 'danger',
            self::Superseded => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Applied => 'heroicon-m-check-circle',
            self::Pending => 'heroicon-m-clock',
            self::Rejected => 'heroicon-m-x-circle',
            self::Conflict => 'heroicon-m-exclamation-triangle',
            self::Superseded => 'heroicon-m-arrow-path',
        };
    }
}
