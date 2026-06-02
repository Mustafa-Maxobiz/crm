<?php

namespace Webkul\Admin\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class DeletedLeadDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('leads')
            ->addSelect(
                'leads.id',
                'leads.title',
                'leads.lead_value',
                'leads.status',
                'leads.deleted_at',
                'users.name as sales_person',
                'persons.name as person_name',
                'lead_pipeline_stages.name as stage'
            )
            ->leftJoin('users', 'leads.user_id', '=', 'users.id')
            ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
            ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNotNull('leads.deleted_at');

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $queryBuilder->whereIn('leads.user_id', $userIds);
        }

        $this->addFilter('id', 'leads.id');
        $this->addFilter('title', 'leads.title');
        $this->addFilter('sales_person', 'users.name');
        $this->addFilter('person_name', 'persons.name');
        $this->addFilter('status', 'leads.status');
        $this->addFilter('deleted_at', 'leads.deleted_at');

        return $queryBuilder;
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.settings.deleted-leads.index.datagrid.id'),
            'type'       => 'integer',
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'title',
            'label'      => trans('admin::app.settings.deleted-leads.index.datagrid.title'),
            'type'       => 'string',
            'filterable' => true,
            'searchable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'sales_person',
            'label'      => trans('admin::app.settings.deleted-leads.index.datagrid.sales-person'),
            'type'       => 'string',
            'filterable' => true,
            'searchable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->sales_person ?: '--',
        ]);

        $this->addColumn([
            'index'      => 'person_name',
            'label'      => trans('admin::app.settings.deleted-leads.index.datagrid.contact-person'),
            'type'       => 'string',
            'filterable' => true,
            'searchable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->person_name ?: '--',
        ]);

        $this->addColumn([
            'index'    => 'stage',
            'label'    => trans('admin::app.settings.deleted-leads.index.datagrid.stage'),
            'type'     => 'string',
            'sortable' => true,
            'closure'  => fn ($row) => $row->stage ?: '--',
        ]);

        $this->addColumn([
            'index'      => 'status',
            'label'      => trans('admin::app.settings.deleted-leads.index.datagrid.status'),
            'type'       => 'string',
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->status ?: '--',
        ]);

        $this->addColumn([
            'index'      => 'lead_value',
            'label'      => trans('admin::app.settings.deleted-leads.index.datagrid.lead-value'),
            'type'       => 'string',
            'sortable'   => true,
            'closure'    => fn ($row) => core()->formatBasePrice($row->lead_value, 2),
        ]);

        $this->addColumn([
            'index'           => 'deleted_at',
            'label'           => trans('admin::app.settings.deleted-leads.index.datagrid.deleted-at'),
            'type'            => 'date',
            'filterable'      => true,
            'filterable_type' => 'date_range',
            'sortable'        => true,
            'closure'         => fn ($row) => core()->formatDate($row->deleted_at),
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.lead.deleted_leads.restore')) {
            $this->addAction([
                'index'  => 'restore',
                'icon'   => 'icon-enter',
                'title'  => trans('admin::app.settings.deleted-leads.index.datagrid.restore'),
                'method' => 'POST',
                'url'    => fn ($row) => route('admin.settings.deleted_leads.restore', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.lead.deleted_leads.permanent_delete')) {
            $this->addAction([
                'index'  => 'permanent_delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.settings.deleted-leads.index.datagrid.permanent-delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.settings.deleted_leads.permanent_delete', $row->id),
            ]);
        }
    }
}
