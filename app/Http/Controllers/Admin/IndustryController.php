<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\Industry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** The sectors tenders and companies are sorted by. Each one gets its own page on the public site. */
class IndustryController extends CrudController
{
    /** Icons a sector can carry on the website (names of Font Awesome icons). */
    public const ICONS = [
        'helmet-safety' => 'Construction', 'kit-medical' => 'Health', 'laptop' => 'ICT', 'seedling' => 'Agriculture',
        'utensils' => 'Food', 'truck' => 'Transport', 'bolt' => 'Energy', 'comments' => 'Consultancy', 'graduation-cap' => 'Education',
        'droplet' => 'Water', 'shirt' => 'Textile', 'print' => 'Printing', 'shield' => 'Security', 'screwdriver-wrench' => 'Maintenance',
        'boxes-stacked' => 'Supplies', 'car' => 'Vehicles', 'building' => 'Buildings', 'box' => 'Other',
    ];

    protected string $model = Industry::class;

    protected string $route = 'admin.industries';

    protected string $layout = 'layouts.admin';

    protected string $title = 'Sectors';

    protected string $singular = 'Sector';

    protected string $icon = 'layer-group';

    protected string $orderBy = 'sort';

    protected function query(Request $request): Builder
    {
        return Industry::query()->withCount('categories', 'tenders');
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:100']],
            ['name' => 'icon', 'label' => 'Icon', 'type' => 'select', 'default' => 'box', 'options' => self::ICONS, 'rules' => ['required', 'string', 'max:40']],
            ['name' => 'sort', 'label' => 'Order in lists', 'type' => 'number', 'default' => 0, 'rules' => ['required', 'integer', 'min:0', 'max:9999']],
            ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Industry $i) => $i->name,
            'Categories' => fn (Industry $i) => $i->categories_count,
            'Tenders' => fn (Industry $i) => $i->tenders_count,
            'Order in lists' => fn (Industry $i) => $i->sort,
            'In use' => fn (Industry $i) => $i->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        // the address of the sector's page is made once and then kept, so links never break
        if (! $record) {
            $base = Str::slug($data['name']) ?: 'sector';
            $slug = $base;
            for ($n = 2; Industry::where('slug', $slug)->exists(); $n++) {
                $slug = $base.'-'.$n;
            }
            $data['slug'] = $slug;
        }

        return $data;
    }

    protected function rowLinks(Model $record): array
    {
        return [['label' => __('Categories'), 'icon' => 'tags', 'url' => route('admin.categories.index', ['industry' => $record->id])]];
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->tenders()->exists() ? __('Tenders are in this sector. Switch it off instead.') : null;
    }
}
