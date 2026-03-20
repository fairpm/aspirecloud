<?php
declare(strict_types=1);

namespace App\Values\Packages;

use App\Models\Package;
use App\Values\DTO;
use Bag\Attributes\Transforms;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Response DTO for the package search endpoint.
 *
 * Wraps paginated results with pagination info and FAIR metadata for each package.
 */
readonly class PackageSearchResponse extends DTO
{
    /**
     * @param array{page: int, per_page: int, total: int, pages: int} $info
     * @param list<array<string, mixed>> $packages
     */
    public function __construct(
        public array $info,
        public array $packages,
    ) {}

    /**
     * Transform a paginator of Package models into the response structure.
     *
     * Each package is converted to its FAIR metadata representation.
     *
     * @param LengthAwarePaginator<int, Package> $paginator
     * @return array<string, mixed>
     */
    #[Transforms(LengthAwarePaginator::class)]
    public static function fromPaginator(LengthAwarePaginator $paginator): array
    {
        return [
            'info' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pages' => $paginator->lastPage(),
            ],
            'packages' => collect($paginator->items())->map(
                fn (Package $package) => FairMetadata::from($package)->toArray()
            )->all(),
        ];
    }
}
