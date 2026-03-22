<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vurb_fsm_states', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->string('fsm_id');
            $table->string('current_state');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'fsm_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vurb_fsm_states');
    }
};
