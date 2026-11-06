<?php

declare(strict_types=1);

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case Indonesian = 'id';

    public function label(): string
    {
        return match ($this) {
            self::English => __('English'),
            self::Indonesian => __('Bahasa Indonesia'),
        };
    }
}
