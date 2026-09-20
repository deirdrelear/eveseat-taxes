<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_taxes_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('refine_efficiency', 12, 6)->default(0.906300);
            $table->string('price_source')->default('eve_average');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['effective_from', 'effective_to']);
        });

        Schema::create('seat_taxes_rule_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_set_id')
                ->constrained('seat_taxes_rule_sets')
                ->cascadeOnDelete();
            $table->string('tax_class', 32);
            $table->decimal('rate', 12, 6);
            $table->timestamps();

            $table->unique(['rule_set_id', 'tax_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_taxes_rule_rates');
        Schema::dropIfExists('seat_taxes_rule_sets');
    }
};
