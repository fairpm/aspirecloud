<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Full-text search: stored generated tsvector column with GIN index
        DB::statement("
            ALTER TABLE packages
            ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('english', coalesce(name, '')), 'A') ||
                setweight(to_tsvector('english', coalesce(slug, '')), 'A') ||
                setweight(to_tsvector('english', coalesce(description, '')), 'B')
            ) STORED
        ");
        DB::statement('CREATE INDEX packages_search_vector_gin ON packages USING GIN (search_vector)');

        // Trigram indexes for fuzzy/partial matching
        DB::statement('CREATE INDEX packages_name_trgm ON packages USING GIST (name gist_trgm_ops(siglen=32))');
        DB::statement('CREATE INDEX packages_slug_trgm ON packages USING GIST (slug gist_trgm_ops(siglen=32))');

        // B-tree index on type for filtering
        DB::statement('CREATE INDEX packages_type_idx ON packages (type)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS packages_type_idx');
        DB::statement('DROP INDEX IF EXISTS packages_slug_trgm');
        DB::statement('DROP INDEX IF EXISTS packages_name_trgm');
        DB::statement('DROP INDEX IF EXISTS packages_search_vector_gin');
        DB::statement('ALTER TABLE packages DROP COLUMN IF EXISTS search_vector');
    }
};
