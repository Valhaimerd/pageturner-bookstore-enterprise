<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mv_bestseller_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('total_books')->default(0);
            $table->decimal('avg_price', 10, 2)->default(0);
            $table->unsignedBigInteger('total_inventory')->default(0);
            $table->unsignedBigInteger('bestseller_count')->default(0);
            $table->date('latest_publication')->nullable();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();

            $table->unique('category_id');
            $table->index('refreshed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mv_bestseller_stats');
    }
};
