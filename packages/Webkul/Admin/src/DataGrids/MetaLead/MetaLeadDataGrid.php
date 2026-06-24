<?php

namespace Webkul\Admin\DataGrids\MetaLead;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;
use Webkul\MetaLead\Models\MetaLead;

class MetaLeadDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('meta_leads')
            ->leftJoin(
                DB::raw('(
                    SELECT meta_lead_user.meta_lead_id,
                           GROUP_CONCAT(users.name ORDER BY users.name SEPARATOR ", ") as assigned_users
                    FROM meta_lead_user
                    INNER JOIN users ON users.id = meta_lead_user.user_id
                    GROUP BY meta_lead_user.meta_lead_id
                ) as meta_lead_assignments'),
                'meta_lead_assignments.meta_lead_id',
                '=',
                'meta_leads.id'
            )
            ->addSelect(
                'meta_leads.id',
                'meta_leads.full_name',
                'meta_leads.phone',
                'meta_leads.email',
                'meta_leads.campaign_name',
                'meta_leads.form_name',
                'meta_leads.status',
                'meta_leads.is_duplicate',
                'meta_leads.lead_id',
                'meta_leads.received_at',
                'meta_leads.created_at',
                'meta_lead_assignments.assigned_users',
            );

        $user = auth()->guard('user')->user();

        if ($user && $user->role?->permission_type !== 'all') {
            $queryBuilder->whereExists(function ($query) use ($user) {
                $query->select(DB::raw(1))
                    ->from('meta_lead_user')
                    ->whereColumn('meta_lead_user.meta_lead_id', 'meta_leads.id')
                    ->where('meta_lead_user.user_id', $user->id);
            });
        }

        $this->addFilter('id', 'meta_leads.id');
        $this->addFilter('full_name', 'meta_leads.full_name');
        $this->addFilter('phone', 'meta_leads.phone');
        $this->addFilter('email', 'meta_leads.email');
        $this->addFilter('campaign_name', 'meta_leads.campaign_name');
        $this->addFilter('form_name', 'meta_leads.form_name');
        $this->addFilter('status', 'meta_leads.status');
        $this->addFilter('received_at', 'meta_leads.received_at');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.meta-leads.index.datagrid.id'),
            'type'       => 'integer',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'full_name',
            'label'      => trans('admin::app.meta-leads.index.datagrid.name'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => function ($row) {
                $name = $row->full_name ?: '—';

                if ($row->is_duplicate) {
                    return $name.' <span class="rounded bg-yellow-100 px-1.5 py-0.5 text-xs text-yellow-800">Duplicate</span>';
                }

                return $name;
            },
        ]);

        $this->addColumn([
            'index'      => 'phone',
            'label'      => trans('admin::app.meta-leads.index.datagrid.phone'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'email',
            'label'      => trans('admin::app.meta-leads.index.datagrid.email'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'campaign_name',
            'label'      => trans('admin::app.meta-leads.index.datagrid.campaign'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'form_name',
            'label'      => trans('admin::app.meta-leads.index.datagrid.form-name'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'assigned_users',
            'label'      => trans('admin::app.meta-leads.index.datagrid.assigned-users'),
            'type'       => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => false,
            'closure'    => fn ($row) => $row->assigned_users ?: '—',
        ]);

        $this->addColumn([
            'index'              => 'status',
            'label'              => trans('admin::app.meta-leads.index.datagrid.status'),
            'type'               => 'string',
            'searchable'         => false,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => collect(MetaLead::STATUSES)->map(fn ($status) => [
                'label' => trans('admin::app.meta-leads.statuses.'.$status),
                'value' => $status,
            ])->values()->all(),
            'sortable'           => true,
            'closure'            => fn ($row) => trans('admin::app.meta-leads.statuses.'.$row->status),
        ]);

        $this->addColumn([
            'index'      => 'received_at',
            'label'      => trans('admin::app.meta-leads.index.datagrid.received-date'),
            'type'       => 'date',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->received_at
                ? \Carbon\Carbon::parse($row->received_at)->format('M d, Y H:i')
                : '—',
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('meta_leads.view')) {
            $this->addAction([
                'index'  => 'view',
                'icon'   => 'icon-eye',
                'title'  => trans('admin::app.meta-leads.index.datagrid.view'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.meta_leads.view', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('meta_leads.view') && bouncer()->hasPermission('leads.view')) {
            $this->addAction([
                'index'  => 'view_lead',
                'icon'   => 'icon-leads',
                'title'  => trans('admin::app.meta-leads.index.datagrid.view-lead'),
                'method' => 'GET',
                'url'    => function ($row) {
                    if (! $row->lead_id) {
                        return null;
                    }

                    return route('admin.leads.view', $row->lead_id);
                },
            ]);
        }

        if (bouncer()->hasPermission('meta_leads.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.meta-leads.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.meta_leads.delete', $row->id),
            ]);
        }
    }

    public function prepareMassActions(): void
    {
        if (! bouncer()->hasPermission('meta_leads.edit')) {
            return;
        }

        $this->addMassAction([
            'title'   => trans('admin::app.meta-leads.index.datagrid.update-status'),
            'url'     => route('admin.meta_leads.mass_update'),
            'method'  => 'POST',
            'options' => collect(MetaLead::STATUSES)->map(fn ($status) => [
                'label' => trans('admin::app.meta-leads.statuses.'.$status),
                'value' => $status,
            ])->values()->all(),
        ]);

        if (bouncer()->hasPermission('meta_leads.delete')) {
            $this->addMassAction([
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.meta-leads.index.datagrid.mass-delete'),
                'method' => 'POST',
                'url'    => route('admin.meta_leads.mass_delete'),
            ]);
        }
    }
}
