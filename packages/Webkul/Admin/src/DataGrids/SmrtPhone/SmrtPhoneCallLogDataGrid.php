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
                'smrtphone_call_logs.created_at',
                'persons.name as person_name',
                'leads.title as lead_title',
            );

        $this->addFilter('id', 'smrtphone_call_logs.id');
        $this->addFilter('direction', 'smrtphone_call_logs.direction');
        $this->addFilter('from_number', 'smrtphone_call_logs.from_number');
        $this->addFilter('to_number', 'smrtphone_call_logs.to_number');
        $this->addFilter('contact_phone', 'smrtphone_call_logs.contact_phone');
        $this->addFilter('contact_name', 'smrtphone_call_logs.contact_name');
        $this->addFilter('user_name', 'smrtphone_call_logs.user_name');
        $this->addFilter('call_status', 'smrtphone_call_logs.call_status');
        $this->addFilter('call_outcome', 'smrtphone_call_logs.call_outcome');
        $this->addFilter('called_at', 'smrtphone_call_logs.called_at');
        $this->addFilter('is_dialer', 'smrtphone_call_logs.is_dialer');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.smrtphone.index.datagrid.id'),
            'type'       => 'integer',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'              => 'direction',
            'label'              => trans('admin::app.smrtphone.index.datagrid.direction'),
            'type'               => 'string',
            'searchable'         => false,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => trans('admin::app.smrtphone.directions.inbound'), 'value' => 'inbound'],
                ['label' => trans('admin::app.smrtphone.directions.outbound'), 'value' => 'outbound'],
                ['label' => trans('admin::app.smrtphone.directions.unknown'), 'value' => 'unknown'],
            ],
            'sortable'           => true,
            'closure'            => function ($row) {
                $direction = $row->direction ?: 'unknown';

                return trans('admin::app.smrtphone.directions.'.$direction);
            },
        ]);

        $this->addColumn([
            'index'      => 'contact_name',
            'label'      => trans('admin::app.smrtphone.index.datagrid.contact'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->contact_name ?: ($row->person_name ?: '—'),
        ]);

        $this->addColumn([
            'index'      => 'contact_phone',
            'label'      => trans('admin::app.smrtphone.index.datagrid.phone'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->contact_phone
                ?: ($row->direction === 'inbound' ? $row->from_number : $row->to_number)
                ?: '—',
        ]);

        $this->addColumn([
            'index'      => 'user_name',
            'label'      => trans('admin::app.smrtphone.index.datagrid.agent'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->user_name ?: '—',
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
            'index'      => 'call_outcome',
            'label'      => trans('admin::app.smrtphone.index.datagrid.outcome'),
            'type'       => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->call_outcome ?: '—',
        ]);

        $this->addColumn([
            'index'      => 'lead_title',
            'label'      => trans('admin::app.smrtphone.index.datagrid.lead'),
            'type'       => 'string',
            'searchable' => false,
            'filterable' => false,
            'sortable'   => false,
            'closure'    => function ($row) {
                if (! $row->lead_id) {
                    return '—';
                }

                return e($row->lead_title ?: '#'.$row->lead_id);
            },
        ]);

        $this->addColumn([
            'index'              => 'is_dialer',
            'label'              => trans('admin::app.smrtphone.index.datagrid.source'),
            'type'               => 'boolean',
            'searchable'         => false,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                ['label' => trans('admin::app.smrtphone.index.datagrid.dialer'), 'value' => 1],
                ['label' => trans('admin::app.smrtphone.index.datagrid.phone'), 'value' => 0],
            ],
            'sortable'           => true,
            'closure'            => fn ($row) => $row->is_dialer
                ? trans('admin::app.smrtphone.index.datagrid.dialer')
                : trans('admin::app.smrtphone.index.datagrid.phone'),
        ]);

        $this->addColumn([
            'index'      => 'called_at',
            'label'      => trans('admin::app.smrtphone.index.datagrid.called-at'),
            'type'       => 'date',
            'searchable' => false,
            'filterable' => true,
            'sortable'   => true,
            'closure'    => fn ($row) => $row->called_at
                ? \Carbon\Carbon::parse($row->called_at)->format('M d, Y H:i')
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

        if (bouncer()->hasPermission('smrtphone.view') && bouncer()->hasPermission('leads.view')) {
            $this->addAction([
                'index'  => 'view_lead',
                'icon'   => 'icon-leads',
                'title'  => trans('admin::app.smrtphone.index.datagrid.view-lead'),
                'method' => 'GET',
                'url'    => function ($row) {
                    if (! $row->lead_id) {
                        return null;
                    }

                    return route('admin.leads.view', $row->lead_id);
                },
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
