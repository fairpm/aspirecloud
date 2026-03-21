<?php
declare(strict_types=1);

use App\Models\Package;
use Database\Factories\PackageReleaseFactory;

beforeEach(function () {
    Package::truncate();
});

it('searches packages by name', function () {
    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Extension()
        ->create(['name' => 'Awesome Gallery', 'slug' => 'awesome-gallery']);

    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Extension()
        ->create(['name' => 'Simple Form', 'slug' => 'simple-form']);

    $this->getJson('/packages/typo3-extension?q=gallery')
        ->assertOk()
        ->assertJsonCount(1, 'packages')
        ->assertJsonPath('packages.0.name', 'Awesome Gallery');
});

it('searches packages by description', function () {
    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Extension()
        ->create([
            'name' => 'Alpha Extension',
            'slug' => 'alpha-extension',
            'description' => 'A powerful image optimization tool',
        ]);

    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Extension()
        ->create([
            'name' => 'Beta Extension',
            'slug' => 'beta-extension',
            'description' => 'A simple contact form builder',
        ]);

    $this->getJson('/packages/typo3-extension?q=optimization')
        ->assertOk()
        ->assertJsonCount(1, 'packages')
        ->assertJsonPath('packages.0.name', 'Alpha Extension');
});

it('searches packages by tag name', function () {
    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->withSpecificTags(['seo', 'marketing'])
        ->typo3Extension()
        ->create(['name' => 'SEO Master', 'slug' => 'seo-master']);

    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->withSpecificTags(['gallery', 'media'])
        ->typo3Extension()
        ->create(['name' => 'Photo Viewer', 'slug' => 'photo-viewer']);

    $this->getJson('/packages/typo3-extension?q=marketing')
        ->assertOk()
        ->assertJsonCount(1, 'packages')
        ->assertJsonPath('packages.0.name', 'SEO Master');
});

it('filters by type', function () {
    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Extension()
        ->create(['name' => 'TYPO3 Extension', 'slug' => 'typo3-extension']);

    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->create(['name' => 'WP Plugin', 'slug' => 'wp-plugin-test', 'type' => 'wp-plugin', 'origin' => 'wp']);

    $this->getJson('/packages/typo3-extension')
        ->assertOk()
        ->assertJsonCount(1, 'packages')
        ->assertJsonPath('packages.0.type', 'typo3-extension');

    $this->getJson('/packages/wp-plugin')
        ->assertOk()
        ->assertJsonCount(1, 'packages')
        ->assertJsonPath('packages.0.type', 'wp-plugin');
});

it('returns all packages of type when no query, newest first', function () {
    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Extension()
        ->create(['name' => 'Old Package', 'slug' => 'old-package', 'created_at' => now()->subDays(10)]);

    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Extension()
        ->create(['name' => 'New Package', 'slug' => 'new-package', 'created_at' => now()]);

    $response = $this->getJson('/packages/typo3-extension')
        ->assertOk()
        ->assertJsonCount(2, 'packages');

    expect($response->json('packages.0.name'))->toBe('New Package');
    expect($response->json('packages.1.name'))->toBe('Old Package');
});

it('paginates results', function () {
    Package::factory(30)
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Extension()
        ->create();

    $this->getJson('/packages/typo3-extension?per_page=10&page=1')
        ->assertOk()
        ->assertJsonPath('info.page', 1)
        ->assertJsonPath('info.per_page', 10)
        ->assertJsonPath('info.total', 30)
        ->assertJsonPath('info.pages', 3)
        ->assertJsonCount(10, 'packages');

    $this->getJson('/packages/typo3-extension?per_page=10&page=3')
        ->assertOk()
        ->assertJsonPath('info.page', 3)
        ->assertJsonCount(10, 'packages');
});

it('returns 404 for invalid type', function () {
    $this->getJson('/packages/invalid-type')
        ->assertNotFound();
});

it('returns FAIR metadata structure', function () {
    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Extension()
        ->create(['name' => 'Test Package', 'slug' => 'test-package']);

    $this->getJson('/packages/typo3-extension')
        ->assertOk()
        ->assertJsonStructure([
            'info' => ['page', 'per_page', 'total', 'pages'],
            'packages' => [
                '*' => [
                    '@context',
                    'id',
                    'type',
                    'license',
                    'authors',
                    'releases',
                    'slug',
                    'name',
                ],
            ],
        ]);
});

