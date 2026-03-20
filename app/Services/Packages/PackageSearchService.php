<?php
declare(strict_types=1);

namespace App\Services\Packages;

use App\Models\Package;
use App\Values\Packages\PackageSearchRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Searches packages using Postgres full-text search with trigram fallback.
 *
 * Search strategy with a query: try tsvector full-text search (including tag matching)
 * first, fall back to trigram similarity on name/slug if no FTS results.
 * Without a query: return all packages of the given type, newest first.
 */
class PackageSearchService
{
    /** @var list<string> Relations to eager load to avoid N+1 queries when building FAIR metadata. */
    private const EAGER_LOAD = ['releases', 'authors', 'tags', 'metas'];

    /**
     * Search packages by type with optional query and version requirements.
     *
     * @return LengthAwarePaginator<int, Package>
     */
    public function search(PackageSearchRequest $request): LengthAwarePaginator
    {
        if ($request->q === null || $request->q === '') {
            $query = Package::with(self::EAGER_LOAD)
                ->where('type', $request->type)
                ->orderByDesc('created_at');

            $this->applyRequiresFilter($query, $request);

            return $query->paginate(perPage: $request->per_page, page: $request->page);
        }

        // Try full-text search first
        $results = $this->fullTextSearch($request);

        if ($results->total() > 0) {
            return $results;
        }

        // Fall back to trigram similarity
        return $this->trigramSearch($request);
    }

    /**
     * Search using Postgres tsvector full-text search, ranked by ts_rank.
     *
     * Also matches packages whose tags match the query via a subquery join.
     *
     * @return LengthAwarePaginator<int, Package>
     */
    private function fullTextSearch(PackageSearchRequest $request): LengthAwarePaginator
    {
        $tsQuery = "plainto_tsquery('english', ?)";

        $query = Package::with(self::EAGER_LOAD)
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
            ->orderByRaw("ts_rank(search_vector, {$tsQuery}) DESC", [$request->q]);

        $this->applyRequiresFilter($query, $request);

        return $query->paginate(perPage: $request->per_page, page: $request->page);
    }

    /**
     * Fallback search using pg_trgm trigram similarity on name and slug.
     *
     * Used when full-text search returns no results, to catch fuzzy/partial matches.
     *
     * @return LengthAwarePaginator<int, Package>
     */
    private function trigramSearch(PackageSearchRequest $request): LengthAwarePaginator
    {
        $query = Package::with(self::EAGER_LOAD)
            ->where('type', $request->type)
            ->whereRaw('(similarity(name, ?) > 0.1 OR similarity(slug, ?) > 0.1)', [$request->q, $request->q])
            ->orderByRaw('GREATEST(similarity(name, ?), similarity(slug, ?)) DESC', [$request->q, $request->q]);

        $this->applyRequiresFilter($query, $request);

        return $query->paginate(perPage: $request->per_page, page: $request->page);
    }

    /**
     * Filter packages to those having at least one release compatible with the given requirements.
     *
     * Compares dotted version strings as integer arrays, e.g. ?requires[typo3]=12.4 finds
     * packages with a release requiring typo3 <= 12.4. Multiple requirements are ANDed together.
     *
     * @param Builder<Package> $query
     */
    private function applyRequiresFilter(Builder $query, PackageSearchRequest $request): void
    {
        if (empty($request->requires)) {
            return;
        }

        $query->whereExists(function ($sub) use ($request) {
            $sub->select(DB::raw(1))
                ->from('package_releases')
                ->whereColumn('package_releases.package_id', 'packages.id');

            foreach ($request->requires as $key => $version) {
                $sub->whereRaw(
                    "package_releases.requires->>? IS NOT NULL AND string_to_array(package_releases.requires->>?, '.')::int[] <= string_to_array(?, '.')::int[]",
                    [$key, $key, $version],
                );
            }
        });
    }
}
