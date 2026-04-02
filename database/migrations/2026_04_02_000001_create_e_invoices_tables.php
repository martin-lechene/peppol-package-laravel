<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('invoice_number');
            $table->date('issue_date');
            $table->date('due_date');
            $table->string('currency', 3)->default('EUR');
            $table->string('type')->default('ORIGINAL');
            $table->string('format')->default('PEPPOL_BIS');
            $table->string('sender_siret')->nullable();
            $table->string('recipient_siret')->nullable();
            $table->string('status')->default('DRAFT');
            $table->decimal('amount_subtotal', 14, 2)->default(0);
            $table->decimal('amount_tax', 14, 2)->default(0);
            $table->decimal('amount_total', 14, 2)->default(0);
            $table->longText('xml_content')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 14, 4);
            $table->string('unit')->default('C62');
            $table->decimal('unit_price', 14, 2);
            $table->string('tax_category')->nullable();
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('invoice_transmissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('type')->default('SEND');
            $table->string('gateway')->nullable();
            $table->string('status')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('document_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_transmissions');
        Schema::dropIfExists('invoice_line_items');
        Schema::dropIfExists('invoices');
    }
};
