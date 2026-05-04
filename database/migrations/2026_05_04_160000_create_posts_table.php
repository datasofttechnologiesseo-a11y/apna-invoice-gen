<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public blog — drives SEO traffic to the landing page. Authored from the
 * super-admin panel, displayed at /blog (index) and /blog/{slug} (post).
 *
 * SEO-first design:
 *  - meta_title / meta_description / meta_keywords are explicit so the
 *    operator can fine-tune each post separately from the visible heading.
 *  - og_image_path is a dedicated column so the social-card image can be
 *    sized for sharing (1200x630) without affecting the in-article cover.
 *  - slug is unique and validated server-side, lowercase-slug-with-dashes.
 *  - published_at allows future-date scheduling without a cron — the public
 *    query just filters `published_at <= now()` on every page load.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()
                  ->comment('Author — only super-admins can post; cascade so removing a user removes their drafts.');
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('excerpt', 500)->nullable()
                  ->comment('Short summary shown on the index. Auto-derived from body if blank.');
            $table->longText('body')
                  ->comment('Markdown source. Rendered to HTML on display via league/commonmark.');
            $table->string('featured_image_path')->nullable();
            $table->string('featured_image_alt', 200)->nullable();

            // SEO overrides — independent from the visible title/excerpt
            // so the operator can craft the search-snippet separately.
            $table->string('meta_title', 70)->nullable()
                  ->comment('Override <title>. Falls back to title. Google truncates ~60 chars.');
            $table->string('meta_description', 200)->nullable()
                  ->comment('<meta name="description">. Ideal 150-160 chars.');
            $table->string('meta_keywords', 255)->nullable()
                  ->comment('Comma-separated. Most engines ignore but Bing/Yandex still use.');
            $table->string('og_image_path')->nullable()
                  ->comment('Override social-card image. Falls back to featured_image_path.');

            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable()
                  ->comment('When the post becomes publicly visible. Nullable for drafts. Future dates = scheduled.');
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedSmallInteger('reading_minutes')->nullable()
                  ->comment('Word count / 200, computed on save.');

            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
