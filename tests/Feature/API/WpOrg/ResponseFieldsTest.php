<?php
declare(strict_types=1);

use App\Models\Package;
use App\Models\WpOrg\Author;
use App\Models\WpOrg\Plugin;
use App\Models\WpOrg\Theme;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['feature.underscore_fair_hack' => false]);
    Plugin::query()->delete();
    $this->metadata = [
        'sections' => [
            'description' => '<p>Description</p>',
            'changelog' => '<p>Changes</p>',
            'reviews' => '<p>Reviews</p>',
        ],
        'versions' => ['1.0' => 'https://downloads.wordpress.org/plugin/field-test.1.0.zip'],
        'icons' => ['1x' => 'https://example.org/icon.png'],
        'banners' => ['low' => 'https://example.org/banner.png'],
        'screenshots' => [['src' => 'https://example.org/screenshot.png', 'caption' => 'Screenshot']],
        'ratings' => ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 1],
        'requires_plugins' => [],
        'upgrade_notice' => ['1.0' => 'Please update'],
    ];
    $this->plugin = Plugin::factory()->create([
        'slug' => 'field-test',
        'version' => '1.0',
        'ac_origin' => 'wp_org',
        'download_link' => 'https://downloads.wordpress.org/plugin/field-test.zip',
        'ac_raw_metadata' => $this->metadata,
    ]);
    $this->plugin->contributors()->attach(Author::factory()->create()->id);
    $this->theme = Theme::factory()->create([
        'slug' => 'field-test-theme',
        'version' => '1.0',
        'ac_origin' => 'wp_org',
        'download_link' => 'https://downloads.wordpress.org/theme/field-test-theme.1.0.zip',
        'ac_raw_metadata' => $this->metadata,
    ]);
});

function fieldsTestUri(string $type, string $action, mixed $fields = [], string $version = '1.2'): string
{
    return (
        "/$type/info/$version?"
        . http_build_query([
            'action' => $action,
            'request' => [
                'slug' => $type === 'plugins' ? 'field-test' : 'field-test-theme',
                'page' => 1,
                'per_page' => 36,
                'locale' => 'en_US',
                'wp_version' => '7.1',
                'fields' => $fields,
            ],
        ])
    );
}

it('uses listing defaults for the core Add Plugins request without fields', function () {
    $response = $this->get(fieldsTestUri('plugins', 'query_plugins'))->assertOk();
    $plugin = $response->json('plugins.0');
    expect($plugin)->toHaveKeys([
        'slug',
        'description',
        'short_description',
        'icons',
        'downloaded',
        'ac_origin',
        'ac_created',
    ]);
    foreach ([
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
    ] as $field) {
        expect($plugin)->not->toHaveKey($field);
    }
    $response->assertJsonPath('info.results', 1)
        ->assertJsonPath(
            'plugins.0.download_link',
            'https://api.aspirecloud.localhost/download/plugin/field-test.1.0.zip',
        );
});

it('uses detail defaults for the core plugin modal request', function () {
    $plugin = $this->get(fieldsTestUri('plugins', 'plugin_information'))->assertOk()->json();
    expect($plugin)->toHaveKeys(['sections', 'versions', 'contributors', 'banners', 'ac_origin', 'ac_created']);
    foreach (['description', 'short_description', 'icons', 'downloaded'] as $field) {
        expect($plugin)->not->toHaveKey($field);
    }
});

it('honors sections=false in the core AJAX installation request', function (string $type, string $action) {
    $response = $this->get(fieldsTestUri($type, $action, ['sections' => '0']))->assertOk();
    expect($response->json())->not->toHaveKey('sections')->not->toHaveKey('description')->toHaveKey('download_link');
})->with([
    ['plugins', 'plugin_information'],
    ['themes',  'theme_information'],
]);

it('allows core dependency requests to add listing fields to plugin details', function () {
    $this->get(fieldsTestUri('plugins', 'plugin_information', [
        'short_description' => true,
        'icons' => true,
    ]))->assertOk()->assertJsonStructure(['short_description', 'icons', 'sections']);
});

