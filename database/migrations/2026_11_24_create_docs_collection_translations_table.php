<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Translated collection names (and descriptions), so the sidebar's group
 * headings follow the reader's language like the page titles under them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docs_collection_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('doc_collection_id')->constrained('doc_collections')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['doc_collection_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docs_collection_translations');
    }
};
