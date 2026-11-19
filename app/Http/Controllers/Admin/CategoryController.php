<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Category;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** The categories inside each sector: Construction > Road works. Companies tick them for their alerts. */
class CategoryController extends CrudController
{
    protected string $model = Category::class;

    protected string $route = 'admin.categories';

    protected string $layout = 'layouts.admin';

    protected string $title = 'Categories';

    protected string $singular = 'Category';

    protected string $icon = 'tags';

    protected array $with = ['industry'];

    protected function query(Request $request): Builder
    {
        return Category::query()->when($request->query('industry'), fn (Builder $q, $id) => $q->where('industry_id', $id));
    }

    protected function filters(Request $request): array
    {
        return [['name' => 'industry', 'label' => __('Sector'), 'options' => Industry::orderBy('sort')->orderBy('name')->pluck('name', 'id')->all(), 'value' => $request->query('industry')]];
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'industry_id', 'label' => 'Sector', 'type' => 'select', 'default' => request()->query('industry'), 'options' => Industry::orderBy('sort')->orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['required', 'exists:industries,id']],
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:100']],
            ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Category $c) => $c->name,
            'Sector' => fn (Category $c) => $c->industry?->name,
            'In use' => fn (Category $c) => $c->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        if (! $record) {
            $base = Str::slug($data['name']) ?: 'category';
            $slug = $base;
            for ($n = 2; Category::where('slug', $slug)->exists(); $n++) {
                $slug = $base.'-'.$n;
            }
            $data['slug'] = $slug;
        }

        return $data;
    }
}