it('honors explicitly enabled and disabled plugin fields', function (string $action, string $prefix) {
    $response = $this->get(fieldsTestUri('plugins', $action, [
        'versions' => 'true',
        'sections' => '1',
        'reviews' => 'false',
        'contributors' => '0',
        'description' => 'false',
        'downloadlink' => '0',
        'rating' => '0',
    ]))->assertOk();
    $data = $prefix === '' ? $response->json() : $response->json($prefix);
    expect($data)
        ->toHaveKeys(['sections', 'versions', 'num_ratings'])
        ->not->toHaveKey('contributors')
        ->not->toHaveKey('description')
        ->not->toHaveKey('download_link')
        ->not->toHaveKey('rating');
    expect($data['sections'])->not->toHaveKey('reviews');
})->with([
    ['query_plugins',      'plugins.0'],
    ['plugin_information', ''],
]);

it('uses theme listing defaults for the core Customizer request', function () {
    $theme = $this->get(fieldsTestUri('themes', 'query_themes', ['reviews_url' => true]))->assertOk()->json('themes.0');
    expect($theme)->toHaveKeys([
        'description',
        'author',
        'rating',
        'num_ratings',
        'reviews_url',
        'requires',
        'requires_php',
    ]);
    foreach ([
        'sections',
        'versions',
        'downloaded',
        'download_link',
        'active_installs',
        'tags',
        'ratings',
        'last_updated',
    ] as $field) {
        expect($theme)->not->toHaveKey($field);
    }
    expect($theme['author'])->toBeArray();
});

it('honors the explicit fields from the core Add Themes screen', function () {
    $fields = [
        'description' => true,
        'sections' => false,
        'tested' => true,
        'requires' => true,
        'rating' => true,
        'downloaded' => true,
        'downloadlink' => true,
        'last_updated' => true,
        'homepage' => true,
        'tags' => true,
        'num_ratings' => true,
        'reviews_url' => true,
    ];
    $theme = $this->get(fieldsTestUri('themes', 'query_themes', $fields))->assertOk()->json('themes.0');
    expect($theme)
        ->toHaveKeys([
            'description',
            'downloaded',
            'download_link',
            'last_updated',
            'last_updated_time',
            'tags',
            'reviews_url',
        ])
        ->not->toHaveKey('sections')
        ->not->toHaveKey('versions');
});

it('applies theme field dependencies and explicit author overrides', function (string $version) {
    $theme = $this->get(fieldsTestUri(
        'themes',
        'theme_information',
        [
            'description' => true,
            'sections' => true,
            'last_updated' => false,
            'rating' => false,
            'extended_author' => false,
            'versions' => true,
        ],
        $version,
    ))
        ->assertOk()
        ->json();
    expect($theme)
        ->toHaveKeys(['sections', 'versions'])
        ->not->toHaveKey('description')
        ->not->toHaveKey('last_updated')
        ->not->toHaveKey('last_updated_time')
        ->not->toHaveKey('rating')
        ->not->toHaveKey('num_ratings');
    expect($theme['author'])->toBeString();
})->with(['1.1', '1.2']);

it('does not load contributors unless the plugin response needs them', function () {
    DB::enableQueryLog();
    $this->get(fieldsTestUri('plugins', 'query_plugins'))->assertOk();
    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();
    expect(implode("\n", $queries))->not->toContain('plugin_authors');

    $this->get(fieldsTestUri('plugins', 'query_plugins', ['contributors' => true]))
        ->assertOk()->assertJsonCount(1, 'plugins.0.contributors');
});

