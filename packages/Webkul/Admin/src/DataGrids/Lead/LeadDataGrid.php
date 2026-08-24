<?php

namespace Webkul\Admin\DataGrids\Lead;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\StageRepository;
use Webkul\Lead\Repositories\TypeRepository;
use Webkul\Tag\Repositories\TagRepository;

class LeadDataGrid extends DataGrid
{
    /**
     * Pipeline instance.
     *
     * @var \Webkul\Contract\Repositories\Pipeline
     */
    protected $pipeline;

    /**
     * Default sort column.
     *
     * @var string
     */
    protected $sortColumn = 'leads.created_at';

    /**
     * Default sort order.
     *
     * @var string
     */
    protected $sortOrder = 'desc';

    /**
     * Create data grid instance.
     *
     * @return void
     */
    public function __construct(
        protected PipelineRepository $pipelineRepository,
        protected StageRepository $stageRepository,
        protected SourceRepository $sourceRepository,
        protected TypeRepository $typeRepository,
        protected TagRepository $tagRepository,
    ) {
        if (request('pipeline_id')) {
            $this->pipeline = $this->pipelineRepository->find(request('pipeline_id'));
        } else {
            $this->pipeline = $this->pipelineRepository->getDefaultPipeline();
        }
    }

    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $industryAttributeId = DB::table('attributes')
            ->where('code', 'industry')
            ->where('entity_type', 'leads')
            ->value('id');

        $queryBuilder = DB::table('leads')
            ->addSelect(
                'leads.id',
                'leads.title',
                'leads.organization_id',
                'organizations.name as company_name',
                'leads.description',
                'leads.source_link',
                'leads.linkedin_profile_id',
                'linkedin_profiles.name as linkedin_profile_name',
                'leads.status',
                'leads.lead_value',
                'leads.next_followup_date',
                'leads.followup_count',
                'leads.last_followup_date',
                'leads.lead_disqualification_reason',
                'leads.lead_disqualified_at',
                'lead_sources.name as lead_source_name',
                'lead_types.name as lead_type_name',
                'leads.created_at',
                'leads.lead_source_id',
                'leads.lead_type_id',
                'leads.lead_pipeline_stage_id',
                'leads.lead_owner_id',
                'lead_pipeline_stages.name as stage',
                'lead_pipeline_stages.code as stage_code',
                'lead_pipeline_stages.sort_order as stage_sort_order',
                'lead_tags.tag_id as tag_id',
                'users.id as user_id',
                'users.name as user_name',
                'persons.id as person_id',
                'persons.name as person_name',
                'persons.contact_numbers',
                'tags.name as tag_name',
                'industry_options.name as industry',
                'industry_values.integer_value as industry_option_id',
                DB::raw('(
                    SELECT GROUP_CONCAT(services.name ORDER BY services.sort_order SEPARATOR ", ")
                    FROM lead_service
                    INNER JOIN services ON services.id = lead_service.service_id
                    WHERE lead_service.lead_id = leads.id
                ) as service_offered'),
                DB::raw('(
                    SELECT GROUP_CONCAT(lead_service.service_id ORDER BY services.sort_order SEPARATOR ",")
                    FROM lead_service
                    INNER JOIN services ON services.id = lead_service.service_id
                    WHERE lead_service.lead_id = leads.id
                ) as service_option_ids'),
                DB::raw('(
                    SELECT GROUP_CONCAT(products.name SEPARATOR ", ")
                    FROM lead_products
                    INNER JOIN products ON products.id = lead_products.product_id
                    WHERE lead_products.lead_id = leads.id
                ) as product_names'),
                DB::raw('(
                    SELECT COUNT(*)
                    FROM lead_activities
                    INNER JOIN activities ON activities.id = lead_activities.activity_id
                    WHERE lead_activities.lead_id = leads.id
                      AND activities.type = "meeting"
                ) as meeting_activity_count'),
            )
            ->leftJoin('users', 'leads.user_id', '=', 'users.id')
            ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
            ->leftJoin('organizations', 'leads.organization_id', '=', 'organizations.id')
            ->leftJoin('lead_types', 'leads.lead_type_id', '=', 'lead_types.id')
            ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->leftJoin('lead_sources', 'leads.lead_source_id', '=', 'lead_sources.id')
            ->leftJoin('linkedin_profiles', 'leads.linkedin_profile_id', '=', 'linkedin_profiles.id')
            ->leftJoin('lead_pipelines', 'leads.lead_pipeline_id', '=', 'lead_pipelines.id')
            ->leftJoin('lead_tags', 'leads.id', '=', 'lead_tags.lead_id')
            ->leftJoin('tags', 'tags.id', '=', 'lead_tags.tag_id')
            ->leftJoin('attribute_values as industry_values', function ($join) use ($industryAttributeId) {
                $join->on('industry_values.entity_id', '=', 'leads.id')
                    ->where('industry_values.entity_type', '=', 'leads');

                if ($industryAttributeId) {
                    $join->where('industry_values.attribute_id', '=', $industryAttributeId);
                } else {
                    $join->whereRaw('1 = 0');
                }
            })
            ->leftJoin('attribute_options as industry_options', 'industry_options.id', '=', 'industry_values.integer_value')
            ->groupBy('leads.id')
            ->whereNull('leads.deleted_at')
            ->where('leads.lead_pipeline_id', $this->pipeline->id);

