<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learnings', function (Blueprint $table) {
            $table->string('source', 50)->nullable()->after('tags');
            $table->string('source_id', 50)->nullable()->after('source');
            $table->integer('source_score')->default(0)->after('source_id');
            $table->string('source_url')->nullable()->after('source_score');
        });
    }

    public function down(): void
    {
        Schema::table('learnings', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_id', 'source_score', 'source_url']);
        });
    }
};
