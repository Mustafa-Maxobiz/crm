<?php

namespace Webkul\Admin\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class ServiceDataGrid extends DataGrid
{
    protected string $permissionPrefix = 'settings.lead.services_offered';

    protected string $routePrefix = 'admin.settings.services_offered';

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
        $queryBuilder = DB::table('services')
            ->addSelect(
                'services.id',
                'services.name',
                'services.sort_order',
            )
            ->orderBy('services.sort_order')
            ->orderBy('services.id');

        $this->addFilter('id', 'services.id');
        $this->addFilter('name', 'services.name');

        return $queryBuilder;
    }

    /**
     * Prepare Columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.settings.services_offered.index.datagrid.id'),
            'type'       => 'string',
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('admin::app.settings.services_offered.index.datagrid.name'),
            'type'       => 'string',
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'sort_order',
            'label'      => trans('admin::app.settings.services_offered.index.datagrid.sort-order'),
            'type'       => 'string',
            'filterable' => false,
            'sortable'   => true,
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission($this->permissionPrefix.'.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.settings.services_offered.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route($this->routePrefix.'.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission($this->permissionPrefix.'.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.settings.services_offered.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route($this->routePrefix.'.delete', $row->id),
            ]);
        }
    }
}
