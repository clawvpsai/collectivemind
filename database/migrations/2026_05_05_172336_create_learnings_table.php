<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('body');
            $table->string('category', 50);
            $table->json('tags')->nullable();
            $table->unsignedInteger('verified_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index(['verified_count', 'failed_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learnings');
    }
};
