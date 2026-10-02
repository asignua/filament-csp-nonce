<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('csp-nonce.report.table', 'csp_violations'), function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('directive', 100);
            $table->string('blocked', 2048);
            $table->string('document', 2048);
            $table->string('source', 2048)->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->unsignedInteger('column')->nullable();
            $table->string('sample', 80)->nullable();
            $table->string('disposition', 20)->default('enforce');
            $table->string('user_agent', 250)->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('csp-nonce.report.table', 'csp_violations'));
    }
};
