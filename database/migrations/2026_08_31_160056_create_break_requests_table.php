<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('break_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_request_id')->constrained()->onDelete('cascade');
            $table->datetime('break_in');
            $table->datetime('break_out');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('break_requests');
    }
};
