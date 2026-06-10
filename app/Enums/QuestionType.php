<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum QuestionType: string implements HasLabel
{
    case PilihanGanda = 'pilihan_ganda';
    case Essay = 'essay';

    public function getLabel(): string
    {
        return match ($this) {
            self::PilihanGanda => 'Pilihan Ganda',
            self::Essay => 'Essay',
        };
    }
}
