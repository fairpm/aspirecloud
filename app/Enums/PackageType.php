<?php
declare(strict_types=1);

namespace App\Enums;

enum PackageType: string
{
    case CORE = 'wp-core';
    case PLUGIN = 'wp-plugin';
    case THEME = 'wp-theme';
    case TYPO3_CORE = 'typo3-core';
    case TYPO3_EXTENSION = 'typo3-extension';
    case TYPO3_THEME = 'typo3-theme';

    /**
     * Get the list of package type values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
