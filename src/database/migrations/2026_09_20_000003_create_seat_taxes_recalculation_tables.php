<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_taxes_recalculation_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->foreignId('base_rule_set_id')
                ->constrained('seat_taxes_rule_sets')
                ->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('mode', 32)->default('rate_only');
            $table->string('status', 32)->default('pending');
            $table->json('overrides');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['from_date', 'to_date']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('seat_taxes_recalculation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')
                ->constrained('seat_taxes_recalculation_runs')
                ->cascadeOnDelete();
            $table->date('tax_date');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('character_id')->nullable();
            $table->unsignedBigInteger('corporation_id')->nullable();
            $table->string('tax_class', 32);
            $table->decimal('original_tax', 30, 2);
            $table->decimal('recalculated_tax', 30, 2);
            $table->decimal('delta', 30, 2);
            $table->timestamps();

            $table->index(['run_id', 'corporation_id']);
            $table->index(['run_id', 'user_id']);
            $table->index(['run_id', 'tax_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_taxes_recalculation_results');
        Schema::dropIfExists('seat_taxes_recalculation_runs');
    }
};
