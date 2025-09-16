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
        Schema::table('login_users', function (Blueprint $table) {
            $table->boolean('password_changed')->default(false)->after('password');
            $table->timestamp('password_changed_at')->nullable()->after('password_changed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('login_users', function (Blueprint $table) {
            $table->dropColumn(['password_changed', 'password_changed_at']);
        });
    }
};
