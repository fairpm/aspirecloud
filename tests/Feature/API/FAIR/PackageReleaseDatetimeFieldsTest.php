<?php
declare(strict_types=1);

use App\Models\Package;

beforeEach(function () {
    Package::truncate();
});

it('includes reported and discovered datetime fields in release output', function () {
    Package::factory()
        ->withAuthors()
        ->withReleases(1)
        ->withMetas()
        ->create([
            'did' => 'fake:datetime-test',
            'name' => 'Datetime Test Package',
            'slug' => 'datetime-test',
            'origin' => 'wp',
            'type' => 'wp-plugin',
            'license' => 'GPLv2',
            'raw_metadata' => [],
        ]);

    $response = $this->getJson('/packages/fake:datetime-test')
        ->assertStatus(200);

    $releases = $response->json('releases');
    expect($releases)->toBeArray()->not->toBeEmpty();

    $release = $releases[0];
    expect($release)
        ->toHaveKeys(['reported', 'discovered'])
        ->and($release['reported'])->toBeString()
        ->and($release['discovered'])->toBeString();
});

it('returns null for reported when it is not set', function () {
    $package = Package::factory()
        ->withAuthors()
        ->withMetas()
        ->create([
            'did' => 'fake:null-reported-test',
            'name' => 'Null Reported Test',
            'slug' => 'null-reported-test',
            'origin' => 'wp',
            'type' => 'wp-plugin',
            'license' => 'GPLv2',
            'raw_metadata' => [],
        ]);

    // Create a release without a reported value
    $package->releases()->create([
        'version' => '1.0.0',
        'download_url' => 'https://example.com/test.zip',
        'reported' => null,
        'artifacts' => [
            'package' => [['url' => 'https://example.com/test.zip', 'type' => 'zip']],
        ],
    ]);

    $response = $this->getJson('/packages/fake:null-reported-test')
        ->assertStatus(200);

    $releases = $response->json('releases');
    expect($releases)->toBeArray()->not->toBeEmpty();

    $release = $releases[0];
    expect($release)
        ->toHaveKey('reported')
        ->toHaveKey('discovered')
        ->and($release['reported'])->toBeNull()
        ->and($release['discovered'])->toBeString();
});
