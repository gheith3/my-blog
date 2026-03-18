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
        Schema::create('visitor_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('path')->nullable();
            $table->string('route_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamp('visited_at');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['post_id', 'visited_at']);
            $table->index(['url', 'visited_at']);
            $table->index(['session_id', 'visited_at']);
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_counts');
    }
};
