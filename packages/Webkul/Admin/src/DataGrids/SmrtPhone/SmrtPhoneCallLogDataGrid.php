<?php

namespace Webkul\Admin\DataGrids\SmrtPhone;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class SmrtPhoneCallLogDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('smrtphone_call_logs')
            ->leftJoin('persons', 'smrtphone_call_logs.person_id', '=', 'persons.id')
            ->leftJoin('leads', 'smrtphone_call_logs.lead_id', '=', 'leads.id')
            ->addSelect(
                'smrtphone_call_logs.id',
                'smrtphone_call_logs.external_call_id',
                'smrtphone_call_logs.direction',
                'smrtphone_call_logs.from_number',
                'smrtphone_call_logs.to_number',
                'smrtphone_call_logs.contact_phone',
                'smrtphone_call_logs.contact_name',
                'smrtphone_call_logs.user_name',
                'smrtphone_call_logs.call_status',
                'smrtphone_call_logs.call_outcome',
                'smrtphone_call_logs.recording_url',
                'smrtphone_call_logs.is_dialer',
                'smrtphone_call_logs.person_id',
                'smrtphone_call_logs.lead_id',
                'smrtphone_call_logs.called_at',
                'smrtphone_call_logs.started_at',
                'smrtphone_call_logs.completed_at',
                'smrtphone_call_logs.duration_seconds',
                'smrtphone_call_logs.created_at',
                'persons.name as person_name',
                'leads.title as lead_title',
            );

        $this->addFilter('from_number', 'smrtphone_call_logs.from_number');
        $this->addFilter('to_number', 'smrtphone_call_logs.to_number');
        $this->addFilter('call_status', 'smrtphone_call_logs.call_status');
        $this->addFilter('started_at', 'smrtphone_call_logs.started_at');
        $this->addFilter('completed_at', 'smrtphone_call_logs.completed_at');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'from_number',
            'label'      => trans('admin::app.smrtphone.index.datagrid.from'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->from_number ?: '—',
        ]);

        $this->addColumn([
            'index'      => 'to_number',
            'label'      => trans('admin::app.smrtphone.index.datagrid.to'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->to_number ?: '—',
        ]);

        $this->addColumn([
            'index'      => 'lead_title',
            'label'      => trans('admin::app.smrtphone.index.datagrid.lead-name'),
            'type'       => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => false,
            'closure'    => function ($row) {
                if (! $row->lead_id) {
                    return '—';
                }

                $label = e($row->lead_title ?: '#'.$row->lead_id);
                $url = e(lead_route_for_user('view', $row->lead_id));

                return '<a href="'.$url.'" class="font-medium text-brandColor hover:underline">'.$label.'</a>';
            },
        ]);

        $this->addColumn([
            'index'      => 'call_status',
            'label'      => trans('admin::app.smrtphone.index.datagrid.status'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->call_status ?: '—',
        ]);

        $this->addColumn([
            'index'      => 'started_at',
            'label'      => trans('admin::app.smrtphone.view.started-at'),
            'type'       => 'date',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->started_at
                ? \Carbon\Carbon::parse($row->started_at)->format('M d, Y H:i')
                : '—',
        ]);

        $this->addColumn([
            'index'      => 'completed_at',
            'label'      => trans('admin::app.smrtphone.view.completed-at'),
            'type'       => 'date',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->completed_at
                ? \Carbon\Carbon::parse($row->completed_at)->format('M d, Y H:i')
                : '—',
        ]);

        $this->addColumn([
            'index'      => 'duration_seconds',
            'label'      => trans('admin::app.smrtphone.view.duration'),
            'type'       => 'integer',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->duration_seconds !== null
                ? gmdate('H:i:s', (int) $row->duration_seconds)
                : '—',
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('smrtphone.view')) {
            $this->addAction([
                'index'  => 'view',
                'icon'   => 'icon-eye',
                'title'  => trans('admin::app.smrtphone.index.datagrid.view'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.smrtphone.view', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('smrtphone.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.smrtphone.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.smrtphone.delete', $row->id),
            ]);
        }
    }

    public function prepareMassActions(): void
    {
        if (bouncer()->hasPermission('smrtphone.delete')) {
            $this->addMassAction([
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.smrtphone.index.datagrid.mass-delete'),
                'method' => 'POST',
                'url'    => route('admin.smrtphone.mass_delete'),
            ]);
        }
    }
}
