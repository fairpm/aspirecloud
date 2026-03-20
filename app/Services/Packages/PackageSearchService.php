<?php
declare(strict_types=1);

namespace App\Services\Packages;

use App\Models\Package;
use App\Values\Packages\PackageSearchRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PackageSearchService
{
    private const EAGER_LOAD = ['releases', 'authors', 'tags', 'metas'];

    /** @return LengthAwarePaginator<int, Package> */
    public function search(PackageSearchRequest $request): LengthAwarePaginator
    {
        if ($request->q === null || $request->q === '') {
            return Package::with(self::EAGER_LOAD)
                ->where('type', $request->type)
                ->orderByDesc('created_at')
                ->paginate(perPage: $request->per_page, page: $request->page);
        }

        // Try full-text search first
        $results = $this->fullTextSearch($request);

        if ($results->total() > 0) {
            return $results;
        }

        // Fall back to trigram similarity
        return $this->trigramSearch($request);
    }

    /** @return LengthAwarePaginator<int, Package> */
    private function fullTextSearch(PackageSearchRequest $request): LengthAwarePaginator
    {
        $tsQuery = "plainto_tsquery('english', ?)";

        return Package::with(self::EAGER_LOAD)
            ->where('type', $request->type)
            ->where(function ($q) use ($tsQuery, $request) {
                $q->whereRaw("search_vector @@ {$tsQuery}", [$request->q])
                    ->orWhereExists(function ($sub) use ($request) {
                        $sub->select(DB::raw(1))
                            ->from('package_package_tag')
                            ->join('package_tags', 'package_tags.id', '=', 'package_package_tag.package_tag_id')
                            ->whereColumn('package_package_tag.package_id', 'packages.id')
                            ->whereRaw("to_tsvector('english', package_tags.name) @@ plainto_tsquery('english', ?)", [$request->q]);
                    });
            })
            ->orderByRaw("ts_rank(search_vector, {$tsQuery}) DESC", [$request->q])
            ->paginate(perPage: $request->per_page, page: $request->page);
    }

    /** @return LengthAwarePaginator<int, Package> */
    private function trigramSearch(PackageSearchRequest $request): LengthAwarePaginator
    {
        return Package::with(self::EAGER_LOAD)
            ->where('type', $request->type)
            ->whereRaw('(similarity(name, ?) > 0.1 OR similarity(slug, ?) > 0.1)', [$request->q, $request->q])
            ->orderByRaw('GREATEST(similarity(name, ?), similarity(slug, ?)) DESC', [$request->q, $request->q])
            ->paginate(perPage: $request->per_page, page: $request->page);
    }
}
