<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('branch', 32); // estetica | tienda | comandas
            $table->string('owner_name');
            $table->string('owner_phone', 32);
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('primary_color', 20)->default('#0ea5e9');
            $table->string('secondary_color', 20)->default('#6366f1');
            $table->string('style_preset', 32)->default('moderno');
            $table->json('features')->nullable();
            $table->json('ai_memory_semantic')->nullable();
            $table->unsignedBigInteger('odoo_company_id')->nullable();
            $table->unsignedBigInteger('odoo_pos_config_id')->nullable();
            $table->unsignedBigInteger('odoo_warehouse_id')->nullable();
            $table->unsignedBigInteger('sc_project_id')->nullable();
            $table->string('wa_instance_name')->nullable();
            $table->boolean('wa_connected')->default(false);
            $table->string('gmaps_place_url')->nullable();
            $table->json('gmaps_kit')->nullable();
            $table->string('provisioning_status', 32)->default('pending');
            $table->json('provisioning_steps')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
