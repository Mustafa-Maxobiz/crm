<?php

namespace Webkul\Tag;

class StaticTags
{
    public const MAX_ALLOWED = 5;

    /**
     * Canonical static tag definitions.
     *
     * @return array<int, array{name: string, color: string}>
     */
    public static function definitions(): array
    {
        return [
            ['name' => 'Do Not Call', 'color' => '#FEE2E2'],
            ['name' => 'Not Answer', 'color' => '#FEF3C7'],
            ['name' => 'Cold Lead', 'color' => '#DBEAFE'],
            ['name' => 'Warm Lead', 'color' => '#DCFCE7'],
            ['name' => 'Incorrect Info', 'color' => '#FFEDD5'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::definitions(), 'name');
    }

    public static function maxAllowed(): int
    {
        return self::MAX_ALLOWED;
    }
}
