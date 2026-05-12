<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (! Schema::hasColumn('books', 'publisher')) {
                $table->string('publisher')->nullable()->after('author');
            }

            if (! Schema::hasColumn('books', 'format')) {
                $table->string('format')->default('paperback')->after('publisher');
            }
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (Schema::hasColumn('books', 'format')) {
                $table->dropColumn('format');
            }

            if (Schema::hasColumn('books', 'publisher')) {
                $table->dropColumn('publisher');
            }
        });
    }
};
