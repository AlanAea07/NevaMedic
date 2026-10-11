<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->json('actor')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->index(['entity_type', 'entity_id', 'id']);
        });
        Schema::table('products', fn (Blueprint $table) => $table->index('deleted_at'));
        Schema::create('pending_image_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_image_deletions');
        Schema::table('products', fn (Blueprint $table) => $table->dropIndex(['deleted_at']));
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['entity_type', 'entity_id', 'id']);
            $table->dropColumn(['actor', 'before', 'after', 'metadata']);
        });
    }
};
