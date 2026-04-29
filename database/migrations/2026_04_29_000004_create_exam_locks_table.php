<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_locks')) {
            Schema::create('exam_locks', function (Blueprint $table) {
                $table->id();
                $table->string('fingerprint')->unique();
                $table->string('last_violation_type', 50);
                $table->string('locked_reason');
                $table->timestamp('locked_at');
                $table->longText('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_locks');
    }
};
