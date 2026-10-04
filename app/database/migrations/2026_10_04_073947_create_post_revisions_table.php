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
        Schema::create('post_revisions', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->nullable();
            $table->unsignedInteger('base_version')->nullable();
            $table->string('state');
            $table->string('source');
            $table->string('client_name')->nullable();
            $table->string('note')->nullable();
            $table->boolean('unguarded')->default(false);
            $table->string('title');
            $table->longText('content_html');
            $table->string('excerpt', 300)->nullable();
            $table->string('slug');
            $table->string('status');
            $table->json('edits')->nullable();
            $table->timestamps();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index('post_id');
            $table->index(['post_id', 'state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_revisions');
    }
};
