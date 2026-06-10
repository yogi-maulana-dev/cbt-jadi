<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttemptStatus: string implements HasColor, HasLabel
{
    case SedangDikerjakan = 'sedang_dikerjakan';
    case Selesai = 'selesai';

    public function getLabel(): string
    {
        return match ($this) {
            self::SedangDikerjakan => 'Sedang Dikerjakan',
            self::Selesai => 'Selesai',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SedangDikerjakan => 'warning',
            self::Selesai => 'success',
        };
    }
}
