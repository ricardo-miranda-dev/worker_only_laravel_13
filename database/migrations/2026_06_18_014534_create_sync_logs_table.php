<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('kommo_lead_id')->index();
            $table->string('q10_consecutivo')->nullable();
            $table->enum('status', ['pending', 'success', 'failed']);
            $table->json('kommo_payload')->nullable();
            $table->json('q10_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};