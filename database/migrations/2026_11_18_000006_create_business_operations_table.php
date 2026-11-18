<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('operation_type', 32); // sale | appointment | comanda | promotion
            $table->string('status', 32)->default('confirmed'); // pending | preparing | ready | confirmed | shipped | completed | cancelled
            $table->unsignedBigInteger('odoo_record_id')->nullable();
            $table->string('odoo_model', 64)->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 32)->nullable();
            $table->string('table_label', 32)->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->json('items')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('source', 32)->default('admin'); // whatsapp_audio | whatsapp_text | landing | admin
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'operation_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_operations');
    }
};
