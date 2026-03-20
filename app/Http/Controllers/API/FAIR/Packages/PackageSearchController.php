<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\FAIR\Packages;

use App\Http\Controllers\Controller;
use App\Services\Packages\PackageSearchService;
use App\Values\Packages\PackageSearchRequest;
use App\Values\Packages\PackageSearchResponse;

class PackageSearchController extends Controller
{
    public function __construct(
        private PackageSearchService $searchService,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(PackageSearchRequest $request): array
    {
        $results = $this->searchService->search($request);

        return PackageSearchResponse::from($results)->toArray();
    }
}
