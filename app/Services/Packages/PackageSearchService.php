<?php
declare(strict_types=1);

namespace App\Services\Packages;

use App\Models\Package;
use App\Models\PackageRelease;
use App\Values\Packages\PackageSearchRequest;
use Composer\Semver\Semver;
use Illuminate\Pagination\LengthAwarePaginator;
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

            return $this->filterPaginatedReleases(
                $query->paginate(perPage: $request->per_page, page: $request->page),
                $request,
            );
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

        return $this->filterPaginatedReleases(
            $query->paginate(perPage: $request->per_page, page: $request->page),
            $request,
        );
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

        return $this->filterPaginatedReleases(
            $query->paginate(perPage: $request->per_page, page: $request->page),
            $request,
        );
    }

    /**
     * Filter packages to those having at least one release compatible with the given requirements.
     *
     * Uses Composer\Semver to evaluate version constraints stored in the requires JSONB field,
     * supporting ranges like ">=11.5.19 <=12.9.99", caret (^12.4), tilde (~12.4), etc.
     *
     * Optimization: instead of loading all releases into PHP, we first collect the distinct
     * constraint strings per key (typically only 20-50 unique values), run Semver::satisfies()
     * on those, then use SQL to find package IDs whose releases match the valid constraints.
     *
     * @param Builder<Package> $query
     */
    private function applyRequiresFilter(Builder $query, PackageSearchRequest $request): void
    {
        if (empty($request->requires)) {
            return;
        }

        // Build a subquery that finds package IDs with at least one release matching all constraints.
        // For each required key, we find the distinct constraint values, filter with Semver in PHP,
        // then add a SQL condition for only the valid constraints.
        $releaseQuery = DB::table('package_releases')
            ->select('package_releases.package_id')
            ->join('packages', 'packages.id', '=', 'package_releases.package_id')
            ->where('packages.type', $request->type);

        foreach ($request->requires as $key => $version) {
            // Get distinct constraint strings for this key (e.g. ">=11.5.0 <=12.99.99", "^12.4")
            $distinctConstraints = DB::table('package_releases')
                ->join('packages', 'packages.id', '=', 'package_releases.package_id')
                ->where('packages.type', $request->type)
                ->whereRaw('jsonb_exists(package_releases.requires, ?)', [$key])
                ->selectRaw('DISTINCT package_releases.requires->>? as constraint_value', [$key])
                ->pluck('constraint_value');

            // Filter to constraints that the provided version satisfies
            $validConstraints = $distinctConstraints
                ->filter(fn (string $constraint) => Semver::satisfies($version, $constraint))
                ->values()
                ->all();

            if (empty($validConstraints)) {
                // No valid constraints found — no packages can match
                $query->whereRaw('1 = 0');

                return;
            }

            $releaseQuery->whereRaw(
                'package_releases.requires->>? IN (' . implode(',', array_fill(0, count($validConstraints), '?')) . ')',
                [$key, ...$validConstraints],
            );
        }

        $matchingIds = $releaseQuery->distinct()->pluck('package_id')->all();

        $query->whereIn('packages.id', $matchingIds);
    }

    /**
     * Post-filter eager-loaded releases on paginated results using Composer\Semver.
     *
     * @param LengthAwarePaginator<int, Package> $results
     * @return LengthAwarePaginator<int, Package>
     */
    private function filterPaginatedReleases(LengthAwarePaginator $results, PackageSearchRequest $request): LengthAwarePaginator
    {
        if (empty($request->requires)) {
            return $results;
        }

        $results->getCollection()->each(function (Package $package) use ($request) {
            $package->setRelation(
                'releases',
                $package->releases
                    ->filter(fn (PackageRelease $release) => $this->releaseSatisfies($release, $request->requires))
                    ->values(),
            );
        });

        return $results;
    }

    /**
     * Check if a release satisfies all version requirements using Composer\Semver.
     *
     * @param array<string, string> $requires Key-value pairs of dependency name to user-provided version
     */
    private function releaseSatisfies(PackageRelease $release, array $requires): bool
    {
        foreach ($requires as $key => $version) {
            $constraint = $release->requires[$key] ?? null;
            if ($constraint === null) {
                return false;
            }

            if (! Semver::satisfies($version, $constraint)) {
                return false;
            }
        }

        return true;
    }
}
