<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class StyleGuideSettings extends Settings
{
    public string $content;

    public int $version;

    public ?string $updated_at;

    public static function group(): string
    {
        return 'style_guide';
    }
}
