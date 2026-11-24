<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A translated page gets its own summary and search-result text, so a reader
 * in another language sees no English in the lead paragraph, the browser tab
 * or a shared link. All nullable: a translation without them falls back to
 * its translated title and content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('docs_page_translations', function (Blueprint $table): void {
            $table->string('excerpt')->nullable()->after('title');
            $table->string('meta_title')->nullable()->after('excerpt');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
        });
    }

    public function down(): void
    {
        Schema::table('docs_page_translations', function (Blueprint $table): void {
            $table->dropColumn(['excerpt', 'meta_title', 'meta_description']);
        });
    }
};
