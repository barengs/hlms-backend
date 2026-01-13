<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('discussions')->nullOnDelete();
            
            // Discussion info
            $table->string('title')->nullable();
            $table->text('content');
            
            // Status
            $table->enum('type', ['question', 'discussion', 'announcement'])->default('discussion');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_approved')->default(true);
            $table->boolean('is_locked')->default(false);
            
            // Stats
            $table->integer('replies_count')->default(0);
            $table->integer('views_count')->default(0);
            $table->integer('upvotes_count')->default(0);
            
            $table->timestamps();

            $table->index(['batch_id', 'created_at']);
            $table->index(['lesson_id', 'created_at']);
            $table->index(['parent_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discussions');
    }
};
