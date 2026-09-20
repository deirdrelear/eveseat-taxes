<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_taxes_price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('price_date');
            $table->unsignedBigInteger('type_id');
            $table->string('price_source', 32);
            $table->decimal('price', 30, 8);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['price_date', 'type_id', 'price_source'], 'seat_taxes_price_snapshot_unique');
        });

        Schema::create('seat_taxes_daily_facts', function (Blueprint $table) {
            $table->id();
            $table->date('tax_date');
            $table->string('source', 32);
            $table->string('source_key', 191);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('main_character_id')->nullable();
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('corporation_id')->nullable();
            $table->unsignedBigInteger('alliance_id')->nullable();
            $table->unsignedBigInteger('type_id')->nullable();
            $table->string('tax_class', 32);
            $table->decimal('quantity', 30, 4)->nullable();

            // Legacy ore-refine state. Stored per fact so the carry chain is
            // auditable and deterministic across daily calculations/rebuilds.
            $table->bigInteger('opening_remainder')->nullable();
            $table->bigInteger('refined_batches')->nullable();
            $table->bigInteger('closing_remainder')->nullable();

            // For ratting facts this snapshots the corporation tax rate used
            // to reconstruct the character's gross income.
            $table->decimal('corporation_tax_rate', 12, 6)->nullable();

            $table->decimal('gross_value', 30, 2)->default(0);
            $table->decimal('taxable_value', 30, 2)->default(0);
            $table->string('price_source', 32)->nullable();
            $table->decimal('price_value', 30, 8)->nullable();
            $table->decimal('refine_efficiency', 12, 6)->nullable();
            $table->json('sde_snapshot')->nullable();
            $table->json('source_payload')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_key'], 'seat_taxes_daily_fact_source_unique');
            $table->index(['tax_date', 'corporation_id']);
            $table->index(['tax_date', 'user_id']);
            $table->index(['tax_date', 'tax_class']);
            $table->index(['character_id', 'type_id', 'tax_date'], 'seat_taxes_refine_carry_lookup');
        });

        Schema::create('seat_taxes_daily_results', function (Blueprint $table) {
            $table->id();
            $table->date('tax_date');
            $table->foreignId('daily_fact_id')
                ->constrained('seat_taxes_daily_facts')
                ->cascadeOnDelete();
            $table->foreignId('rule_set_id')
                ->constrained('seat_taxes_rule_sets')
                ->restrictOnDelete();
            $table->decimal('tax_rate', 12, 6);
            $table->decimal('tax_amount', 30, 2);
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['daily_fact_id', 'rule_set_id'], 'seat_taxes_daily_result_unique');
            $table->index(['tax_date', 'rule_set_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_taxes_daily_results');
        Schema::dropIfExists('seat_taxes_daily_facts');
        Schema::dropIfExists('seat_taxes_price_snapshots');
    }
};
