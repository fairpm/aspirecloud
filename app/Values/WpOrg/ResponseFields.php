<?php
declare(strict_types=1);

namespace App\Values\WpOrg;

/** Normalizes the WordPress fields parameter without letting it select arbitrary DTO properties. */
final class ResponseFields
{
    /**
     * @param array<string, bool> $defaults
     * @return array<string, bool>
     */
    public static function resolve(mixed $requested, array $defaults): array
    {
        if (is_string($requested)) {
            $requested = explode(',', $requested);
        }
        if (!is_array($requested)) {
            return $defaults;
        }

        $overrides = [];
        if (array_is_list($requested)) {
            foreach ($requested as $field) {
                if (is_string($field)) {
                    $overrides[trim($field)] = true;
                }
            }
        } else {
            foreach ($requested as $field => $value) {
                $value = is_string($value) ? strtolower(trim($value)) : $value;
                $enabled = match ($value) {
                    true, 1, '1', 'true' => true,
                    false, 0, '0', 'false' => false,
                    default => null,
                };
                if ($enabled !== null) {
                    $overrides[$field] = $enabled;
                }
            }
        }

        // WordPress calls this downloadlink; AspireCloud also accepts its output field name.
        if (array_key_exists('downloadlink', $overrides)) {
            $overrides['download_link'] = $overrides['downloadlink'];
        }

        return array_replace($defaults, array_intersect_key($overrides, $defaults));
    }
}
