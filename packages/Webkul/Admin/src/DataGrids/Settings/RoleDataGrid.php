<?php

namespace Webkul\Admin\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class RoleDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('roles')
            ->leftJoin(
                DB::raw('(
                    SELECT role_source.role_id,
                           GROUP_CONCAT(lead_sources.name ORDER BY lead_sources.name SEPARATOR ", ") AS assigned_sources
                    FROM role_source
                    INNER JOIN lead_sources ON lead_sources.id = role_source.lead_source_id
                    GROUP BY role_source.role_id
                ) AS role_source_names'),
                'role_source_names.role_id',
                '=',
                'roles.id'
            )
            ->leftJoin(
                DB::raw('(
                    SELECT role_organization.role_id,
                           GROUP_CONCAT(organizations.name ORDER BY organizations.name SEPARATOR ", ") AS assigned_organizations
                    FROM role_organization
                    INNER JOIN organizations ON organizations.id = role_organization.organization_id
                    GROUP BY role_organization.role_id
                ) AS role_organization_names'),
                'role_organization_names.role_id',
                '=',
                'roles.id'
            )
            ->leftJoin(
                DB::raw('(
                    SELECT role_pipeline_stage.role_id,
                           GROUP_CONCAT(
                               CONCAT(
                                   lead_pipeline_stages.name,
                                   IF(role_pipeline_stage.is_shared, " (Shared)", "")
                               )
                               ORDER BY lead_pipeline_stages.sort_order SEPARATOR ", "
                           ) AS assigned_stages
                    FROM role_pipeline_stage
                    INNER JOIN lead_pipeline_stages ON lead_pipeline_stages.id = role_pipeline_stage.lead_pipeline_stage_id
                    GROUP BY role_pipeline_stage.role_id
                ) AS role_stage_names'),
                'role_stage_names.role_id',
                '=',
                'roles.id'
            )
            ->addSelect(
                'roles.id',
                'roles.name',
                'roles.description',
                'roles.permission_type',
                'role_source_names.assigned_sources',
                'role_organization_names.assigned_organizations',
                'role_stage_names.assigned_stages',
            );

        $this->addFilter('id', 'roles.id');
        $this->addFilter('name', 'roles.name');

        return $queryBuilder;
    }

    /**
     * Prepare Columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.settings.roles.index.datagrid.id'),
            'type'       => 'string',
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('admin::app.settings.roles.index.datagrid.name'),
            'type'       => 'string',
            'filterable' => true,
            'sortable'   => true,
            'searchable' => true,
        ]);

        $this->addColumn([
            'index'      => 'description',
            'label'      => trans('admin::app.settings.roles.index.datagrid.description'),
            'type'       => 'string',
            'sortable'   => false,
            'searchable' => true,
        ]);

        $this->addColumn([
            'index'      => 'assigned_sources',
            'label'      => trans('admin::app.settings.roles.index.datagrid.assigned-sources'),
            'type'       => 'string',
            'sortable'   => false,
            'searchable' => false,
            'filterable' => false,
            'closure'    => fn ($row) => $row->assigned_sources ?: trans('admin::app.settings.roles.index.datagrid.all'),
        ]);

        $this->addColumn([
            'index'      => 'assigned_organizations',
            'label'      => trans('admin::app.settings.roles.index.datagrid.assigned-companies'),
            'type'       => 'string',
            'sortable'   => false,
            'searchable' => false,
            'filterable' => false,
            'closure'    => fn ($row) => $row->assigned_organizations ?: trans('admin::app.settings.roles.index.datagrid.all'),
        ]);

        $this->addColumn([
            'index'      => 'assigned_stages',
            'label'      => trans('admin::app.settings.roles.index.datagrid.assigned-stages'),
            'type'       => 'string',
            'sortable'   => false,
            'searchable' => false,
            'filterable' => false,
            'closure'    => fn ($row) => $row->assigned_stages ?: trans('admin::app.settings.roles.index.datagrid.all'),
        ]);

        $this->addColumn([
            'index'              => 'permission_type',
            'label'              => trans('admin::app.settings.roles.index.datagrid.permission-type'),
            'type'               => 'string',
            'searchable'         => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                [
                    'label' => trans('admin::app.settings.roles.index.datagrid.custom'),
                    'value' => 'custom',
                ],
                [
                    'label' => trans('admin::app.settings.roles.index.datagrid.all'),
                    'value' => 'all',
                ],
            ],
            'sortable'   => true,
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.user.roles.edit')) {
            $this->addAction([
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.settings.roles.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.roles.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.user.roles.delete')) {
            $this->addAction([
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.settings.roles.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.settings.roles.delete', $row->id),
            ]);
        }
    }
}
