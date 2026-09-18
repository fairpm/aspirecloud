<?php
declare(strict_types=1);

namespace App\Values\WpOrg\Plugins;

use App\Values\WpOrg\ResponseFields;

final class PluginFields
{
    /** @return array<string, bool> */
    public static function resolve(mixed $requested, bool $information = false): array
    {
        $common = array_fill_keys([
            'requires',
            'tested',
            'requires_php',
            'download_link',
            'author',
            'author_profile',
            'rating',
            'num_ratings',
            'ratings',
            'support_threads',
            'support_threads_resolved',
            'active_installs',
            'last_updated',
            'added',
            'homepage',
            'tags',
            'donate_link',
            'requires_plugins',
        ], true);
        $listing = array_fill_keys(['downloaded', 'short_description', 'description', 'icons'], !$information);
        $details = array_fill_keys([
            'sections',
            'versions',
            'contributors',
            'screenshots',
            'support_url',
            'upgrade_notice',
            'business_model',
            'repository_url',
            'commercial_support_url',
            'banners',
            'preview_link',
            'reviews',
        ], $information);

        return ResponseFields::resolve($requested, array_merge($common, $listing, $details));
    }
}