it('keeps listing payloads independent of the size of unrequested history', function (
    string $type,
    string $action,
    string $key,
) {
    $uri = fieldsTestUri($type, $action);
    $before = $this->get($uri)->assertOk()->json();
    $model = $type === 'plugins' ? $this->plugin : $this->theme;
    $metadata = $this->metadata;
    $metadata['sections']['changelog'] = str_repeat('<p>Historical release notes.</p>', 10000);
    for ($i = 1; $i <= 500; $i++) {
        $metadata['versions']["0.$i"] = "https://example.org/releases/0.$i.zip";
    }
    $model->update(['ac_raw_metadata' => $metadata]);
    $after = $this->get($uri)->assertOk()->json();
    expect($after)->toBe($before);
    $expanded = $this->get(fieldsTestUri($type, $action, ['sections' => true, 'versions' => true]))->assertOk();
    expect($expanded->json("$key.0.sections.changelog") === $metadata['sections']['changelog'])->toBeTrue();
    $expanded->assertJsonCount(501, "$key.0.versions");
})->with([
    ['plugins', 'query_plugins', 'plugins'],
    ['themes',  'query_themes',  'themes'],
]);

it('preserves complete FAIR metadata independently of WordPress fields', function (
    string $type,
    string $action,
    string $prefix,
) {
    config(['feature.underscore_fair_hack' => true]);
    $slug = $type === 'plugins' ? 'field-test' : 'field-test-theme';
    $metadata = [
        '@context' => 'https://fair.pm/ns/metadata/v1',
        'id' => 'did:plc:fieldtest',
        'slug' => $slug,
        'sections' => ['description' => 'Full FAIR description'],
        'releases' => [[
            'version' => '1.0',
            'artifacts' => ['package' => [[
                'url' => 'https://example.org/archive.zip',
                'signature' => 'original-signature',
            ]]],
        ]],
        'x-extension' => ['preserve' => true],
    ];
    $package = Package::factory()->create(['slug' => $slug, 'did' => $metadata['id'], 'raw_metadata' => $metadata]);
    $uri = fieldsTestUri($type, $action, ['sections' => false, 'versions' => false, '_fair' => false]);
    $response = $this->get($uri . '&_fair=1.6.0')->assertOk();
    $data = $prefix === '' ? $response->json() : $response->json($prefix);
    expect($data)->not->toHaveKey('sections')->not->toHaveKey('versions');
    expect($data['_fair'])->toBe($package->fresh()->raw_metadata);
    $this->get('/packages/' . $metadata['id'])->assertOk()->assertExactJson($metadata);
    expect($response->headers->get('Cache-Control'))->toContain('public', 's-maxage=300');
})->with([
    ['plugins', 'query_plugins',      'plugins.0'],
    ['plugins', 'plugin_information', ''],
    ['themes',  'query_themes',       'themes.0'],
    ['themes',  'theme_information',  ''],
]);

it('only includes inline FAIR metadata when both switches are enabled', function (
    string $type,
    string $action,
    string $prefix,
    bool $enabled,
    bool $requested,
) {
    config(['feature.underscore_fair_hack' => $enabled]);
    $slug = $type === 'plugins' ? 'field-test' : 'field-test-theme';
    $package = Package::factory()->create([
        'slug' => $slug,
        'raw_metadata' => ['slug' => $slug, 'releases' => [['version' => '1.0']]],
    ]);
    $uri = fieldsTestUri($type, $action, ['sections' => false, 'versions' => false]);
    $response = $this->get($uri . ($requested ? '&_fair=1' : ''))->assertOk();
    $data = $prefix === '' ? $response->json() : $response->json($prefix);
    expect($data)->not->toHaveKey('sections')->not->toHaveKey('versions');
    if ($enabled && $requested) {
        expect($data['_fair'])->toBe($package->fresh()->raw_metadata);
    } else {
        expect($data)->not->toHaveKey('_fair');
    }
})->with([
    ['plugins', 'query_plugins',      'plugins.0'],
    ['plugins', 'plugin_information', ''],
    ['themes',  'query_themes',       'themes.0'],
    ['themes',  'theme_information',  ''],
])->with([[false, false], [false, true], [true, false], [true, true]]);

