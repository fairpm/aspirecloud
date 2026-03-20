<?php
declare(strict_types=1);

namespace App\Services\Packages;

use App\Models\Package;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PackageSearchService
{
    public function search(string $type, ?string $query, int $page = 1, int $perPage = 24): LengthAwarePaginator
    {
        if ($query === null || $query === '') {
            return Package::where('type', $type)
                ->orderByDesc('created_at')
                ->paginate(perPage: $perPage, page: $page);
        }

        // Try full-text search first
        $results = $this->fullTextSearch($type, $query, $page, $perPage);

        if ($results->total() > 0) {
            return $results;
        }

        // Fall back to trigram similarity
        return $this->trigramSearch($type, $query, $page, $perPage);
    }

    private function fullTextSearch(string $type, string $query, int $page, int $perPage): LengthAwarePaginator
    {
        $tsQuery = "plainto_tsquery('english', ?)";

        return Package::where('type', $type)
            ->where(function ($q) use ($tsQuery, $query) {
                $q->whereRaw("search_vector @@ {$tsQuery}", [$query])
                    ->orWhereExists(function ($sub) use ($query) {
                        $sub->select(DB::raw(1))
                            ->from('package_package_tag')
                            ->join('package_tags', 'package_tags.id', '=', 'package_package_tag.package_tag_id')
                            ->whereColumn('package_package_tag.package_id', 'packages.id')
                            ->whereRaw("to_tsvector('english', package_tags.name) @@ plainto_tsquery('english', ?)", [$query]);
                    });
            })
            ->orderByRaw("ts_rank(search_vector, {$tsQuery}) DESC", [$query])
            ->paginate(perPage: $perPage, page: $page);
    }

    private function trigramSearch(string $type, string $query, int $page, int $perPage): LengthAwarePaginator
    {
        return Package::where('type', $type)
            ->whereRaw('(similarity(name, ?) > 0.1 OR similarity(slug, ?) > 0.1)', [$query, $query])
            ->orderByRaw('GREATEST(similarity(name, ?), similarity(slug, ?)) DESC', [$query, $query])
            ->paginate(perPage: $perPage, page: $page);
    }
}
