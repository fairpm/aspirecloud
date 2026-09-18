<?php

declare(strict_types=1);

namespace App\Values\WpOrg\Plugins;

use App\Models\WpOrg\Author as AuthorModel;
use App\Models\WpOrg\Plugin;
use App\Utils\Regex;
use App\Values\DTO;
use App\Values\WpOrg\Author;
use Bag\Attributes\Transforms;
use Bag\Values\Optional;
use DateTimeInterface;

readonly class PluginResponse extends DTO
{
    public const LAST_UPDATED_DATE_FORMAT = 'Y-m-d h:ia T'; // .org's goofy format: "2024-09-27 9:53pm GMT"

    /**
     * @param Optional|array<array-key, mixed> $banners
     * @param Optional|array<array-key, array{src: string, caption: string}> $screenshots
     * @param Optional|array<string, Author> $contributors
     * @param Optional|array<string, string> $versions
     * @param Optional|array<string, string> $sections
     * @param Optional|array{"1":int, "2":int, "3":int, "4":int, "5":int} $ratings
     * @param Optional|list<string> $requires_plugins
     * @param Optional|array<string, string> $icons
     * @param Optional|array<string, string>|null $upgrade_notice
     * @param Optional|array<string, string> $tags
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $version,
        public Optional|string|null $requires,
        public Optional|string|null $tested,
        public Optional|string|null $requires_php,
        public Optional|string $download_link,
        public Optional|string $author,
        public Optional|string|null $author_profile,
        public Optional|int $rating,
        public Optional|int $num_ratings,
        public Optional|array $ratings,
        public Optional|int $support_threads,
        public Optional|int $support_threads_resolved,
        public Optional|int $active_installs,
        public Optional|string|null $last_updated,
        public Optional|string|null $added,
        public Optional|string|null $homepage,
        public Optional|array $tags,
        public Optional|string|null $donate_link,
        public Optional|array $requires_plugins,

        // query_plugins defaults
        public Optional|string|null $downloaded,
        public Optional|string|null $short_description,
        public Optional|string|null $description,
        public Optional|array $icons,

        // plugin_information defaults
        public Optional|array $sections,
        public Optional|array $versions,
        public Optional|array $contributors,
        public Optional|array $screenshots,
        public Optional|string|null $support_url,
        public Optional|array|null $upgrade_notice,
        public Optional|string|null $business_model,
        public Optional|string|null $repository_url,
        public Optional|string|null $commercial_support_url,
        public Optional|array $banners,
        public Optional|string|null $preview_link,

        // aspirecloud metadata
        public Optional|string $ac_origin,
        public Optional|DateTimeInterface $ac_created,
    ) {}

    /** @param array<string, bool>|null $fields Null includes all fields. */
    public static function fromModel(Plugin $plugin, null|array $fields = null): static
    {
        return static::from(static::fromPlugin($plugin, $fields));
    }

    /**
     * @param array<string, bool>|null $fields Null preserves the legacy unfiltered response.
     * @return array<string, mixed>
     */
    #[Transforms(Plugin::class)]
    public static function fromPlugin(Plugin $plugin, null|array $fields = null): array
    {
        $none = new Optional();

        $data = [
            // common
            'name' => $plugin->name,
            'slug' => $plugin->slug,
            'version' => $plugin->version,
            'requires' => $fields['requires'] ?? true ? $plugin->requires : $none,
            'tested' => $fields['tested'] ?? true ? $plugin->tested : $none,
            'requires_php' => $fields['requires_php'] ?? true ? $plugin->requires_php : $none,
            'download_link' => $fields['download_link'] ?? true ? $plugin->download_link : $none,
            'author' => $fields['author'] ?? true ? $plugin->author : $none,
            'author_profile' => $fields['author_profile'] ?? true ? $plugin->author_profile : $none,
            'rating' => $fields['rating'] ?? true ? $plugin->rating : $none,
            'num_ratings' => $fields['num_ratings'] ?? true ? $plugin->num_ratings : $none,
            'ratings' => $fields['ratings'] ?? true ? $plugin->ratings : $none,
            'support_threads' => $fields['support_threads'] ?? true ? $plugin->support_threads : $none,
            'support_threads_resolved' => $fields['support_threads_resolved'] ?? true
                ? $plugin->support_threads_resolved
                : $none,
            'active_installs' => $fields['active_installs'] ?? true ? $plugin->active_installs : $none,
            'last_updated' => $fields['last_updated'] ?? true ? self::formatLastUpdated($plugin->last_updated) : $none,
            'added' => $fields['added'] ?? true ? $plugin->added?->format('Y-m-d') : $none,
            'homepage' => $fields['homepage'] ?? true ? $plugin->homepage : $none,
            'tags' => $fields['tags'] ?? true ? $plugin->tagsArray() : $none,
            'donate_link' => $fields['donate_link'] ?? true ? $plugin->donate_link : $none,
            'requires_plugins' => $fields['requires_plugins'] ?? true ? $plugin->requires_plugins : $none,
            // query_plugins defaults
            'downloaded' => $fields['downloaded'] ?? true ? $plugin->downloaded : $none,
            'short_description' => $fields['short_description'] ?? true ? $plugin->short_description : $none,
            'description' => $fields['description'] ?? true ? $plugin->description : $none,
            'icons' => $fields['icons'] ?? true ? $plugin->icons : $none,
            // plugin_information defaults
            'sections' => $fields['sections'] ?? true ? $plugin->sections : $none,
            'versions' => $fields['versions'] ?? true ? $plugin->versions : $none,
            'contributors' => $fields['contributors'] ?? true
                ? $plugin
                    ->contributors
                    ->mapWithKeys(
                        fn(AuthorModel $model) => [$model->user_nicename => Author::from($model)],
                    )
                    ->toArray()
                : $none,
            'screenshots' => $fields['screenshots'] ?? true ? $plugin->screenshots : $none,
            'support_url' => $fields['support_url'] ?? true ? $plugin->support_url : $none,
            'upgrade_notice' => $fields['upgrade_notice'] ?? true ? ($plugin->upgrade_notice ?: $none) : $none,
            'business_model' => $fields['business_model'] ?? true ? $plugin->business_model : $none,
            'repository_url' => $fields['repository_url'] ?? true ? $plugin->repository_url : $none,
            'commercial_support_url' => $fields['commercial_support_url'] ?? true
                ? $plugin->commercial_support_url
                : $none,
            'banners' => $fields['banners'] ?? true ? $plugin->banners : $none,
            'preview_link' => $fields['preview_link'] ?? true ? $plugin->preview_link : $none,
            // aspirecloud metadata
            'ac_origin' => $plugin->ac_origin,
            'ac_created' => $plugin->ac_created,
        ];
        if (is_array($data['sections']) && !($fields['reviews'] ?? true)) {
            unset($data['sections']['reviews']);
        }
        return $data;
    }

    private static function formatLastUpdated(null|DateTimeInterface $lastUpdated): null|string
    {
        if ($lastUpdated === null) {
            return null;
        }
        $out = $lastUpdated->format(self::LAST_UPDATED_DATE_FORMAT);
        // Unfortunately this seems to render GMT as "GMT+0000" for some reason, so strip that out
        return Regex::replace('/\+\d+$/', '', $out);
    }
}
