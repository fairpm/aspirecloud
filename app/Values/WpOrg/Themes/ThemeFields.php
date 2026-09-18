<?php
declare(strict_types=1);

namespace App\Values\WpOrg\Themes;

use App\Values\WpOrg\ResponseFields;
use Illuminate\Http\Request;

trait ThemeFields
{
    /**
     * @param array<string, bool> $actionDefaults
     * @return array<string, bool>
     */
    private function selectFields(array $actionDefaults, bool $information = false): array
    {
        if ($this->apiVersion === '1.0') {
            return $this->fields ?? [];
        }

        $defaults = array_merge(
            self::additionalFields,
            [
                'last_updated' => false,
                'num_ratings' => true,
            ],
            $actionDefaults,
        );
        if (version_compare($this->apiVersion, '1.2', '>=')) {
            foreach ([
                'extended_author',
                'requires',
                'requires_php',
                'is_commercial',
                'is_community',
                'external_repository_url',
                'external_support_url',
            ] as $field) {
                $defaults[$field] = true;
            }
            if ($information) {
                $defaults['reviews_url'] = true;
                $defaults['creation_time'] = true;
            }
        }

        $fields = ResponseFields::resolve($this->fields, $defaults);
        // last_updated controls both representations unless the time field is explicitly overridden.
        $time = ResponseFields::resolve($this->fields, ['last_updated_time' => $fields['last_updated']]);
        $fields['last_updated_time'] = $time['last_updated_time'];
        $fields['num_ratings'] = $fields['rating'] && $fields['num_ratings'];
        $fields['description'] = $fields['description'] && !$fields['sections'];
        return $fields;
    }

    // does not include fields that are always enabled, e.g. slug, name
    public const additionalFields = [
        'description' => false,
        'downloaded' => false,
        'download_link' => false,
        'last_updated_time' => false,
        'creation_time' => false,
        'parent' => false,
        'rating' => false,
        'ratings' => false,
        'reviews_url' => false,
        'screenshot_count' => false,
        'screenshot_url' => true,
        'screenshots' => false,
        'sections' => false,
        'tags' => false,
        'template' => false,
        'versions' => false,
        'theme_url' => false,
        'homepage' => false,
        'extended_author' => false,
        'photon_screenshots' => false,
        'active_installs' => false,
        'requires' => false,
        'requires_php' => false,
        'trac_tickets' => false,
        'is_commercial' => false,
        'is_community' => false,
        'external_repository_url' => false,
        'external_support_url' => false,
        'upload_date' => false,
    ];

    /**
     * Keep the historical field selection for serialized PHP responses (API 1.0).
     *
     * @param array<string,bool> $defaultFields
     * @return array<string,bool>
     */
    private static function getLegacyFields(Request $request, array $defaultFields = []): array
    {
        $specifiedFields = $request->query('fields');
        if (!$specifiedFields) {
            return array_merge(self::additionalFields, $defaultFields);
        }

        if (!is_array($specifiedFields)) {
            $specifiedFields = explode(',', $specifiedFields);
        }

        // Indexed array: eg: [ 'field1', 'field2' ]
        if (array_keys($specifiedFields) === range(0, count($specifiedFields) - 1)) {
            // Convert [ 'field1', 'field2' ] => [ 'field1' => true, 'field2' => true ]
            $specifiedFields = array_combine($specifiedFields, array_fill(0, count($specifiedFields), true));
        } else {
            // [ 'field1' => 1, 'field2' => 'false'] => [ 'field1' => true, 'field2' => false ]
            $specifiedFields = array_map(
                function ($value) {
                    if (is_string($value)) {
                        $value = strtolower($value); // Make the string case-insensitive
                        if ($value === '1' || $value === 'true') {
                            return true;
                        } elseif ($value === '0' || $value === 'false') {
                            return false;
                        }
                    }
                    return $value;
                },
                $specifiedFields,
            );
        }

        return array_merge(self::additionalFields, $specifiedFields, $defaultFields);
    }
}
