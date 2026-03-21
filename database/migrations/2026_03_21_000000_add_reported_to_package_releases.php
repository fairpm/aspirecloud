<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_releases', function (Blueprint $table) {
            $table->timestamp('reported')->nullable()->after('checksum');
        });
    }

    public function down(): void
    {
        Schema::table('package_releases', function (Blueprint $table) {
            $table->dropColumn('reported');
        });
    }
};
