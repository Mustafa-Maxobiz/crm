<?php

namespace Webkul\Admin\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Webkul\DataGrid\DataGrid;

class UserDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('users')
            ->leftJoin(
                DB::raw('(
                    SELECT user_source.user_id,
                           GROUP_CONCAT(lead_sources.name ORDER BY lead_sources.name SEPARATOR ", ") AS assigned_sources
                    FROM user_source
                    INNER JOIN lead_sources ON lead_sources.id = user_source.lead_source_id
                    GROUP BY user_source.user_id
                ) AS user_source_names'),
                'user_source_names.user_id',
                '=',
                'users.id'
            )
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
                'users.role_id'
            )
            ->leftJoin(
                DB::raw('(
                    SELECT user_organization.user_id,
                           GROUP_CONCAT(organizations.name ORDER BY organizations.name SEPARATOR ", ") AS assigned_organizations
                    FROM user_organization
                    INNER JOIN organizations ON organizations.id = user_organization.organization_id
                    GROUP BY user_organization.user_id
                ) AS user_organization_names'),
                'user_organization_names.user_id',
                '=',
                'users.id'
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
                'users.role_id'
            )
            ->distinct()
            ->addSelect(
                'users.id',
                'users.name',
                'users.email',
                'users.image',
                'users.status',
                'users.created_at',
                DB::raw('CASE
                    WHEN user_source_names.assigned_sources IS NOT NULL THEN user_source_names.assigned_sources
                    ELSE role_source_names.assigned_sources
                END AS assigned_sources'),
                DB::raw('CASE
                    WHEN user_source_names.assigned_sources IS NOT NULL THEN 0
                    ELSE 1
                END AS inherits_role_sources'),
                DB::raw('CASE
                    WHEN user_organization_names.assigned_organizations IS NOT NULL THEN user_organization_names.assigned_organizations
                    ELSE role_organization_names.assigned_organizations
                END AS assigned_organizations'),
                DB::raw('CASE
                    WHEN user_organization_names.assigned_organizations IS NOT NULL THEN 0
                    ELSE 1
                END AS inherits_role_organizations'),
            )
            ->leftJoin('user_groups', 'users.id', '=', 'user_groups.user_id');

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $queryBuilder->whereIn('users.id', $userIds);
        }

        $this->addFilter('id', 'users.id');
        $this->addFilter('name', 'users.name');
        $this->addFilter('email', 'users.email');

        return $queryBuilder;
    }

    /**
     * Add columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'    => 'id',
            'label'    => trans('admin::app.settings.users.index.datagrid.id'),
            'type'     => 'string',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('admin::app.settings.users.index.datagrid.name'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
            'closure'    => function ($row) {
                return [
                    'image' => $row->image ? Storage::url($row->image) : null,
                    'name'  => $row->name,
                ];
            },
        ]);

        $this->addColumn([
            'index'      => 'email',
            'label'      => trans('admin::app.settings.users.index.datagrid.email'),
            'type'       => 'string',
            'sortable'   => true,
            'searchable' => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'assigned_sources',
            'label'      => trans('admin::app.settings.users.index.datagrid.assigned-sources'),
            'type'       => 'string',
            'sortable'   => false,
            'searchable' => false,
            'filterable' => false,
            'closure'    => function ($row) {
                if (! $row->assigned_sources) {
                    return '—';
                }

                $label = e($row->assigned_sources);

                if ($row->inherits_role_sources) {
                    return $label.' <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 dark:bg-slate-900 dark:text-slate-300">'
                        .trans('admin::app.settings.users.index.datagrid.inherited').'</span>';
                }

                return $label.' <span class="rounded bg-blue-100 px-1.5 py-0.5 text-xs text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">'
                    .trans('admin::app.settings.users.index.datagrid.custom').'</span>';
            },
        ]);

        $this->addColumn([
            'index'      => 'assigned_organizations',
            'label'      => trans('admin::app.settings.users.index.datagrid.assigned-companies'),
            'type'       => 'string',
            'sortable'   => false,
            'searchable' => false,
            'filterable' => false,
            'closure'    => function ($row) {
                if (! $row->assigned_organizations) {
                    return '—';
                }

                $label = e($row->assigned_organizations);

                if ($row->inherits_role_organizations) {
                    return $label.' <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 dark:bg-slate-900 dark:text-slate-300">'
                        .trans('admin::app.settings.users.index.datagrid.inherited').'</span>';
                }

                return $label.' <span class="rounded bg-blue-100 px-1.5 py-0.5 text-xs text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">'
                    .trans('admin::app.settings.users.index.datagrid.custom').'</span>';
            },
        ]);

        $this->addColumn([
            'index'      => 'status',
            'label'      => trans('admin::app.settings.users.index.datagrid.status'),
            'type'       => 'boolean',
            'filterable' => true,
            'sortable'   => true,
            'searchable' => true,
        ]);

        $this->addColumn([
            'index'           => 'created_at',
            'label'           => trans('admin::app.settings.users.index.datagrid.created-at'),
            'type'            => 'date',
            'sortable'        => true,
            'searchable'      => true,
            'filterable_type' => 'date_range',
            'filterable'      => true,
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.user.users.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.settings.users.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.users.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.user.users.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.settings.users.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.settings.users.delete', $row->id),
            ]);
        }
    }

    /**
     * Prepare mass actions.
     */
    public function prepareMassActions(): void
    {
        $this->addMassAction([
            'icon'   => 'icon-delete',
            'title'  => trans('admin::app.settings.users.index.datagrid.delete'),
            'method' => 'POST',
            'url'    => route('admin.settings.users.mass_delete'),
        ]);

        $this->addMassAction([
            'title'   => trans('admin::app.settings.users.index.datagrid.update-status'),
            'method'  => 'POST',
            'url'     => route('admin.settings.users.mass_update'),
            'options' => [
                [
                    'label' => trans('admin::app.settings.users.index.datagrid.active'),
                    'value' => 1,
                ],
                [
                    'label' => trans('admin::app.settings.users.index.datagrid.inactive'),
                    'value' => 0,
                ],
            ],
        ]);
    }
}
