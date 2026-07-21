<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Admin\DataGrids\Lead\LeadDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\LeadForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Requests\MassUpdateRequest;
use Webkul\Admin\Http\Resources\LeadResource;
use Webkul\Admin\Http\Resources\StageResource;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Contact\Repositories\TeamRepository;
use Webkul\Lead\Helpers\MagicAI;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\ProductRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\StageRepository;
use Webkul\Lead\Repositories\TypeRepository;
use Webkul\Lead\Services\FollowupScheduleService;
use Webkul\Lead\Services\MagicAIService;
use Webkul\Lead\Services\SourceAccessService;
use Webkul\Tag\Repositories\TagRepository;
use Webkul\User\Repositories\UserRepository;

class LeadController extends Controller
{
    /**
     * Const variable for supported types.
     */
    const SUPPORTED_TYPES = 'pdf,bmp,jpeg,jpg,png,webp';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected UserRepository $userRepository,
        protected AttributeRepository $attributeRepository,
        protected SourceRepository $sourceRepository,
        protected TypeRepository $typeRepository,
        protected PipelineRepository $pipelineRepository,
        protected StageRepository $stageRepository,
        protected LeadRepository $leadRepository,
        protected ProductRepository $productRepository,
        protected PersonRepository $personRepository,
        protected OrganizationRepository $organizationRepository,
        protected TeamRepository $teamRepository,
        protected TagRepository $tagRepository,
        protected SourceAccessService $sourceAccessService,
        protected FollowupScheduleService $followupScheduleService,
    ) {
        request()->request->add(['entity_type' => 'leads']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(LeadDataGrid::class)->process();
        }

        if (request('pipeline_id')) {
            $pipeline = $this->pipelineRepository->find(request('pipeline_id'));
        } else {
            $pipeline = $this->pipelineRepository->getDefaultPipeline();
        }

        return view('admin::leads.index', [
            'pipeline' => $pipeline,
            'columns'  => $this->getKanbanColumns(),
        ]);
    }

    /**
     * Returns a listing of the resource.
     */
    public function get(): JsonResponse
    {
        if (request()->query('pipeline_id')) {
            $pipeline = $this->pipelineRepository->find(request()->query('pipeline_id'));
        } else {
            $pipeline = $this->pipelineRepository->getDefaultPipeline();
        }

        if ($stageId = request()->query('pipeline_stage_id')) {
            $stages = $pipeline->stages->where('id', request()->query('pipeline_stage_id'));
        } else {
            $stages = $pipeline->stages;
        }

        // Get sort parameters (default: newest first)
        $sortBy = request()->query('sort_by', 'created_at');
        $sortOrder = request()->query('sort_order', 'desc');

        foreach ($stages as $stage) {
            /**
             * We have to create a new instance of the lead repository every time, which is
             * why we're not using the injected one.
             */
            $query = app(LeadRepository::class)
                ->pushCriteria(app(RequestCriteria::class))
                ->where([
                    'lead_pipeline_id'       => $pipeline->id,
                    'lead_pipeline_stage_id' => $stage->id,
                ]);

            if ($userIds = bouncer()->getAuthorizedUserIds()) {
                $query->whereIn('leads.user_id', $userIds);
            }

            $this->sourceAccessService->applyLeadQueryScope($query);

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            $stage->lead_value = (clone $query)->sum('lead_value');

            $data[$stage->sort_order] = (new StageResource($stage))->jsonSerialize();

            $data[$stage->sort_order]['leads'] = [
                'data' => LeadResource::collection($paginator = $query->with([
                    'tags',
                    'type',
                    'source',
                    'subSource',
                    'user',
                    'person',
                    'person.organization',
                    'pipeline',
                    'pipeline.stages',
                    'stage',
                    'attribute_values',
                ])->paginate(10)),

                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'from'         => $paginator->firstItem(),
                    'last_page'    => $paginator->lastPage(),
                    'per_page'     => $paginator->perPage(),
                    'to'           => $paginator->lastItem(),
                    'total'        => $paginator->total(),
                ],
            ];
        }

        return response()->json($data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin::leads.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LeadForm $request): RedirectResponse|JsonResponse
    {
        Event::dispatch('lead.create.before');

        $data = request()->all();

        $data['status'] = 1;

        if (! empty($data['lead_pipeline_stage_id'])) {
            $stage = $this->stageRepository->findOrFail($data['lead_pipeline_stage_id']);

            $data['lead_pipeline_id'] = $stage->lead_pipeline_id;
        } else {
            if (empty($data['lead_pipeline_id'])) {
                $pipeline = $this->pipelineRepository->getDefaultPipeline();

                $data['lead_pipeline_id'] = $pipeline->id;
            } else {
                $pipeline = $this->pipelineRepository->findOrFail($data['lead_pipeline_id']);
            }

            $stage = $pipeline->stages()->first();

            $data['lead_pipeline_stage_id'] = $stage->id;
        }

        if (in_array($stage->code, ['won', 'lost'])) {
            $data['closed_at'] = Carbon::now();
        }

        $lead = $this->leadRepository->create($data);

        $this->syncLeadTags($lead, $data['tags'] ?? []);

        if (request()->ajax()) {
            return response()->json([
                'message' => trans('admin::app.leads.create-success'),
                'data'    => new LeadResource($lead),
            ]);
        }

        Event::dispatch('lead.create.after', $lead);

        session()->flash('success', trans('admin::app.leads.create-success'));

        if (! empty($data['lead_pipeline_id'])) {
            $params['pipeline_id'] = $data['lead_pipeline_id'];
        }

        return redirect()->route('admin.leads.index', $params ?? []);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View|RedirectResponse
    {
        $lead = $this->leadRepository->findOrFail($id);

        $userIds = bouncer()->getAuthorizedUserIds();

        if ($userIds && ! in_array($lead->user_id, $userIds)) {
            return redirect()->route('admin.leads.index');
        }

        if (! $this->sourceAccessService->canAccessLead($lead)) {
            return redirect()->route('admin.leads.index');
        }

        return view('admin::leads.edit', compact('lead'));
    }

    /**
     * Display a resource.
     */
    public function view(int $id)
    {
        $lead = $this->leadRepository->findOrFail($id);

        $userIds = bouncer()->getAuthorizedUserIds();

        if (
            $userIds
            && ! in_array($lead->user_id, $userIds)
        ) {
            return redirect()->route('admin.leads.index');
        }

        if (! $this->sourceAccessService->canAccessLead($lead)) {
            return redirect()->route('admin.leads.index');
        }

        return view('admin::leads.view', compact('lead'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LeadForm $request, int $id): RedirectResponse|JsonResponse
    {
        Event::dispatch('lead.update.before', $id);

        $data = $request->all();

        if (isset($data['lead_pipeline_stage_id'])) {
            $stage = $this->stageRepository->findOrFail($data['lead_pipeline_stage_id']);

            $data['lead_pipeline_id'] = $stage->lead_pipeline_id;
        } else {
            $pipeline = $this->pipelineRepository->getDefaultPipeline();

            $stage = $pipeline->stages()->first();

            $data['lead_pipeline_id'] = $pipeline->id;

            $data['lead_pipeline_stage_id'] = $stage->id;
        }

        $lead = $this->leadRepository->update($data, $id);

        $this->syncLeadTags($lead, $data['tags'] ?? []);

        Event::dispatch('lead.update.after', $lead);

        if (request()->ajax()) {
            return response()->json([
                'message' => trans('admin::app.leads.update-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.leads.update-success'));

        if (request()->has('closed_at')) {
            return redirect()->back();
        } else {
            return redirect()->route('admin.leads.index', $data['lead_pipeline_id']);
        }
    }

    /**
     * Update the lead attributes.
     */
    public function updateAttributes(int $id)
    {
        $data = request()->all();

        $attributes = $this->attributeRepository->findWhere([
            'entity_type' => 'leads',
            ['code', 'NOTIN', ['title', 'description']],
        ]);

        Event::dispatch('lead.update.before', $id);

        $lead = $this->leadRepository->update($data, $id, $attributes);

        Event::dispatch('lead.update.after', $lead);

        return response()->json([
            'message' => trans('admin::app.leads.update-success'),
        ]);
    }

    /**
     * Update the lead stage.
     */
    public function updateStage(int $id)
    {
        $this->validate(request(), [
            'lead_pipeline_stage_id' => 'required',
        ]);

        $lead = $this->leadRepository->findOrFail($id);

        $stage = $lead->pipeline->stages()
            ->where('id', request()->input('lead_pipeline_stage_id'))
            ->firstOrFail();

        Event::dispatch('lead.update.before', $id);

        $payload = request()->merge([
            'entity_type'            => 'leads',
            'lead_pipeline_stage_id' => $stage->id,
        ])->only([
            'closed_at',
            'lost_reason',
            'lead_pipeline_stage_id',
            'entity_type',
        ]);

        $lead = $this->leadRepository->update($payload, $id, ['lead_pipeline_stage_id']);

        Event::dispatch('lead.update.after', $lead);

        return response()->json([
            'message' => trans('admin::app.leads.update-success'),
        ]);
    }

    /**
     * Search person results.
     */
    public function search(): AnonymousResourceCollection
    {
        $userIds = bouncer()->getAuthorizedUserIds();

        $results = $this->leadRepository
            ->pushCriteria(app(RequestCriteria::class))
            ->scopeQuery(function ($query) use ($userIds) {
                if ($userIds) {
                    $query->whereIn('user_id', $userIds);
                }

                return $this->sourceAccessService->applyLeadQueryScope($query);
            })
            ->all();

        return LeadResource::collection($results);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->leadRepository->findOrFail($id);

        try {
            Event::dispatch('lead.delete.before', $id);

            $this->leadRepository->delete($id);

            Event::dispatch('lead.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.leads.destroy-success'),
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.leads.destroy-failed'),
            ], 400);
        }
    }

    /**
     * Mass update the specified resources.
     */
    public function massUpdate(MassUpdateRequest $massUpdateRequest): JsonResponse
    {
        $leads = $this->leadRepository->findWhereIn('id', $massUpdateRequest->input('indices'));

        try {
            foreach ($leads as $lead) {
                Event::dispatch('lead.update.before', $lead->id);

                $lead = $this->leadRepository->find($lead->id);

                $lead?->update(['lead_pipeline_stage_id' => $massUpdateRequest->input('value')]);

                Event::dispatch('lead.update.before', $lead->id);
            }

            return response()->json([
                'message' => trans('admin::app.leads.update-success'),
            ]);
        } catch (\Exception $th) {
            return response()->json([
                'message' => trans('admin::app.leads.update-failed'),
            ], 400);
        }
    }

    /**
     * Mass delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $leads = $this->leadRepository->findWhereIn('id', $massDestroyRequest->input('indices'));

        try {
            foreach ($leads as $lead) {
                Event::dispatch('lead.delete.before', $lead->id);

                $this->leadRepository->delete($lead->id);

                Event::dispatch('lead.delete.after', $lead->id);
            }

            return response()->json([
                'message' => trans('admin::app.leads.destroy-success'),
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.leads.destroy-failed'),
            ]);
        }
    }

    /**
     * Attach product to lead.
     */
    public function addProduct(int $leadId): JsonResponse
    {
        $product = $this->productRepository->updateOrCreate(
            [
                'lead_id'    => $leadId,
                'product_id' => request()->input('product_id'),
            ],
            array_merge(
                request()->all(),
                [
                    'lead_id' => $leadId,
                    'amount'  => request()->input('price') * request()->input('quantity'),
                ],
            )
        );

        return response()->json([
            'data'    => $product,
            'message' => trans('admin::app.leads.update-success'),
        ]);
    }

    /**
     * Remove product attached to lead.
     */
    public function removeProduct(int $id): JsonResponse
    {
        try {
            Event::dispatch('lead.product.delete.before', $id);

            $this->productRepository->deleteWhere([
                'lead_id'    => $id,
                'product_id' => request()->input('product_id'),
            ]);

            Event::dispatch('lead.product.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.leads.destroy-success'),
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.leads.destroy-failed'),
            ]);
        }
    }

    /**
     * Kanban lookup.
     */
    public function kanbanLookup()
    {
        $params = $this->validate(request(), [
            'column'      => ['required'],
            'search'      => ['required', 'min:2'],
        ]);

        /**
         * Finding the first column from the collection.
         */
        $column = collect($this->getKanbanColumns())->where('index', $params['column'])->firstOrFail();

        /**
         * Fetching on the basis of column options.
         */
        return app($column['filterable_options']['repository'])
            ->select([$column['filterable_options']['column']['label'].' as label', $column['filterable_options']['column']['value'].' as value'])
            ->where($column['filterable_options']['column']['label'], 'LIKE', '%'.$params['search'].'%')
            ->get()
            ->map
            ->only('label', 'value');
    }

    /**
     * Get columns for the kanban view.
     */
    private function getKanbanColumns(): array
    {
        return [
            [
                'index'                 => 'id',
                'label'                 => trans('admin::app.leads.index.kanban.columns.id'),
                'type'                  => 'integer',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => null,
                'filterable_options'    => [],
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
            [
                'index'                 => 'lead_value',
                'label'                 => trans('admin::app.leads.index.kanban.columns.lead-value'),
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => null,
                'filterable_options'    => [],
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
            [
                'index'                 => 'user_id',
                'label'                 => trans('admin::app.leads.index.kanban.columns.sales-person'),
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => 'searchable_dropdown',
                'filterable_options'    => [
                    'repository' => UserRepository::class,
                    'column'     => [
                        'label' => 'name',
                        'value' => 'id',
                    ],
                ],
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
            [
                'index'                 => 'person.id',
                'label'                 => trans('admin::app.leads.index.kanban.columns.contact-person'),
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_options'    => [],
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
                'filterable_type'       => 'searchable_dropdown',
                'filterable_options'    => [
                    'repository' => PersonRepository::class,
                    'column'     => [
                        'label' => 'name',
                        'value' => 'id',
                    ],
                ],
            ],
            [
                'index'                 => 'lead_type_id',
                'label'                 => trans('admin::app.leads.index.kanban.columns.lead-type'),
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => 'dropdown',
                'filterable_options'    => $this->typeRepository->all(['name as label', 'id as value'])->toArray(),
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
            [
                'index'                 => 'lead_source_id',
                'label'                 => trans('admin::app.leads.index.kanban.columns.source'),
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => 'dropdown',
                'filterable_options'    => $this->sourceRepository->getRootDropdownOptions(),
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
            ],
            [
                'index'                 => 'tags.name',
                'label'                 => trans('admin::app.leads.index.kanban.columns.tags'),
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_options'    => [],
                'allow_multiple_values' => true,
                'sortable'              => true,
                'visibility'            => true,
                'filterable_type'       => 'searchable_dropdown',
                'filterable_options'    => [
                    'repository' => TagRepository::class,
                    'column'     => [
                        'label' => 'name',
                        'value' => 'name',
                    ],
                ],
            ],
        ];
    }

    /**
     * Create lead with specified AI.
     */
    public function createByAI()
    {
        $leadData = [];

        $errorMessages = [];

        foreach (request()->file('files') as $file) {
            $lead = $this->processFile($file);

            if (
                isset($lead['status'])
                && $lead['status'] === 'error'
            ) {
                $errorMessages[] = $lead['message'];
            } else {
                $leadData[] = $lead;
            }
        }

        if (isset($errorMessages[0]['code'])) {
            return response()->json(MagicAI::errorHandler($errorMessages[0]['message']));
        }

        if (
            empty($leadData)
            && ! empty($errorMessages)
        ) {
            return response()->json(MagicAI::errorHandler(implode(', ', $errorMessages)), 400);
        }

        if (empty($leadData)) {
            return response()->json(MagicAI::errorHandler(trans('admin::app.leads.no-valid-files')), 400);
        }

        return response()->json([
            'message' => trans('admin::app.leads.create-success'),
            'leads'   => $this->createLeads($leadData),
        ]);
    }

    /**
     * Process file.
     *
     * @param  mixed  $file
     */
    private function processFile($file)
    {
        $validator = Validator::make(
            ['file' => $file],
            ['file' => 'required|extensions:'.str_replace(' ', '', self::SUPPORTED_TYPES)]
        );

        if ($validator->fails()) {
            return MagicAI::errorHandler($validator->errors()->first());
        }

        $base64Pdf = base64_encode(file_get_contents($file->getRealPath()));

        $extractedData = MagicAIService::extractDataFromFile($base64Pdf);

        $lead = MagicAI::mapAIDataToLead($extractedData);

        return $lead;
    }

    /**
     * Create multiple leads.
     */
    private function createLeads($rawLeads): array
    {
        $leads = [];

        foreach ($rawLeads as $rawLead) {
            Event::dispatch('lead.create.before');

            foreach ($rawLead['person']['emails'] as $email) {
                $person = $this->personRepository
                    ->whereJsonContains('emails', [['value' => $email['value']]])
                    ->first();

                if ($person) {
                    $rawLead['person']['id'] = $person->id;

                    break;
                }
            }

            $pipeline = $this->pipelineRepository->getDefaultPipeline();

            $stage = $pipeline->stages()->first();

            $lead = $this->leadRepository->create(array_merge($rawLead, [
                'lead_pipeline_id'       => $pipeline->id,
                'lead_pipeline_stage_id' => $stage->id,
            ]));

            $this->syncLeadTags($lead, $rawLead['tags'] ?? []);

            Event::dispatch('lead.create.after', $lead);

            $leads[] = $lead;
        }

        return $leads;
    }

    /**
     * Sync tag names on a lead.
     */
    private function syncLeadTags($lead, array $tagNames): void
    {
        $tagIds = collect($tagNames)
            ->filter(fn ($name) => filled($name))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->map(function (string $name): int {
                $tag = $this->tagRepository->findOneWhere([
                    'name'    => $name,
                    'user_id' => auth()->id(),
                ]);

                if (! $tag) {
                    $tag = $this->tagRepository->create([
                        'name'    => $name,
                        'user_id' => auth()->id(),
                    ]);
                }

                return $tag->id;
            })
            ->values()
            ->all();

        $lead->tags()->sync($tagIds);
    }

    /**
     * Duplicate a lead into selected companies and optional teams.
     */
    public function duplicateToCompanies(int $id): RedirectResponse
    {
        $data = request()->validate([
            'organization_ids'   => ['required', 'array', 'min:1'],
            'organization_ids.*' => ['required', 'integer', 'exists:organizations,id'],
            'team_ids'           => ['nullable', 'array'],
            'team_ids.*'         => ['integer', 'exists:teams,id'],
        ]);

        $lead = $this->leadRepository->with(['person', 'tags'])->findOrFail($id);

        if (! $this->sourceAccessService->canAccessLead($lead)) {
            return redirect()->route('admin.leads.index');
        }

        $organizationIds = array_values(array_unique(array_map('intval', $data['organization_ids'])));
        $teamIds = array_values(array_unique(array_map('intval', $data['team_ids'] ?? [])));

        $allowedOrganizationIds = collect($this->organizationRepository->getDropdownOptions())
            ->pluck('value')
            ->map(fn ($value) => (int) $value)
            ->all();

        if (array_diff($organizationIds, $allowedOrganizationIds)) {
            return redirect()->back()->withErrors([
                'organization_ids' => trans('admin::app.leads.replicate.invalid-companies'),
            ]);
        }

        $teamsByOrganization = collect();

        if (! empty($teamIds)) {
            $teams = $this->teamRepository->getModel()
                ->newQuery()
                ->with(['organizations' => fn ($query) => $query->whereIn('organizations.id', $organizationIds)])
                ->whereIn('id', $teamIds)
                ->get();

            if ($teams->count() !== count($teamIds)) {
                return redirect()->back()->withErrors([
                    'team_ids' => trans('admin::app.leads.replicate.invalid-teams'),
                ]);
            }

            $invalidTeams = $teams->filter(
                fn ($team) => $team->organizations->isEmpty()
            );

            if ($invalidTeams->isNotEmpty()) {
                return redirect()->back()->withErrors([
                    'team_ids' => trans('admin::app.leads.replicate.invalid-teams'),
                ]);
            }

            $teamsByOrganization = $teams
                ->flatMap(fn ($team) => $team->organizations->map(fn ($organization) => [
                    'organization_id' => (int) $organization->id,
                    'team'            => $team,
                ]))
                ->groupBy('organization_id')
                ->map(fn ($rows) => $rows->pluck('team'));
        }

        $replicasCreated = 0;

        DB::transaction(function () use ($lead, $organizationIds, $teamsByOrganization, &$replicasCreated) {
            foreach ($organizationIds as $organizationId) {
                $organizationTeams = $teamsByOrganization->get($organizationId, collect());

                if ($organizationTeams->isEmpty()) {
                    Event::dispatch('lead.create.before');

                    $replicatedLead = $this->leadRepository->create(
                        $this->buildLeadDuplicatePayload($lead, (int) $organizationId)
                    );

                    $this->syncLeadTags($replicatedLead, $lead->tags->pluck('name')->all());

                    Event::dispatch('lead.create.after', $replicatedLead);

                    $replicasCreated++;

                    continue;
                }

                foreach ($organizationTeams as $team) {
                    Event::dispatch('lead.create.before');

                    $replicatedLead = $this->leadRepository->create(
                        $this->buildLeadDuplicatePayload($lead, (int) $organizationId, (int) $team->id)
                    );

                    $this->syncLeadTags($replicatedLead, $lead->tags->pluck('name')->all());

                    Event::dispatch('lead.create.after', $replicatedLead);

                    $replicasCreated++;
                }
            }
        });

        session()->flash('success', trans('admin::app.leads.replicate.success', [
            'count' => $replicasCreated,
        ]));

        return redirect()->back();
    }

    /**
     * Build duplicate payload for the selected company.
     */
    private function buildLeadDuplicatePayload($lead, int $organizationId, ?int $teamId = null): array
    {
        $payload = [];

        foreach ($lead->getFillable() as $field) {
            $payload[$field] = $lead->getRawOriginal($field);
        }

        $payload = Arr::except($payload, [
            'person_id',
            'team_id',
            'next_followup_date',
            'followup_count',
            'last_followup_date',
            'followup_notes',
        ]);

        $payload['entity_type'] = 'leads';
        $payload['team_id'] = $teamId;
        $payload['person'] = $this->buildDuplicatePersonPayload($lead, $organizationId);

        return $payload;
    }

    /**
     * Build duplicate person payload for the selected company.
     */
    private function buildDuplicatePersonPayload($lead, int $organizationId): array
    {
        if (! $lead->person) {
            return [
                'organization_id' => $organizationId,
            ];
        }

        $payload = [
            'name'            => $lead->person->name,
            'emails'          => $lead->person->emails ?? [],
            'contact_numbers' => $lead->person->contact_numbers ?? [],
            'job_title'       => $lead->person->job_title,
            'user_id'         => $lead->person->user_id,
            'organization_id' => $organizationId,
            'address'         => $lead->person->address,
            'website'         => $lead->person->website,
        ];

        $existingPerson = $this->personRepository->findByUniqueIdentity($payload);

        if ($existingPerson) {
            return [
                'id' => $existingPerson->id,
            ];
        }

        return $payload;
    }

    /**
     * Mark follow-up as complete.
     */
    public function followupComplete(int $id): RedirectResponse
    {
        $data = request()->validate([
            'current_followup_date'  => ['required', 'date'],
            'schedule_next_followup' => ['required', 'boolean'],
            'next_followup_date'     => ['required_if:schedule_next_followup,1', 'nullable', 'date', 'after:now'],
        ]);

        $result = DB::transaction(function () use ($id, $data) {
            $lead = $this->leadRepository
                ->getModel()
                ->newQuery()
                ->lockForUpdate()
                ->findOrFail($id);

            if (! $lead->next_followup_date) {
                return ['lead' => $lead, 'exhausted' => false];
            }

            if (! Carbon::parse($lead->next_followup_date)->equalTo(Carbon::parse($data['current_followup_date']))) {
                return ['lead' => $lead, 'exhausted' => false];
            }

            Event::dispatch('lead.update.before', $id);

            $completedAt = Carbon::now();
            $manualNext = request()->boolean('schedule_next_followup')
                ? Carbon::parse($data['next_followup_date'])
                : null;

            $lead->newQuery()
                ->whereKey($lead->getKey())
                ->update([
                    'followup_count'     => ($lead->followup_count ?? 0) + 1,
                    'last_followup_date' => $completedAt,
                ]);

            $lead->refresh();

            $this->followupScheduleService->applyNextFollowup($lead, $manualNext);

            $lead->refresh();

            $exhausted = ! $manualNext
                && is_null($lead->next_followup_date)
                && optional($lead->loadMissing('stage')->stage)->code === 'lost';

            Event::dispatch('lead.update.after', $lead);

            return ['lead' => $lead, 'exhausted' => $exhausted];
        });

        if ($result['exhausted']) {
            session()->flash('warning', trans('admin::app.leads.followup-schedule-ended'));
        } else {
            session()->flash('success', trans('admin::app.leads.followup-complete-success'));
        }

        return redirect()->back();
    }
}
