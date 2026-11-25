<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content pages (Our story, FAQ, Contact…) written in the admin panel.
 * menu_group places a page in the About menu column "discover" or "help";
 * only published pages appear anywhere. template "contact" adds the form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->string('slug')->unique();
            $table->json('body')->nullable();
            $table->string('menu_group', 20)->nullable();   // discover, help
            $table->string('template', 20)->default('default'); // default, contact
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta_description')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'menu_group', 'sort_order']);
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->text('message');
            $table->string('locale', 5);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('pages');
    }
};
