<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\FAIR\Packages;

use App\Http\Controllers\Controller;
use App\Services\Packages\PackageSearchService;
use App\Values\Packages\FairMetadata;
use App\Values\Packages\PackageSearchRequest;
use Illuminate\Http\JsonResponse;

class PackageSearchController extends Controller
{
    public function __construct(
        private PackageSearchService $searchService,
    ) {}

    public function __invoke(PackageSearchRequest $request): JsonResponse
    {
        $results = $this->searchService->search($request);

        $packages = collect($results->items())->map(
            fn ($package) => FairMetadata::from($package)->toArray()
        );

        return response()->json([
            'info' => [
                'page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'pages' => $results->lastPage(),
            ],
            'packages' => $packages,
        ]);
    }
}
