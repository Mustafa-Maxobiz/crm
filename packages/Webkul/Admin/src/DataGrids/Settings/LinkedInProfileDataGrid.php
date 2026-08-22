<?php

namespace Webkul\Admin\DataGrids\Settings;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class LinkedInProfileDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('linkedin_profiles')
            ->select(
                'linkedin_profiles.id',
                'linkedin_profiles.name',
                'linkedin_profiles.profile_url',
                'linkedin_profiles.is_active',
                'linkedin_profiles.created_at',
                DB::raw('(
                    SELECT GROUP_CONCAT(users.name ORDER BY users.name SEPARATOR ", ")
                    FROM linkedin_profile_user
                    INNER JOIN users ON users.id = linkedin_profile_user.user_id
                    WHERE linkedin_profile_user.linkedin_profile_id = linkedin_profiles.id
                ) as assigned_users'),
            );

        $this->addFilter('id', 'linkedin_profiles.id');
        $this->addFilter('name', 'linkedin_profiles.name');
        $this->addFilter('is_active', 'linkedin_profiles.is_active');
        $this->addFilter('created_at', 'linkedin_profiles.created_at');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => 'ID',
            'type'       => 'integer',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => 'Profile Name',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
        ]);

        $this->addColumn([
            'index'      => 'profile_url',
            'label'      => 'Profile URL',
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => false,
            'filterable' => false,
            'closure'    => fn ($row) => '<a href="'.e($row->profile_url).'" target="_blank" rel="noopener noreferrer" class="text-brandColor">'.e($row->profile_url).'</a>',
        ]);

        $this->addColumn([
            'index'      => 'assigned_users',
            'label'      => 'Assigned Users',
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => false,
            'filterable' => false,
            'closure'    => fn ($row) => e($row->assigned_users ?: '--'),
        ]);

        $this->addColumn([
            'index'      => 'is_active',
            'label'      => 'Status',
            'type'       => 'boolean',
            'searchable' => false,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => fn ($row) => $row->is_active
                ? '<span class="label-active">Active</span>'
                : '<span class="label-canceled">Inactive</span>',
        ]);

        $this->addColumn([
            'index'      => 'created_at',
            'label'      => 'Created At',
            'type'       => 'date',
            'searchable' => false,
            'sortable'   => true,
            'filterable' => true,
            'filterable_type' => 'date_range',
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('settings.other_settings.linkedin_profiles.edit')) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => 'Edit',
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.settings.linkedin_profiles.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('settings.other_settings.linkedin_profiles.delete')) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => 'Delete',
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.settings.linkedin_profiles.delete', $row->id),
            ]);
        }
    }
}
