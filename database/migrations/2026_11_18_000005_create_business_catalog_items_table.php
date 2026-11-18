<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('odoo_product_id')->nullable();
            $table->string('name');
            $table->string('type', 32)->default('service'); // service | consu | product
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('duration_minutes')->nullable();
            $table->integer('stock_qty')->default(0);
            $table->string('category', 64)->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_catalog_items');
    }
};
