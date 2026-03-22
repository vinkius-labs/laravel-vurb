<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vurb_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('tool_name')->index();
            $table->string('session_id')->nullable()->index();
            $table->json('input')->nullable();
            $table->json('output_summary')->nullable();
            $table->boolean('is_error')->default(false);
            $table->string('error_code')->nullable();
            $table->float('latency_ms')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vurb_audit_log');
    }
};