        // Warm Lead first, then Cold Lead / Cold Call, then newest.
        $coldCallSourceId = DB::table('lead_sources')->where('name', 'Cold Call')->value('id');

        $queryBuilder->orderByRaw(
            'CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM lead_tags
                    INNER JOIN tags ON tags.id = lead_tags.tag_id
                    WHERE lead_tags.lead_id = leads.id
                      AND tags.name = ?
                ) THEN 0
                WHEN EXISTS (
                    SELECT 1
                    FROM lead_tags
                    INNER JOIN tags ON tags.id = lead_tags.tag_id
                    WHERE lead_tags.lead_id = leads.id
                      AND tags.name = ?
                ) THEN 1
                WHEN leads.lead_source_id IS NULL OR leads.lead_source_id = ? THEN 1
                ELSE 0
            END ASC',
            ['Warm Lead', 'Cold Lead', $coldCallSourceId ?: 0]
        );

        if (! app(\Webkul\Lead\Services\SourceAccessService::class)->isAdmin()) {
            app(\Webkul\Lead\Services\SourceAccessService::class)->applyLeadOwnerVisibilityTableScope($queryBuilder);
            app(\Webkul\Lead\Services\SourceAccessService::class)->applyNonTransferredOwnerTableScope($queryBuilder);
        }

        app(\Webkul\Lead\Services\SourceAccessService::class)->applyLeadTableScope($queryBuilder);

        $this->addFilter('id', 'leads.id');
        $this->addFilter('title', 'leads.title');
        $this->addFilter('company_name', 'organizations.name');
        $this->addFilter('organization_id', 'leads.organization_id');
        $this->addFilter('description', 'leads.description');
        $this->addFilter('source_link', 'leads.source_link');
        $this->addFilter('linkedin_profile_id', 'leads.linkedin_profile_id');
        $this->addFilter('user', 'leads.user_id');
        $this->addFilter('lead_source_name', 'lead_sources.id');
        $this->addFilter('lead_source_search', 'lead_sources.name');
        $this->addFilter('lead_type_name', 'lead_types.id');
        $this->addFilter('lead_value', 'leads.lead_value');
        $this->addFilter('person_name', 'persons.name');
        $this->addFilter('industry', 'industry_options.id');
        $this->addFilter('service_offered', 'leads.id');
        $this->addFilter('type', 'lead_pipeline_stages.code');
        $this->addFilter('stage', 'lead_pipeline_stages.id');
        $this->addFilter('tag_name', 'tags.id');
        $this->addFilter('next_followup_date', 'leads.next_followup_date');
        $this->addFilter('followup_count', 'leads.followup_count');
        $this->addFilter('last_followup_date', 'leads.last_followup_date');
        $this->addFilter('lead_disqualification_reason', 'leads.lead_disqualification_reason');

        return $queryBuilder;
    }

    /**
     * Process filters with special handling for services offered.
     */
    protected function processRequestedFilters(array $requestedFilters)
    {
        if (! empty($requestedFilters['service_offered'])) {
            $serviceIds = collect((array) $requestedFilters['service_offered'])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values()
                ->all();

            unset($requestedFilters['service_offered']);

            if (! empty($serviceIds)) {
                $this->queryBuilder->whereExists(function ($query) use ($serviceIds) {
                    $query
                        ->select(DB::raw(1))
                        ->from('lead_service')
                        ->whereColumn('lead_service.lead_id', 'leads.id')
                        ->whereIn('lead_service.service_id', $serviceIds);
                });
            }
        }

        return parent::processRequestedFilters($requestedFilters);
    }

    /**
     * Prepare columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.leads.index.datagrid.id'),
            'type'       => 'integer',
            'searchable' => true,
            'sortable'   => true,
            'filterable' => true,
        ]);

        if (lead_variant() !== 'main') {
            $this->addColumn([
                'index'      => 'title',
                'label'      => trans('admin::app.leads.index.datagrid.subject'),
                'type'       => 'string',
                'searchable' => true,
                'sortable'   => true,
                'closure'    => fn ($row) => $row->company_name ?: $row->title,
            ]);

            $this->addColumn([
                'index'      => 'company_name',
                'label'      => trans('admin::app.leads.index.datagrid.subject'),
                'type'       => 'string',
                'searchable' => true,
                'sortable'   => true,
                'filterable' => false,
                'visibility' => false,
            ]);
        } else {
            $this->addColumn([
                'index'      => 'title',
                'label'      => trans('admin::app.leads.index.datagrid.title'),
                'type'       => 'string',
                'searchable' => true,
                'sortable'   => true,
                'filterable' => true,
                'closure'    => fn ($row) => $row->title ?: '--',
            ]);

            $this->addColumn([
                'index'      => 'company_name',
                'label'      => trans('admin::app.leads.index.datagrid.company-name'),
                'type'       => 'string',
                'searchable' => true,
                'sortable'   => true,
                'filterable' => false,
                'visibility' => false,
            ]);
        }

        $this->addColumn([
            'index'      => 'description',
            'label'      => trans('admin::app.leads.index.datagrid.description'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => false,
            'filterable' => false,
            'visibility' => false,
        ]);

        $this->addColumn([
            'index'      => 'source_link',
            'label'      => trans('admin::app.leads.index.datagrid.source-link'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => false,
            'filterable' => false,
            'visibility' => false,
        ]);

        $this->addColumn([
            'index'      => 'lead_source_search',
            'label'      => trans('admin::app.leads.index.datagrid.source'),
            'type'       => 'string',
            'searchable' => true,
            'sortable'   => false,
            'filterable' => false,
            'visibility' => false,
        ]);

        $this->addColumn([
            'index'              => 'lead_source_name',
            'label'              => trans('admin::app.leads.index.datagrid.source'),
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => $this->sourceRepository->getRootDropdownOptions(),
        ]);

        if (lead_variant() === 'lge') {
            $this->addColumn([
                'index'              => 'linkedin_profile_id',
                'label'              => 'LinkedIn Profile',
                'type'               => 'string',
                'searchable'         => false,
                'sortable'           => true,
                'filterable'         => true,
                'filterable_type'    => 'dropdown',
                'filterable_options' => app(\Webkul\Lead\Services\LinkedInProfileAccessService::class)->getFilterOptionsWithHistoricalLeads(),
                'closure'            => fn ($row) => $row->linkedin_profile_name ?: '--',
            ]);
        }

        $this->addColumn([
            'index'              => 'lead_type_name',
            'label'              => trans('admin::app.leads.index.datagrid.lead-type'),
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => $this->typeRepository->all(['name as label', 'id as value'])->toArray(),
        ]);

        $this->addColumn([
            'index'      => 'lead_value',
            'label'      => trans('admin::app.leads.index.datagrid.lead-value'),
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => true,
            'filterable' => true,
            'visibility' => lead_variant() === 'main',
        ]);

        $this->addColumn([
            'index'              => 'user',
            'label'              => trans('admin::app.leads.index.datagrid.sales-person'),
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => DB::table('users')
                ->where('status', 1)
                ->orderBy('name')
                ->get(['name as label', 'id as value'])
                ->map(fn ($row) => [
                    'label' => $row->label,
                    'value' => (int) $row->value,
                ])
                ->values()
                ->all(),
            'closure'            => fn ($row) => $row->user_name ?: '--',
        ]);

        $this->addColumn([
            'index'              => 'industry',
            'label'              => trans('admin::app.leads.index.datagrid.industry'),
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => $this->getIndustryFilterOptions(),
            'closure'            => fn ($row) => $row->industry ?: '--',
        ]);

        $this->addColumn([
            'index'              => 'service_offered',
            'label'              => trans('admin::app.leads.index.datagrid.service-offered'),
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => false,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => app(\Webkul\Lead\Repositories\ServiceRepository::class)->getDropdownOptions(),
            'closure'            => fn ($row) => $row->service_offered ?: '--',
        ]);

        $this->addColumn([
            'index'      => 'product_names',
            'label'      => lead_variant() === 'sdr'
                ? trans('admin::app.leads.index.datagrid.packages')
                : trans('admin::app.leads.index.datagrid.products'),
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => false,
            'filterable' => false,
            'closure'    => fn ($row) => $row->product_names ?: '--',
        ]);

        $this->addColumn([
            'index'              => 'tag_name',
            'label'              => trans('admin::app.leads.index.datagrid.tag-name'),
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => DB::table('tags')
                ->orderBy('name')
                ->get(['name as label', 'id as value'])
                ->map(fn ($row) => [
                    'label' => $row->label,
                    'value' => (int) $row->value,
                ])
                ->values()
                ->all(),
            'closure'            => fn ($row) => $row->tag_name ?? '--',
        ]);

        $this->addColumn([
            'index'              => 'person_name',
            'label'              => trans('admin::app.leads.index.datagrid.contact-person'),
            'type'               => 'string',
            'searchable'         => true,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'searchable_dropdown',
            'filterable_options' => [
                'repository' => \Webkul\Contact\Repositories\PersonRepository::class,
                'column'     => [
                    'label' => 'name',
                    'value' => 'name',
                ],
            ],
            'closure'    => function ($row) {
                if (empty($row->person_id) || empty($row->person_name)) {
                    return '-';
                }

                $route = route('admin.contacts.persons.view', $row->person_id);

                return "<a class=\"text-brandColor transition-all hover:underline\" href='".$route."'>".$row->person_name.'</a>';
            },
        ]);

        $this->addColumn([
            'index'      => 'contact_numbers',
            'label'      => trans('admin::app.leads.index.datagrid.phone'),
            'type'       => 'string',
            'searchable' => false,
            'sortable'   => false,
            'filterable' => false,
            'closure'    => function ($row) {
                $numbers = collect(json_decode($row->contact_numbers ?? '[]', true) ?? [])
                    ->pluck('value')
                    ->filter()
                    ->values();

                if ($numbers->isEmpty()) {
                    return '--';
                }

                $phone = e($numbers->first());
                $allPhones = e($numbers->join(', '));

                return '<span class="inline-flex items-center gap-1.5 whitespace-nowrap">'
                    .'<span title="'.$allPhones.'">'.$phone.'</span>'
                    .'<button '
                    .'type="button" '
                    .'class="inline-flex h-6 shrink-0 items-center justify-center rounded border border-gray-200 px-1.5 text-xs font-semibold text-gray-600 transition-all hover:border-brandColor hover:text-brandColor dark:border-gray-700 dark:text-gray-300" '
                    .'title="'.e(trans('admin::app.leads.index.datagrid.copy-phone')).'" '
                    .'onclick="event.stopPropagation(); (window.copyLeadPhone || function () {})(this, \''.$phone.'\')"'
                    .'>'
                    .'Copy'
                    .'</button>'
                    .'</span>';
            },
        ]);

        $this->addColumn([
            'index'              => 'stage',
            'label'              => trans('admin::app.leads.index.datagrid.stage'),
            'type'               => 'string',
            'searchable'         => false,
            'sortable'           => true,
            'filterable'         => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => $this->getAccessiblePipelineStages()
                ->map(fn ($stage) => [
                    'value' => $stage->id,
                    'label' => $stage->name,
                ])
                ->values()
                ->all(),
        ]);

        $this->addColumn([
            'index'           => 'next_followup_date',
            'label'           => trans('admin::app.leads.index.datagrid.next-followup-date'),
            'type'            => 'date',
            'searchable'      => false,
            'sortable'        => true,
            'filterable'      => true,
            'filterable_type' => 'date_range',
            'closure'         => function ($row) {
                if (! $row->next_followup_date) {
                    return '--';
                }

                return core()->formatDate($row->next_followup_date);
            },
        ]);

        $this->addColumn([
            'index'      => 'followup_count',
            'label'      => trans('admin::app.leads.index.datagrid.followup-count'),
            'type'       => 'integer',
            'searchable' => false,
            'sortable'   => true,
            'filterable' => true,
            'closure'    => fn ($row) => $row->followup_count ?? 0,
        ]);

        $this->addColumn([
            'index'           => 'last_followup_date',
            'label'           => trans('admin::app.leads.index.datagrid.last-followup-date'),
            'type'            => 'date',
            'searchable'      => false,
            'sortable'        => true,
            'filterable'      => true,
            'filterable_type' => 'date_range',
            'closure'         => function ($row) {
                if (! $row->last_followup_date) {
                    return '--';
                }

                return core()->formatDate($row->last_followup_date);
            },
        ]);

    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission(lead_permission('view'))) {
            $this->addAction([
                'index'  => 'view',
                'icon'   => 'icon-eye',
                'title'  => trans('admin::app.leads.index.datagrid.view'),
                'method' => 'GET',
                'target' => '_blank',
                'url'    => fn ($row) => lead_route('view', $row->id),
            ]);
        }

        if (bouncer()->hasPermission(lead_permission('edit'))) {
            $this->addAction([
                'index'  => 'edit',
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.leads.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => fn ($row) => lead_route('form_data', $row->id),
            ]);

            $this->addAction([
                'index'  => 'note',
                'icon'   => 'icon-note',
                'title'  => trans('admin::app.leads.index.datagrid.add-note'),
                'method' => 'GET',
                'url'    => fn ($row) => route('admin.activities.store'),
            ]);
        }

        if (bouncer()->hasPermission(lead_permission('delete'))) {
            $this->addAction([
                'index'  => 'delete',
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.leads.index.datagrid.delete'),
                'method' => 'delete',
                'url'    => fn ($row) => lead_route('delete', $row->id),
            ]);
        }
    }

    /**
     * Prepare mass actions.
     */
    public function prepareMassActions(): void
    {
        if (bouncer()->hasPermission(lead_permission('delete'))) {
            $this->addMassAction([
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.leads.index.datagrid.mass-delete'),
                'method' => 'POST',
                'url'    => lead_route('mass_delete'),
            ]);
        }

        if (bouncer()->hasPermission(lead_permission('edit'))) {
            $this->addMassAction([
                'title'   => trans('admin::app.leads.index.datagrid.mass-update'),
                'url'     => lead_route('mass_update'),
                'method'  => 'POST',
                'options' => $this->getAccessiblePipelineStages()->map(fn ($stage) => [
                    'label' => $stage->name,
                    'value' => $stage->id,
                ])->values()->all(),
            ]);
        }
    }

    /**
     * Pipeline stages the current user may use for filters and mass updates.
     */
    protected function getAccessiblePipelineStages()
    {
        $stages = app(\Webkul\Lead\Services\SourceAccessService::class)
            ->filterAccessibleStages($this->pipeline->stages);

        if (! in_array(lead_variant(), ['sdr', 'lge'], true)) {
            return $stages;
        }

        $meetingStage = $this->pipeline->stages->firstWhere('code', 'meeting');

        if (! $meetingStage) {
            return $stages;
        }

        return $stages
            ->filter(fn ($stage) => (int) $stage->sort_order <= (int) $meetingStage->sort_order)
            ->values();
    }

    /**
     * Industry attribute options for the leads filter dropdown.
     *
     * @return array<int, array{label: string, value: int}>
     */
    protected function getIndustryFilterOptions(): array
    {
        return DB::table('attribute_options')
            ->join('attributes', 'attributes.id', '=', 'attribute_options.attribute_id')
            ->where('attributes.entity_type', 'leads')
            ->where('attributes.code', 'industry')
            ->orderBy('attribute_options.sort_order')
            ->orderBy('attribute_options.name')
            ->get([
                'attribute_options.name as label',
                'attribute_options.id as value',
            ])
            ->map(fn ($row) => [
                'label' => $row->label,
                'value' => (int) $row->value,
            ])
            ->values()
            ->all();
    }
}
