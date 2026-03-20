<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\FAIR\Packages;

use App\Http\Controllers\Controller;
use App\Services\Packages\PackageSearchService;
use App\Values\Packages\FairMetadata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageSearchController extends Controller
{
    public function __construct(
        private PackageSearchService $searchService,
    ) {}

    public function __invoke(Request $request, string $type): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 24);

        $results = $this->searchService->search($type, $validated['q'] ?? null, $page, $perPage);

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