it('ignores unknown fields and cannot suppress identity or AspireCloud metadata', function (
    string $type,
    string $action,
    string $prefix,
) {
    $response = $this->get(fieldsTestUri($type, $action, [
        'name' => false,
        'slug' => false,
        'version' => false,
        'ac_origin' => false,
        'ac_created' => false,
        'unknown' => true,
        'ac_raw_metadata' => true,
        'extended_author' => ['invalid'],
    ]))->assertOk();
    $data = $prefix === '' ? $response->json() : $response->json($prefix);
    expect($data)
        ->toHaveKeys(['name', 'slug', 'version', 'ac_origin', 'ac_created'])
        ->not->toHaveKey('unknown')
        ->not->toHaveKey('ac_raw_metadata');
})->with([
    ['plugins', 'query_plugins',      'plugins.0'],
    ['plugins', 'plugin_information', ''],
    ['themes',  'query_themes',       'themes.0'],
    ['themes',  'theme_information',  ''],
]);

it('keeps the plugin 1.0 response unfiltered', function () {
    $this->get('/plugins/info/1.0/field-test.json?fields[sections]=0&fields[versions]=0')
        ->assertOk()
        ->assertExactJson(\App\Values\WpOrg\Plugins\PluginResponse::from($this->plugin->fresh())->toArray());
});

it('keeps the theme 1.0 serialized response and legacy field precedence', function () {
    $response = $this->get(fieldsTestUri('themes', 'theme_information', ['sections' => false], '1.0'))->assertOk();
    $theme = unserialize($response->getContent());
    expect($theme)->toBeInstanceOf(\App\Values\WpOrg\Themes\ThemeResponse::class);
    expect($theme->slug)->toBe('field-test-theme');
    expect($theme->sections)->toBe($this->theme->fresh()->sections);
    expect($theme->description)->toBeInstanceOf(\Bag\Values\Optional::class);
    expect($theme->versions)->toBeInstanceOf(\Bag\Values\Optional::class);
    expect($theme->author)->toBeString();
    expect($theme->extended_author)->toBeInstanceOf(\App\Values\WpOrg\Author::class);
});

it('does not lazily load plugin contributors for installation details', function () {
    $plugin = $this->plugin->fresh();
    $fields = \App\Values\WpOrg\Plugins\PluginFields::resolve(['contributors' => false], information: true);
    \App\Values\WpOrg\Plugins\PluginResponse::fromPlugin($plugin, $fields);
    expect($plugin->relationLoaded('contributors'))->toBeFalse();
});

it('accepts list and comma-separated fields through the HTTP request DTOs', function (
    string $type,
    string $action,
    string $prefix,
    mixed $fields,
) {
    $response = $this->get(fieldsTestUri($type, $action, $fields))->assertOk();
    $data = $prefix === '' ? $response->json() : $response->json($prefix);
    expect($data)->toHaveKeys(['sections', 'versions']);
})->with([
    ['plugins', 'query_plugins',      'plugins.0'],
    ['plugins', 'plugin_information', ''],
    ['themes',  'query_themes',       'themes.0'],
    ['themes',  'theme_information',  ''],
])->with([[['sections', 'versions']], ['sections,versions']]);

it('computes an enabled ETag from the final filtered response including FAIR metadata', function (bool $fair) {
    config(['feature.underscore_fair_hack' => $fair]);
    // Exercise Laravel's optional ETag support without changing the production cache configuration.
    $this->app['router']->pushMiddlewareToGroup('api', 'cache.headers:etag');
    Package::factory()->create([
        'slug' => 'field-test',
        'raw_metadata' => ['id' => 'did:plc:fieldtest', 'releases' => [['version' => '1.0']]],
    ]);
    $uri = fieldsTestUri('plugins', 'plugin_information', ['sections' => false]) . '&_fair=1';
    $response = $this->get($uri)->assertOk();
    $etag = '"' . hash('xxh128', $response->getContent()) . '"';
    expect($response->headers->get('ETag'))->toBe($etag);
    $this->get($uri, ['If-None-Match' => $etag])->assertStatus(304);
})->with([false, true]);
