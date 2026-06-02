<?php

namespace Webkul\Admin\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class SourceDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('lead_sources')
            ->leftJoin('lead_source_parents', 'lead_sources.id', '=', 'lead_source_parents.parent_source_id')
            ->leftJoin('lead_sources as sub_sources', 'lead_source_parents.source_id', '=', 'sub_sources.id')
            ->select(
                'lead_sources.id',
                'lead_sources.name',
                'lead_sources.parent_id',
                DB::raw('GROUP_CONCAT(sub_sources.name SEPARATOR ", ") as sub_source_names')
            )
            ->groupBy('lead_sources.id', 'lead_sources.name', 'lead_sources.parent_id')
            ->orderBy('lead_sources.parent_id')
            ->orderBy('lead_sources.sort_order');

        // Only filter out sub-sources if 'all' parameter is not set
        if (!request()->has('all')) {
            $queryBuilder->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('lead_source_parents')
                      ->whereColumn('lead_source_parents.source_id', 'lead_sources.id');
            });
        }

        $this->addFilter('id', 'lead_sources.id');

        return $queryBuilder;
    }

    /**
     * Prepare Columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'    => 'id',
            'label'    => trans('admin::app.settings.sources.index.datagrid.id'),
            'type'     => 'string',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('admin::app.settings.sources.index.datagrid.name'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'sub_source_names',
            'label'      => trans('admin::app.settings.sources.index.datagrid.sub-sources'),
            'type'       => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => false,
            'closure'    => fn ($row) => $row->sub_source_names ?? '-',
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.lead.sources.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.settings.sources.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.sources.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.lead.sources.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.settings.sources.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.settings.sources.delete', $row->id),
            ]);
        }
    }
}
