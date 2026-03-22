<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // GIN index on requires JSONB for fast key-existence checks (e.g. requires ? 'env:typo3')
        DB::statement('CREATE INDEX package_releases_requires_gin ON package_releases USING GIN (requires)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS package_releases_requires_gin');
    }
};
