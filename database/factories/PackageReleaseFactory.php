<?php
declare(strict_types=1);

namespace Database\Factories;

use App\Models\Package;
use App\Models\PackageRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PackageRelease> */
class PackageReleaseFactory extends Factory
{
    protected $model = PackageRelease::class;

    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'version' => $this->faker->semver(),
            'download_url' => $this->faker->url(),
            'requires' => [
                'env:php' => $this->faker->randomElement(['^8.0', '^8.1', '^8.2', '^8.3']),
            ],
            'suggests' => [
                'another-plugin' => $this->faker->semver(),
            ],
            'provides' => [
                'some-feature' => $this->faker->semver(),
            ],
            'artifacts' => [
                'package' => [
                    [
                        'url' => $this->faker->url(),
                        'type' => 'zip',
                    ],
                ],
            ],
        ];
    }

    public function typo3(): static
    {
        return $this->state(fn () => [
            'requires' => [
                'env:typo3' => $this->faker->randomElement([
                    '>=11.5.0 <=11.99.99',
                    '>=12.4.0 <=12.99.99',
                    '>=13.4.0 <=13.99.99',
                    '>=11.5.0 <=12.99.99',
                ]),
                'env:php' => $this->faker->randomElement(['^8.1', '^8.2', '>=8.1']),
            ],
        ]);
    }
}
