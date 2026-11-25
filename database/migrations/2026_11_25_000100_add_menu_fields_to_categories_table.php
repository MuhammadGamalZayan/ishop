<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mega menu settings for top-level categories: whether the category is a
 * header item, its "Popular colours" (option value ids) and two picture
 * cards (image path, title, subtitle, link) shown on the right.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('show_in_menu')->default(true)->after('is_active');
            $table->json('menu_colour_value_ids')->nullable()->after('show_in_menu');
            $table->json('menu_cards')->nullable()->after('menu_colour_value_ids');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['show_in_menu', 'menu_colour_value_ids', 'menu_cards']);
        });
    }
};
