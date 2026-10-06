<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('role', 20)->nullable()->index());
        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);
        DB::table('users')->where('is_admin', false)->update(['role' => 'publisher']);
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn('sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->string('sku', 50)->nullable()->unique());
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
