<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->index(['category_id', 'status', 'published_at', 'id'], 'idx_books_lab7_catalog');
            $table->index(['price', 'stock', 'id'], 'idx_books_lab7_price_stock');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('books', function (Blueprint $table) {
                $table->fullText(['title', 'description'], 'idx_books_lab7_fulltext');
            });
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('books', function (Blueprint $table) {
                $table->dropFullText('idx_books_lab7_fulltext');
            });
        }

        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex('idx_books_lab7_price_stock');
            $table->dropIndex('idx_books_lab7_catalog');
        });
    }
};
