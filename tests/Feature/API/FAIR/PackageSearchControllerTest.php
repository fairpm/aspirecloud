<?php
declare(strict_types=1);

use App\Models\Package;

beforeEach(function () {
    Package::truncate();
});

it('searches packages by name', function () {
    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Plugin()
        ->create(['name' => 'Awesome Gallery', 'slug' => 'awesome-gallery']);

    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Plugin()
        ->create(['name' => 'Simple Form', 'slug' => 'simple-form']);

    $this->getJson('/packages/typo3-plugin?q=gallery')
        ->assertOk()
        ->assertJsonCount(1, 'packages')
        ->assertJsonPath('packages.0.name', 'Awesome Gallery');
});

it('searches packages by description', function () {
    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Plugin()
        ->create([
            'name' => 'Alpha Extension',
            'slug' => 'alpha-extension',
            'description' => 'A powerful image optimization tool',
        ]);

    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Plugin()
        ->create([
            'name' => 'Beta Extension',
            'slug' => 'beta-extension',
            'description' => 'A simple contact form builder',
        ]);

    $this->getJson('/packages/typo3-plugin?q=optimization')
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
        ->typo3Plugin()
        ->create(['name' => 'SEO Master', 'slug' => 'seo-master']);

    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->withSpecificTags(['gallery', 'media'])
        ->typo3Plugin()
        ->create(['name' => 'Photo Viewer', 'slug' => 'photo-viewer']);

    $this->getJson('/packages/typo3-plugin?q=marketing')
        ->assertOk()
        ->assertJsonCount(1, 'packages')
        ->assertJsonPath('packages.0.name', 'SEO Master');
});

it('filters by type', function () {
    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Plugin()
        ->create(['name' => 'TYPO3 Extension', 'slug' => 'typo3-extension']);

    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->create(['name' => 'WP Plugin', 'slug' => 'wp-plugin-test', 'type' => 'wp-plugin', 'origin' => 'wp']);

    $this->getJson('/packages/typo3-plugin')
        ->assertOk()
        ->assertJsonCount(1, 'packages')
        ->assertJsonPath('packages.0.type', 'typo3-plugin');

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
        ->typo3Plugin()
        ->create(['name' => 'Old Package', 'slug' => 'old-package', 'created_at' => now()->subDays(10)]);

    Package::factory()
        ->withAuthors()
        ->withReleases()
        ->withMetas()
        ->typo3Plugin()
        ->create(['name' => 'New Package', 'slug' => 'new-package', 'created_at' => now()]);

    $response = $this->getJson('/packages/typo3-plugin')
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
        ->typo3Plugin()
        ->create();

    $this->getJson('/packages/typo3-plugin?per_page=10&page=1')
        ->assertOk()
        ->assertJsonPath('info.page', 1)
        ->assertJsonPath('info.per_page', 10)
        ->assertJsonPath('info.total', 30)
        ->assertJsonPath('info.pages', 3)
        ->assertJsonCount(10, 'packages');

    $this->getJson('/packages/typo3-plugin?per_page=10&page=3')
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
        ->typo3Plugin()
        ->create(['name' => 'Test Package', 'slug' => 'test-package']);

    $this->getJson('/packages/typo3-plugin')
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

it('returns empty packages array with zero total when no results', function () {
    $this->getJson('/packages/typo3-plugin?q=nonexistent')
        ->assertOk()
        ->assertJsonPath('info.total', 0)
        ->assertJsonPath('info.pages', 1)
        ->assertJsonCount(0, 'packages');
});
