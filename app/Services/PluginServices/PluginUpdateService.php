<?php
declare(strict_types=1);

namespace App\Services\PluginServices;

use App\Models\WpOrg\Plugin;
use App\Values\WpOrg\Plugins\PluginUpdateCheckRequest;
use App\Values\WpOrg\Plugins\PluginUpdateCheckResponse;
use App\Values\WpOrg\Plugins\PluginUpdateRequestItem;
use App\Values\WpOrg\Plugins\PluginUpdateResponseItem;

class PluginUpdateService
{
    public function checkForUpdates(PluginUpdateCheckRequest $req): PluginUpdateCheckResponse
    {
        $bySlug = $req->plugins
            ->filter(fn(PluginUpdateRequestItem $item) => $item->hasValidUpdateUri())
            ->mapWithKeys(fn($item, $path) => [$this->extractSlug($path) => [$path, $item]]);

        $isUpdated = function (Plugin $plugin) use ($bySlug): bool {
            $item = $bySlug[$plugin->slug][1];
            return version_compare($plugin->version, $item->Version ?? '', '>');
        };

        $mkUpdate = function (Plugin $plugin) use ($bySlug) {
            $file = (string)$bySlug[$plugin->slug][0];
            return [$file => PluginUpdateResponseItem::from($plugin)->with(plugin: $file)];
        };

        /** @noinspection PhpParamsInspection (broken on Collection::partition) */
        [$updates, $no_updates] = Plugin::query()
            ->whereIn('slug', $bySlug->keys())
            ->get()
            ->partition($isUpdated)
            ->map(fn($collection) => $collection->mapWithKeys($mkUpdate));

        return PluginUpdateCheckResponse::from(plugins: $updates, no_update: $no_updates, translations: collect([]));
    }

    /** Extract the plugin slug from the plugin file path */
    private function extractSlug(string $pluginFile): string
    {
        return str_contains($pluginFile, '/')
            ? explode('/', $pluginFile)[0]
            : pathinfo($pluginFile, PATHINFO_FILENAME);
    }
}
