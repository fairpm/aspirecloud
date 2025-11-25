<?php
declare(strict_types=1);

namespace App\Values\WpOrg\Plugins;

use App\Values\DTO;
use Bag\Values\Optional;
use Illuminate\Support\Collection;

readonly class PluginUpdateCheckResponse extends DTO
{
    /**
     * @param Collection<string, PluginUpdateResponseItem>          $plugins
     * @param Optional|Collection<string, PluginUpdateResponseItem> $no_update
     * @param Collection<array-key, mixed>                          $translations
     */
    public function __construct(
        public Collection $plugins,
        public Optional|Collection $no_update,
        public Collection $translations,
    ) {}

    // not the best name for the method but that's what we get for a negative-named property.  $no_tea anyone?
    public function withoutNoUpdate(): self
    {
        return $this->with(no_update: new Optional());
    }
}
