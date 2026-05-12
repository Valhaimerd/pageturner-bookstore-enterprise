<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('subscription_tier')->default('standard')->after('role');
            $table->index(['role', 'subscription_tier']);
        });

        Schema::table('books', function (Blueprint $table) {
            $table->index(['category_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index(['status', 'placed_at']);
            $table->index(['payment_status', 'created_at']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->index(['book_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::table(config('audit.drivers.database.table', 'audits'), function (Blueprint $table) {
            $table->string('method', 16)->nullable()->after('url');
            $table->string('checksum', 64)->nullable()->after('tags');
            $table->timestamp('archived_at')->nullable()->after('checksum');
            $table->index(['event', 'created_at']);
            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audits_auditable_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table(config('audit.drivers.database.table', 'audits'), function (Blueprint $table) {
            $table->dropIndex(['event', 'created_at']);
            $table->dropIndex('audits_auditable_created_idx');
            $table->dropColumn(['method', 'checksum', 'archived_at']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['book_id', 'created_at']);
            $table->dropIndex(['user_id', 'created_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['status', 'placed_at']);
            $table->dropIndex(['payment_status', 'created_at']);
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'status']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'subscription_tier']);
            $table->dropColumn('subscription_tier');
        });
    }
};
