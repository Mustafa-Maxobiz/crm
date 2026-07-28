<?php

namespace Webkul\Admin\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class LeadAttributeOptionDataGrid extends DataGrid
{
    protected int $attributeId = 0;

    protected string $permissionPrefix = '';

    protected string $routePrefix = '';

    public function setAttributeId(int $attributeId): self
    {
        $this->attributeId = $attributeId;

        return $this;
    }

    public function setPermissionPrefix(string $permissionPrefix): self
    {
        $this->permissionPrefix = $permissionPrefix;

        return $this;
    }

    public function setRoutePrefix(string $routePrefix): self
    {
        $this->routePrefix = $routePrefix;

        return $this;
    }

    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('attribute_options')
            ->where('attribute_options.attribute_id', $this->attributeId)
            ->addSelect(
                'attribute_options.id',
                'attribute_options.name',
                'attribute_options.sort_order',
            )
            ->orderBy('attribute_options.sort_order')
            ->orderBy('attribute_options.id');

        $this->addFilter('id', 'attribute_options.id');
        $this->addFilter('name', 'attribute_options.name');

        return $queryBuilder;
    }

    /**
     * Prepare Columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.settings.industries.index.datagrid.id'),
            'type'       => 'string',
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('admin::app.settings.industries.index.datagrid.name'),
            'type'       => 'string',
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'sort_order',
            'label'      => trans('admin::app.settings.industries.index.datagrid.sort-order'),
            'type'       => 'string',
            'filterable' => false,
            'sortable'   => true,
        ]);
    }

    /**
     * Prepare Actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission($this->permissionPrefix.'.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.settings.roles.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route($this->routePrefix.'.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission($this->permissionPrefix.'.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.settings.roles.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route($this->routePrefix.'.delete', $row->id),
            ]);
        }
    }
}
