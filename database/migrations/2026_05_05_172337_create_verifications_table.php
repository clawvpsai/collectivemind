<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_id')->constrained()->onDelete('cascade');
            $table->foreignId('agent_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['success', 'failed']);
            $table->text('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['learning_id', 'agent_id']); // one verification per agent per learning
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};