it('filters by requires version', function () {
    // Package requiring TYPO3 11.5
    $old = Package::factory()->withAuthors()->withMetas()->typo3Extension()
        ->create(['name' => 'Old Extension', 'slug' => 'old-ext']);
    $old->releases()->createMany(
        PackageReleaseFactory::new()->count(1)->make([
            'package_id' => $old->id,
            'requires' => ['typo3' => '11.5', 'php' => '8.1'],
        ])->toArray(),
    );

    // Package requiring TYPO3 13.4
    $new = Package::factory()->withAuthors()->withMetas()->typo3Extension()
        ->create(['name' => 'New Extension', 'slug' => 'new-ext']);
    $new->releases()->createMany(
        PackageReleaseFactory::new()->count(1)->make([
            'package_id' => $new->id,
            'requires' => ['typo3' => '13.4', 'php' => '8.2'],
        ])->toArray(),
    );

    // Filter for TYPO3 12.4 — only the 11.5 package qualifies
    $this->getJson('/packages/typo3-extension?requires[typo3]=12.4')
        ->assertOk()
        ->assertJsonCount(1, 'packages')
        ->assertJsonPath('packages.0.name', 'Old Extension');

    // Filter for TYPO3 13.4 — both qualify
    $this->getJson('/packages/typo3-extension?requires[typo3]=13.4')
        ->assertOk()
        ->assertJsonCount(2, 'packages');
});

it('filters by requires version combined with search', function () {
    $match = Package::factory()->withAuthors()->withMetas()->typo3Extension()
        ->create(['name' => 'Gallery Pro', 'slug' => 'gallery-pro']);
    $match->releases()->createMany(
        PackageReleaseFactory::new()->count(1)->make([
            'package_id' => $match->id,
            'requires' => ['typo3' => '12.4', 'php' => '8.1'],
        ])->toArray(),
    );

    $tooNew = Package::factory()->withAuthors()->withMetas()->typo3Extension()
        ->create(['name' => 'Gallery Ultra', 'slug' => 'gallery-ultra']);
    $tooNew->releases()->createMany(
        PackageReleaseFactory::new()->count(1)->make([
            'package_id' => $tooNew->id,
            'requires' => ['typo3' => '13.4', 'php' => '8.3'],
        ])->toArray(),
    );

    $this->getJson('/packages/typo3-extension?q=gallery&requires[typo3]=12.4')
        ->assertOk()
        ->assertJsonCount(1, 'packages')
        ->assertJsonPath('packages.0.name', 'Gallery Pro');
});

it('only includes matching releases when filtering by requires', function () {
    // Package with two releases: one for TYPO3 11.5 and one for TYPO3 13.4
    $package = Package::factory()->withAuthors()->withMetas()->typo3Extension()
        ->create(['name' => 'Multi Release Ext', 'slug' => 'multi-release-ext']);

    $package->releases()->createMany([
        PackageReleaseFactory::new()->make([
            'package_id' => $package->id,
            'version' => '1.0.0',
            'requires' => ['typo3' => '11.5', 'php' => '8.1'],
        ])->toArray(),
        PackageReleaseFactory::new()->make([
            'package_id' => $package->id,
            'version' => '2.0.0',
            'requires' => ['typo3' => '13.4', 'php' => '8.2'],
        ])->toArray(),
    ]);

    // Filter for TYPO3 12.4 — only the 11.5 release qualifies
    $response = $this->getJson('/packages/typo3-extension?requires[typo3]=12.4')
        ->assertOk()
        ->assertJsonCount(1, 'packages');

    $releases = $response->json('packages.0.releases');
    expect($releases)->toHaveCount(1);
    expect($releases[0]['version'])->toBe('1.0.0');

    // Without requires filter — both releases should be returned
    $response = $this->getJson('/packages/typo3-extension')
        ->assertOk()
        ->assertJsonCount(1, 'packages');

    $releases = $response->json('packages.0.releases');
    expect($releases)->toHaveCount(2);
});

it('returns empty packages array with zero total when no results', function () {
    $this->getJson('/packages/typo3-extension?q=nonexistent')
        ->assertOk()
        ->assertJsonPath('info.total', 0)
        ->assertJsonPath('info.pages', 1)
        ->assertJsonCount(0, 'packages');
});
