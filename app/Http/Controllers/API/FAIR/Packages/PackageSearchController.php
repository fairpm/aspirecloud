<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\FAIR\Packages;

use App\Http\Controllers\Controller;
use App\Services\Packages\PackageSearchService;
use App\Values\Packages\PackageSearchRequest;
use App\Values\Packages\PackageSearchResponse;

/**
 * Search and browse packages by type.
 *
 * Handles GET /packages/{type} with optional full-text search, version
 * filtering, and pagination. Returns FAIR metadata for each matching package.
 */
class PackageSearchController extends Controller
{
    public function __construct(
        private PackageSearchService $searchService,
    ) {}

    /**
     * Execute the search and return paginated FAIR metadata results.
     *
     * @return array<string, mixed>
     */
    public function __invoke(PackageSearchRequest $request): array
    {
        $results = $this->searchService->search($request);

        return PackageSearchResponse::from($results)->toArray();
    }
}
