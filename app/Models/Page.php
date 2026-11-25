<?php

namespace App\Models;

use App\Models\Concerns\GeneratesUniqueSlug;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A content page written in the admin panel (Our story, FAQ, Contact…).
 * Shown at /{locale}/pages/{slug}; in the About menu when menu_group is set.
 *
 * @property int $id
 * @property string $slug
 * @property string|null $menu_group discover | help
 * @property string $template default | contact
 * @property bool $is_published
 */
#[Fillable(['title', 'slug', 'body', 'menu_group', 'template', 'is_published', 'sort_order', 'meta_description'])]
class Page extends Model
{
    use GeneratesUniqueSlug, HasTranslations;

    public const MENU_GROUPS = ['discover', 'help'];

    public const TEMPLATES = ['default', 'contact'];

    /** @var list<string> */
    public array $translatable = ['title', 'body', 'meta_description'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected function slugSource(): string
    {
        return 'title';
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @param Builder<self> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
