<?php

namespace Webkul\Admin\DataGrids\Contact;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

class TeamDataGrid extends DataGrid
{
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('teams')
            ->leftJoin('users', 'teams.user_id', '=', 'users.id')
            ->leftJoin(
                DB::raw('(
                    SELECT organization_team.team_id,
                           GROUP_CONCAT(organizations.name ORDER BY organizations.name SEPARATOR ", ") AS organization_names
                    FROM organization_team
                    INNER JOIN organizations ON organizations.id = organization_team.organization_id
                    GROUP BY organization_team.team_id
                ) AS team_organization_names'),
                'team_organization_names.team_id',
                '=',
                'teams.id'
            )
            ->addSelect(
                'teams.id',
                'teams.name',
                'teams.description',
                'team_organization_names.organization_names',
                'users.name as owner_name',
                'teams.created_at'
            );

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $queryBuilder->where(function ($query) use ($userIds) {
                $query->whereIn('teams.user_id', $userIds)
                    ->orWhereExists(function ($query) use ($userIds) {
                        $query->select(DB::raw(1))
                            ->from('organization_team')
                            ->join('organizations', 'organizations.id', '=', 'organization_team.organization_id')
                            ->whereColumn('organization_team.team_id', 'teams.id')
                            ->whereIn('organizations.user_id', $userIds);
                    });
            });
        }

        $organizationIds = app(\Webkul\Lead\Services\SourceAccessService::class)->getEffectiveOrganizationIds();

        if ($organizationIds !== null) {
            if (empty($organizationIds)) {
                $queryBuilder->whereRaw('0 = 1');
            } else {
                $queryBuilder->whereExists(function ($query) use ($organizationIds) {
                    $query->select(DB::raw(1))
                        ->from('organization_team')
                        ->whereColumn('organization_team.team_id', 'teams.id')
                        ->whereIn('organization_team.organization_id', $organizationIds);
                });
            }
        }

        $this->addFilter('id', 'teams.id');
        $this->addFilter('name', 'teams.name');
        $this->addFilter('created_at', 'teams.created_at');

        return $queryBuilder;
    }

    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.contacts.teams.index.datagrid.id'),
            'type'       => 'integer',
            'filterable' => true,
            'sortable'   => true,
        ]);

        $this->addColumn([
            'index'      => 'name',
            'label'      => trans('admin::app.contacts.teams.index.datagrid.name'),
            'type'       => 'string',
            'sortable'   => true,
            'filterable' => true,
            'searchable' => true,
        ]);

        $this->addColumn([
            'index'      => 'organization_names',
            'label'      => trans('admin::app.contacts.teams.index.datagrid.company'),
            'type'       => 'string',
            'sortable'   => true,
            'filterable' => false,
            'searchable' => false,
            'closure'    => fn ($row) => $row->organization_names ?: '-',
        ]);

        $this->addColumn([
            'index'      => 'owner_name',
            'label'      => trans('admin::app.contacts.teams.index.datagrid.owner'),
            'type'       => 'string',
            'sortable'   => true,
            'filterable' => false,
            'searchable' => false,
            'closure'    => fn ($row) => $row->owner_name ?: '-',
        ]);

        $this->addColumn([
            'index'           => 'created_at',
            'label'           => trans('admin::app.contacts.teams.index.datagrid.created-at'),
            'type'            => 'date',
            'searchable'      => false,
            'filterable'      => true,
            'filterable_type' => 'date_range',
            'sortable'        => true,
            'closure'         => fn ($row) => core()->formatDate($row->created_at),
        ]);
    }

    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('contacts.teams.edit')) {
            $this->addAction([
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.contacts.teams.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.contacts.teams.edit', $row->id),
            ]);
        }

        if (bouncer()->hasPermission('contacts.teams.delete')) {
            $this->addAction([
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.contacts.teams.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => fn ($row) => route('admin.contacts.teams.delete', $row->id),
            ]);
        }
    }

    public function prepareMassActions(): void
    {
        if (bouncer()->hasPermission('contacts.teams.delete')) {
            $this->addMassAction([
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.contacts.teams.index.datagrid.delete'),
                'method' => 'PUT',
                'url'    => route('admin.contacts.teams.mass_delete'),
            ]);
        }
    }
}
