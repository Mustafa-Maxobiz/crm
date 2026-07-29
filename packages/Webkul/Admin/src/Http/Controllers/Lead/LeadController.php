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
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use Prettus\Repository\Criteria\RequestCriteria;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
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
use Webkul\Lead\Models\Lead;
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

        if (! request()->has('view_type')) {
            return redirect()->route('admin.leads.index', array_merge(request()->query(), [
                'view_type' => 'table',
            ]));
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
     * Download admin lead import template.
     */
    public function importTemplate(): StreamedResponse
    {
        abort_unless($this->sourceAccessService->isAdmin(), 403);

        $headers = [
            'title*',
            'lead_value*',
            'source*',
            'type*',
            'pricing_type*',
            'person_name',
            'email',
            'phone',
            'company',
            'sales_owner_email',
            'pipeline',
            'stage',
            'expected_close_date',
            'schedule_followup',
            'next_followup_date',
            'description',
            'source_link',
            'sub_source',
            'source_sub_type',
            'tags',
        ];

        $sample = [
            'Sample Lead',
            '5000',
            'Cold Call',
            'New Business',
            'Fixed Price',
            'John Smith',
            'john@example.com',
            '+15551234567',
            'Sample Company',
            'sdr@example.com',
            'Default Pipeline',
            'New',
            Carbon::now()->addDays(14)->toDateString(),
            'yes',
            Carbon::now()->addDay()->format('Y-m-d H:i:s'),
            'Imported lead description',
            'https://example.com/source',
            '',
            '',
            'Cold Call,priority',
        ];

        return response()->streamDownload(function () use ($headers, $sample) {
            $stream = fopen('php://output', 'w');

            fputcsv($stream, $headers);
            fputcsv($stream, $sample);

            fclose($stream);
        }, 'lead-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Import leads from CSV/XLSX for admins.
     */
    public function import(): RedirectResponse|JsonResponse
    {
        abort_unless($this->sourceAccessService->isAdmin(), 403);

        $data = request()->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ]);

        try {
            $sheets = Excel::toArray(new class implements ToArray
            {
                public function array(array $array) {}
            }, $data['file']);
        } catch (Throwable) {
            return $this->importResponse(0, [
                'The uploaded file could not be read. Please upload a valid CSV or XLSX file.',
            ], 422);
        }

        $rows = $sheets[0] ?? [];

        if (count($rows) < 2) {
            return $this->importResponse(0, [
                'The import file has no data rows.',
            ], 422);
        }

        $headers = $this->normalizeImportHeaders(array_shift($rows));
        $missingHeaders = array_diff($this->requiredImportColumns(), array_keys($headers));

        if (! empty($missingHeaders)) {
            return $this->importResponse(0, [
                'Missing required columns: '.implode(', ', array_map(fn ($column) => $column.'*', $missingHeaders)),
            ], 422);
        }

        $created = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            if ($this->isEmptyImportRow($row)) {
                continue;
            }

            $rowData = $this->mapImportRow($headers, $row);
            $rowErrors = $this->validateImportRow($rowData);

            if (! empty($rowErrors)) {
                $errors[] = 'Row '.$rowNumber.': '.implode(' ', $rowErrors);

                continue;
            }

            try {
                Event::dispatch('lead.create.before');

                $lead = $this->leadRepository->create($this->prepareImportedLeadData($rowData));

                $this->syncLeadTags($lead, $this->tagsFromImportRow($rowData));

                $this->syncSourceTagForLead($lead);

                Event::dispatch('lead.create.after', $lead);

                $created++;
            } catch (Throwable $exception) {
                $errors[] = 'Row '.$rowNumber.': '.$exception->getMessage();
            }
        }

        return $this->importResponse($created, $errors, $created || empty($errors) ? 200 : 422);
    }

    /**
     * Start an AJAX lead import and persist normalized rows for chunked processing.
     */
    public function importStart(): JsonResponse
    {
        abort_unless($this->sourceAccessService->isAdmin(), 403);

        $data = request()->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ]);

        try {
            $sheets = Excel::toArray(new class implements ToArray
            {
                public function array(array $array) {}
            }, $data['file']);
        } catch (Throwable) {
            return response()->json([
                'message' => 'The uploaded file could not be read. Please upload a valid CSV or XLSX file.',
            ], 422);
        }

        $rows = $sheets[0] ?? [];

        if (count($rows) < 2) {
            return response()->json([
                'message' => 'The import file has no data rows.',
            ], 422);
        }

        $headers = $this->normalizeImportHeaders(array_shift($rows));
        $missingHeaders = array_diff($this->requiredImportColumns(), array_keys($headers));

        if (! empty($missingHeaders)) {
            return response()->json([
                'message' => 'Missing required columns: '.implode(', ', array_map(fn ($column) => $column.'*', $missingHeaders)),
            ], 422);
        }

        $importRows = [];

        foreach ($rows as $index => $row) {
            if ($this->isEmptyImportRow($row)) {
                continue;
            }

            $importRows[] = [
                'row_number' => $index + 2,
                'data'       => $this->mapImportRow($headers, $row),
            ];
        }

        if (empty($importRows)) {
            return response()->json([
                'message' => 'The import file has no importable rows.',
            ], 422);
        }

        $token = (string) Str::uuid();
        $directory = storage_path('app/imports/pending');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->pendingImportPath($token), json_encode([
            'rows'    => $importRows,
            'created' => 0,
            'errors'  => [],
        ], JSON_THROW_ON_ERROR));

        return response()->json([
            'token'   => $token,
            'total'   => count($importRows),
            'message' => count($importRows).' row'.(count($importRows) === 1 ? '' : 's').' ready to import.',
        ]);
    }

    /**
     * Process the next chunk of a pending AJAX lead import.
     */
    public function importProcess(): JsonResponse
    {
        abort_unless($this->sourceAccessService->isAdmin(), 403);

        $data = request()->validate([
            'token'  => ['required', 'string'],
            'offset' => ['required', 'integer', 'min:0'],
        ]);

        $path = $this->pendingImportPath($data['token']);

        if (! is_file($path)) {
            return response()->json([
                'message' => 'Import session expired. Please upload the file again.',
            ], 404);
        }

        $payload = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $rows = $payload['rows'] ?? [];
        $total = count($rows);
        $offset = (int) $data['offset'];
        $chunkSize = 1;
        $chunk = array_slice($rows, $offset, $chunkSize);

        foreach ($chunk as $row) {
            $rowData = $row['data'] ?? [];
            $rowErrors = $this->validateImportRow($rowData);

            if (! empty($rowErrors)) {
                $payload['errors'][] = 'Row '.$row['row_number'].': '.implode(' ', $rowErrors);

                continue;
            }

            try {
                Event::dispatch('lead.create.before');

                $lead = $this->leadRepository->create($this->prepareImportedLeadData($rowData));

                $this->syncLeadTags($lead, $this->tagsFromImportRow($rowData));

                $this->syncSourceTagForLead($lead);

                Event::dispatch('lead.create.after', $lead);

                $payload['created']++;
            } catch (Throwable $exception) {
                $payload['errors'][] = 'Row '.$row['row_number'].': '.$exception->getMessage();
            }
        }

        $processed = min($offset + count($chunk), $total);
        $done = $processed >= $total;

        if ($done) {
            @unlink($path);
        } else {
            file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
        }

        return response()->json([
            'processed' => $processed,
            'total'     => $total,
            'created'   => $payload['created'],
            'errors'    => $payload['errors'],
            'done'      => $done,
            'message'   => $payload['created'].' lead'.($payload['created'] === 1 ? '' : 's').' imported.',
        ]);
    }

    /**
     * Display admin-only DNC and incorrect-info leads.
     */
    public function disqualified(): View|RedirectResponse
    {
        if (! $this->sourceAccessService->isAdmin()) {
            return redirect()->route('admin.leads.index');
        }

        $baseQuery = Lead::query()
            ->with(['person.organization', 'user', 'source', 'subSource'])
            ->whereNotNull('lead_disqualification_reason')
            ->orderByDesc('lead_disqualified_at')
            ->orderByDesc('id');

        return view('admin::leads.disqualified', [
            'doNotCallLeads' => (clone $baseQuery)
                ->where('lead_disqualification_reason', 'do_not_call')
                ->paginate(5, ['*'], 'dnc_page'),
            'incorrectInfoLeads' => (clone $baseQuery)
                ->where('lead_disqualification_reason', 'incorrect_info')
                ->paginate(5, ['*'], 'incorrect_page'),
            'endedLeads' => (clone $baseQuery)
                ->where('lead_disqualification_reason', 'ended')
                ->paginate(5, ['*'], 'ended_page'),
            'users' => $this->userRepository
                ->orderBy('name')
                ->all(['id', 'name', 'email']),
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

            $isSharedNewSdrPool = $this->isSdrUser()
                && $stage->code === 'new';

            $userIds = bouncer()->getAuthorizedUserIds();

            if (! $this->sourceAccessService->isAdmin() && ! $isSharedNewSdrPool && $userIds) {
                $query->whereIn('leads.user_id', $userIds);
            }

            $this->sourceAccessService->applyLeadQueryScope($query);

            $this->applyKanbanSearch($query, request()->query('lead_search'));

            // Apply sorting
            $query->orderBy($sortBy, $sortOrder);

            $stage->lead_value = (clone $query)->sum('lead_value');

            $data[$stage->sort_order] = (new StageResource($stage))->jsonSerialize();

            $data[$stage->sort_order]['leads'] = [
                'data' => LeadResource::collection($paginator = $query->with([
                    'tags.user',
                    'type',
                    'source',
                    'subSource',
                    'user',
                    'person',
                    'person.organization',
                    'pipeline',
                    'pipeline.stages',
                    'stage',
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
     * Apply one grouped kanban search so filters stay strict and search matches
     * only leads containing the entered term in visible lead-related fields.
     */
    protected function applyKanbanSearch(mixed $query, mixed $search): void
    {
        $search = trim((string) $search);

        if ($search === '') {
            return;
        }

        $query->where(function ($query) use ($search) {
            $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('source_link', 'like', "%{$search}%")
                ->orWhere('lead_value', 'like', "%{$search}%")
                ->orWhereHas('person', function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhereHas('organization', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                })
                ->orWhereHas('user', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('source', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('subSource', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('type', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('tags', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                });
        });
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

        $this->syncSourceTagForLead($lead);

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

        if (! $this->sourceAccessService->canAccessLead($lead)) {
            return redirect()->route('admin.leads.index');
        }

        if ($this->isSdrUser()) {
            $lead = $this->claimNewLeadForSdr($lead);
        }

        $userIds = bouncer()->getAuthorizedUserIds();

        if ($userIds && ! in_array($lead->user_id, $userIds)) {
            return redirect()->route('admin.leads.index');
        }

        return view('admin::leads.edit', compact('lead'));
    }

    /**
     * Return lead form values for the table edit modal.
     */
    public function formData(int $id): JsonResponse|RedirectResponse
    {
        $lead = $this->leadRepository->findOrFail($id);

        if (! $this->sourceAccessService->canAccessLead($lead)) {
            abort(403);
        }

        if ($this->isSdrUser()) {
            $lead = $this->claimNewLeadForSdr($lead);
        }

        $userIds = bouncer()->getAuthorizedUserIds();

        if ($userIds && ! in_array($lead->user_id, $userIds)) {
            abort(403);
        }

        $lead->load(['person.organization', 'tags', 'pipeline.stages']);

        $data = $lead->attributesToArray();

        foreach (['expected_close_date', 'next_followup_date', 'last_followup_date', 'closed_at', 'lead_disqualified_at'] as $dateField) {
            $raw = $lead->getAttributes()[$dateField] ?? null;

            if (! $raw) {
                $data[$dateField] = null;

                continue;
            }

            try {
                $parsed = Carbon::parse($raw);
                $data[$dateField] = in_array($dateField, ['expected_close_date'], true)
                    ? $parsed->format('Y-m-d')
                    : $parsed->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                $data[$dateField] = $raw;
            }
        }

        $data['entity_type'] = 'leads';
        $data['quick_add'] = 1;
        $data['tags'] = $lead->tags->pluck('name')->values()->all();
        $data['person'] = $lead->person
            ? [
                'id'               => $lead->person->id,
                'name'             => $lead->person->name,
                'emails'           => $lead->person->emails ?: [['value' => '', 'label' => 'work']],
                'contact_numbers'  => $lead->person->contact_numbers ?: [['value' => '', 'label' => 'work']],
                'organization_id'  => $lead->person->organization_id,
                'organization'     => $lead->person->organization,
                'address'          => $lead->person->address,
                'website'          => $lead->person->website ?? '',
            ]
            : [
                'id'              => null,
                'name'            => '',
                'emails'          => [['value' => '', 'label' => 'work']],
                'contact_numbers' => [['value' => '', 'label' => 'work']],
                'organization_id' => null,
                'organization'    => null,
                'address'         => null,
                'website'         => '',
            ];

        $data['stages'] = $lead->pipeline
            ? $lead->pipeline->stages->map(fn ($stage) => [
                'id'   => $stage->id,
                'name' => $stage->name,
            ])->values()->all()
            : [];

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Display a resource.
     */
    public function view(int $id)
    {
        $lead = $this->leadRepository->findOrFail($id);

        if (! $this->sourceAccessService->canAccessLead($lead)) {
            return redirect()->route('admin.leads.index');
        }

        if ($this->isSdrUser()) {
            $lead = $this->claimNewLeadForSdr($lead);
        }

        $userIds = bouncer()->getAuthorizedUserIds();

        if (
            $userIds
            && ! in_array($lead->user_id, $userIds)
        ) {
            return redirect()->route('admin.leads.index');
        }



        $lead->load('tags');

        return view('admin::leads.view', compact('lead'));
    }

    protected function isSdrUser(): bool
    {
        return strtolower((string) auth()->guard('user')->user()?->role?->name) === 'sdr';
    }

    /**
     * First SDR to open a New lead claims it and moves it into Follow Up.
     */
    protected function claimNewLeadForSdr($lead)
    {
        return DB::transaction(function () use ($lead) {
            $lead = Lead::query()
                ->with(['pipeline.stages', 'stage'])
                ->lockForUpdate()
                ->findOrFail($lead->id);

            if (($lead->stage?->code ?? null) !== 'new') {
                return $lead;
            }

            $followUpStage = $lead->pipeline?->stages
                ->firstWhere('code', 'follow-up');

            if (! $followUpStage) {
                return $lead;
            }

            Event::dispatch('lead.update.before', $lead->id);

            $payload = [
                'entity_type'            => 'leads',
                'user_id'                => auth()->guard('user')->id(),
                'lead_pipeline_stage_id' => $followUpStage->id,
            ];

            $attributes = ['user_id', 'lead_pipeline_stage_id'];

            if (empty($lead->next_followup_date)) {
                $payload['next_followup_date'] = $this->followupScheduleService->calculateNext(
                    $lead,
                    Carbon::now(),
                    (int) ($lead->followup_count ?? 0)
                );

                $attributes[] = 'next_followup_date';
            }

            $lead = $this->leadRepository->update($payload, $lead->id, $attributes);

            Event::dispatch('lead.update.after', $lead);

            return $lead->fresh(['pipeline.stages', 'stage']);
        });
    }

    /**
     * Auto-assign Warm/Cold Lead tag from the lead source.
     * Non-Cold Call sources get Warm Lead; Cold Call gets Cold Lead.
     * Does not change the source when tags change.
     */
    protected function syncSourceTagForLead($lead): void
    {
        $sourceName = DB::table('lead_sources')
            ->where('id', $lead->lead_source_id)
            ->value('name');

        if (! $sourceName) {
            return;
        }

        $isColdCall = $sourceName === 'Cold Call';
        $tagName = $isColdCall ? 'Cold Lead' : 'Warm Lead';
        $oppositeTagName = $isColdCall ? 'Warm Lead' : 'Cold Lead';

        $tag = $this->findSourceTag($tagName);
        $oppositeTag = $this->findSourceTag($oppositeTagName);

        if (! $tag) {
            return;
        }

        if ($oppositeTag) {
            $lead->tags()->detach($oppositeTag->id);
        }

        $lead->tags()->syncWithoutDetaching([$tag->id]);
    }

    protected function findSourceTag(string $name)
    {
        return $this->tagRepository
            ->getModel()
            ->newQuery()
            ->where('name', $name)
            ->first();
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

        $this->syncSourceTagForLead($lead);

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

        if (array_key_exists('lead_source_id', $data)) {
            $this->syncSourceTagForLead($lead);
        }

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

        return DB::transaction(function () use ($id) {
            $lead = Lead::query()
                ->with(['pipeline.stages', 'stage'])
                ->lockForUpdate()
                ->findOrFail($id);

            if (! $this->sourceAccessService->canAccessLead($lead)) {
                return response()->json([
                    'message' => trans('admin::app.leads.source-access-denied'),
                ], 403);
            }

            $isSharedNewSdrLead = $this->isSdrUser()
                && ($lead->stage?->code ?? null) === 'new';

            $userIds = bouncer()->getAuthorizedUserIds();

            if (
                $userIds
                && ! in_array($lead->user_id, $userIds)
                && ! $isSharedNewSdrLead
            ) {
                return response()->json([
                    'message' => trans('admin::app.leads.source-access-denied'),
                ], 403);
            }

            $stage = $lead->pipeline->stages()
                ->where('id', request()->input('lead_pipeline_stage_id'))
                ->firstOrFail();

            if ($response = $this->validateMeetingStageMove($lead, $stage)) {
                return $response;
            }

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

            $attributes = ['lead_pipeline_stage_id'];

            if ($isSharedNewSdrLead) {
                $payload['user_id'] = auth()->guard('user')->id();
                $attributes[] = 'user_id';
            }

            $lead = $this->leadRepository->update($payload, $id, $attributes);

            if ($stage->code === 'follow-up' && request()->filled('followup_mode')) {
                $mode = request()->input('followup_mode');

                if ($mode === 'custom') {
                    $this->validate(request(), [
                        'next_followup_date' => ['required', 'date', 'after:now'],
                    ]);

                    $this->followupScheduleService->applyNextFollowup(
                        $lead,
                        Carbon::parse(request()->input('next_followup_date'))
                    );
                } elseif ($mode === 'auto') {
                    $this->followupScheduleService->applyNextFollowup($lead, null, true);
                }

                $lead = $lead->refresh();
            }

            Event::dispatch('lead.update.after', $lead);

            return response()->json([
                'message' => trans('admin::app.leads.update-success'),
            ]);
        });
    }

    /**
     * Enforce meeting activity requirements around the Meeting pipeline stage.
     */
    protected function validateMeetingStageMove($lead, $targetStage): ?JsonResponse
    {
        $currentStage = $lead->stage;
        $meetingStage = $lead->pipeline->stages()
            ->where('code', 'meeting')
            ->first();

        if (! $meetingStage || ! $currentStage) {
            return null;
        }

        $hasMeetingActivity = $lead->activities()
            ->where('type', 'meeting')
            ->exists();

        if ($targetStage->code === 'meeting' && ! $hasMeetingActivity) {
            return response()->json([
                'message'                    => trans('admin::app.leads.meeting-stage-requires-activity'),
                'requires_meeting_activity'  => true,
            ], 422);
        }

        $movingBeyondMeeting = $targetStage->sort_order > $meetingStage->sort_order;

        if (! $movingBeyondMeeting) {
            return null;
        }

        $hasCompletedMeeting = $lead->activities()
            ->where('type', 'meeting')
            ->where('is_done', 1)
            ->exists();

        if (! $hasCompletedMeeting) {
            return response()->json([
                'message' => trans('admin::app.leads.meeting-stage-requires-done-activity'),
            ], 422);
        }

        return null;
    }

    /**
     * Mark a lead as removed from SDR calling queues.
     */
    public function disqualify(int $id): RedirectResponse
    {
        $data = request()->validate([
            'reason'  => ['required', 'in:do_not_call,incorrect_info'],
            'comment' => ['required', 'string', 'max:1000'],
        ]);

        $lead = $this->leadRepository->findOrFail($id);

        $userIds = bouncer()->getAuthorizedUserIds();

        if ($userIds && ! in_array($lead->user_id, $userIds)) {
            return redirect()->route('admin.leads.index');
        }

        if (! $this->sourceAccessService->canAccessLead($lead)) {
            return redirect()->route('admin.leads.index');
        }

        Event::dispatch('lead.update.before', $id);

        $lead = $this->leadRepository->update([
            'entity_type'                  => 'leads',
            'lead_disqualification_reason' => $data['reason'],
            'lead_disqualification_comment'=> trim($data['comment']),
            'lead_disqualified_at'         => Carbon::now(),
            'next_followup_date'           => null,
            'followup_notes'               => trim((string) $lead->followup_notes) ?: trim($data['comment']),
        ], $id);

        Event::dispatch('lead.update.after', $lead);

        session()->flash('success', trans('admin::app.leads.disqualified-success', [
            'reason' => $this->disqualificationLabels()[$data['reason']],
        ]));

        if ($this->sourceAccessService->isAdmin()) {
            return redirect()->back();
        }

        return redirect()->route('admin.leads.index');
    }

    /**
     * Restore a disqualified lead to SDR visibility.
     */
    public function restoreDisqualified(int $id): RedirectResponse
    {
        if (! $this->sourceAccessService->isAdmin()) {
            return redirect()->route('admin.leads.index');
        }

        Event::dispatch('lead.update.before', $id);

        $lead = $this->leadRepository->update([
            'entity_type'                  => 'leads',
            'lead_disqualification_reason' => null,
            'lead_disqualification_comment'=> null,
            'lead_disqualified_at'         => null,
        ], $id);

        Event::dispatch('lead.update.after', $lead);

        session()->flash('success', trans('admin::app.leads.restored-success'));

        return redirect()->back();
    }

    /**
     * Correct an incorrect-info lead and assign it back to a user.
     */
    public function reassignIncorrectInfo(int $id): RedirectResponse
    {
        return $this->reassignDisqualifiedLead($id, 'incorrect_info');
    }

    /**
     * Reassign an ended lead back to a user.
     */
    public function reassignEndedLead(int $id): RedirectResponse
    {
        return $this->reassignDisqualifiedLead($id, 'ended');
    }

    /**
     * Reassign an admin-review lead back to SDR visibility.
     */
    protected function reassignDisqualifiedLead(int $id, string $expectedReason): RedirectResponse
    {
        if (! $this->sourceAccessService->isAdmin()) {
            return redirect()->route('admin.leads.index');
        }

        $data = request()->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $lead = $this->leadRepository->findOrFail($id);

        if ($lead->lead_disqualification_reason !== $expectedReason) {
            session()->flash('error', trans('admin::app.leads.disqualification.reassign-invalid'));

            return redirect()->route('admin.leads.disqualified');
        }

        Event::dispatch('lead.update.before', $id);

        $lead = $this->leadRepository->update([
            'entity_type'                    => 'leads',
            'user_id'                        => $data['user_id'],
            'lead_disqualification_reason'   => null,
            'lead_disqualification_comment'  => null,
            'lead_disqualified_at'           => null,
        ], $id, [
            'user_id',
            'lead_disqualification_reason',
            'lead_disqualification_comment',
            'lead_disqualified_at',
        ]);

        Event::dispatch('lead.update.after', $lead);

        session()->flash('success', trans('admin::app.leads.disqualification.reassigned-success'));

        return redirect()->route('admin.leads.disqualified');
    }

    /**
     * Disqualification labels.
     */
    protected function disqualificationLabels(): array
    {
        return [
            'do_not_call'    => trans('admin::app.leads.disqualification.do-not-call'),
            'incorrect_info' => trans('admin::app.leads.disqualification.incorrect-info'),
            'ended'          => trans('admin::app.leads.disqualification.ended'),
        ];
    }

    /**
     * Search person results.
     */
    public function search(): AnonymousResourceCollection
    {
        $userIds = bouncer()->getAuthorizedUserIds();
        $limit = min(max((int) request('limit', 20), 1), 50);

        $results = $this->leadRepository
            ->with([
                'tags.user',
                'type',
                'source',
                'subSource',
                'user',
                'person.organization',
                'pipeline.stages',
                'stage',
            ])
            ->pushCriteria(app(RequestCriteria::class))
            ->scopeQuery(function ($query) use ($userIds, $limit) {
                if ($userIds) {
                    $query->whereIn('user_id', $userIds);
                }

                return $this->sourceAccessService->applyLeadQueryScope($query)
                    ->limit($limit);
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
                'index'                 => 'lead_disqualification_reason',
                'label'                 => trans('admin::app.leads.index.datagrid.disqualification'),
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => 'dropdown',
                'filterable_options'    => [
                    ['label' => trans('admin::app.leads.disqualification.do-not-call'), 'value' => 'do_not_call'],
                    ['label' => trans('admin::app.leads.disqualification.incorrect-info'), 'value' => 'incorrect_info'],
                ],
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

            $this->syncSourceTagForLead($lead);

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

    protected function requiredImportColumns(): array
    {
        return [
            'title',
            'lead_value',
            'source',
            'type',
            'pricing_type',
        ];
    }

    protected function importColumnAliases(): array
    {
        return [
            'lead_title'         => 'title',
            'value'              => 'lead_value',
            'amount'             => 'lead_value',
            'lead_source'        => 'source',
            'lead_type'          => 'type',
            'owner'              => 'sales_owner_email',
            'sales_owner'        => 'sales_owner_email',
            'owner_email'        => 'sales_owner_email',
            'company_name'       => 'company',
            'organization'       => 'company',
            'organization_name'  => 'company',
            'contact_name'       => 'person_name',
            'person'             => 'person_name',
            'contact_email'      => 'email',
            'contact_phone'      => 'phone',
            'phone_number'       => 'phone',
            'expected_close'     => 'expected_close_date',
            'followup'           => 'next_followup_date',
            'next_follow_up'     => 'next_followup_date',
            'next_followup'      => 'next_followup_date',
            'follow_up_enabled'  => 'schedule_followup',
            'subsource'          => 'sub_source',
            'lead_sub_source'    => 'sub_source',
            'source_subtype'     => 'source_sub_type',
            'tag'                => 'tags',
        ];
    }

    protected function normalizeImportHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $index => $header) {
            $column = $this->normalizeImportColumnName($header);

            if (! $column) {
                continue;
            }

            $normalized[$this->importColumnAliases()[$column] ?? $column] = $index;
        }

        return $normalized;
    }

    protected function normalizeImportColumnName($header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
        $header = strtolower(trim(str_replace('*', '', $header)));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header);

        return trim($header, '_');
    }

    protected function isEmptyImportRow(array $row): bool
    {
        return ! collect($row)->contains(fn ($value) => filled(trim((string) $value)));
    }

    protected function mapImportRow(array $headers, array $row): array
    {
        $data = [];

        foreach ($headers as $column => $index) {
            $value = $row[$index] ?? null;
            $data[$column] = is_string($value) ? trim($value) : $value;
        }

        return $data;
    }

    protected function validateImportRow(array $row): array
    {
        $errors = [];

        foreach ($this->requiredImportColumns() as $column) {
            if (! filled($row[$column] ?? null)) {
                $errors[] = $column.' is required.';
            }
        }

        if (filled($row['lead_value'] ?? null) && ! is_numeric($row['lead_value'])) {
            $errors[] = 'lead_value must be numeric.';
        }

        if (filled($row['email'] ?? null) && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'email must be a valid email address.';
        }

        foreach (['source' => 'lead_sources', 'type' => 'lead_types'] as $column => $table) {
            if (filled($row[$column] ?? null) && ! $this->resolveImportId($table, $row[$column])) {
                $errors[] = $column.' "'.$row[$column].'" was not found.';
            }
        }

        if (filled($row['pricing_type'] ?? null) && ! $this->resolveAttributeOptionId('pricing_type', $row['pricing_type'])) {
            $errors[] = 'pricing_type "'.$row['pricing_type'].'" was not found.';
        }

        if (filled($row['sub_source'] ?? null) && ! $this->resolveImportId('lead_sources', $row['sub_source'])) {
            $errors[] = 'sub_source "'.$row['sub_source'].'" was not found.';
        }

        if (filled($row['sales_owner_email'] ?? null) && ! $this->resolveUserId($row['sales_owner_email'])) {
            $errors[] = 'sales_owner_email "'.$row['sales_owner_email'].'" was not found.';
        }

        if (filled($row['pipeline'] ?? null) && ! $this->resolveImportId('lead_pipelines', $row['pipeline'])) {
            $errors[] = 'pipeline "'.$row['pipeline'].'" was not found.';
        }

        return $errors;
    }

    protected function prepareImportedLeadData(array $row): array
    {
        $pipeline = filled($row['pipeline'] ?? null)
            ? $this->pipelineRepository->find($this->resolveImportId('lead_pipelines', $row['pipeline']))
            : $this->pipelineRepository->getDefaultPipeline();

        $stage = filled($row['stage'] ?? null)
            ? $pipeline->stages()
                ->where(function ($query) use ($row) {
                    $query
                        ->whereRaw('LOWER(name) = ?', [strtolower(trim($row['stage']))])
                        ->orWhereRaw('LOWER(code) = ?', [strtolower(trim($row['stage']))]);
                })
                ->first()
            : $pipeline->stages()->where('code', 'new')->first();

        $stage ??= $pipeline->stages()->first();

        if (! $stage) {
            throw new \InvalidArgumentException('No stage was found for the selected pipeline.');
        }

        $nextFollowupDate = $this->formatImportDateTime($row['next_followup_date'] ?? null);
        $scheduleFollowup = $nextFollowupDate
            ? true
            : $this->booleanImportValue($row['schedule_followup'] ?? null, true);

        return [
            'entity_type'              => 'leads',
            'title'                    => trim($row['title']),
            'description'              => $this->nullableImportValue($row['description'] ?? null),
            'lead_value'               => (float) $row['lead_value'],
            'lead_source_id'           => $this->resolveImportId('lead_sources', $row['source']),
            'lead_sub_source_id'       => filled($row['sub_source'] ?? null)
                ? $this->resolveImportId('lead_sources', $row['sub_source'])
                : null,
            'lead_type_id'             => $this->resolveImportId('lead_types', $row['type']),
            'pricing_type'             => $this->resolveAttributeOptionId('pricing_type', $row['pricing_type']),
            'source_sub_type'          => $this->nullableImportValue($row['source_sub_type'] ?? null),
            'source_link'              => $this->nullableImportValue($row['source_link'] ?? null),
            'user_id'                  => filled($row['sales_owner_email'] ?? null)
                ? $this->resolveUserId($row['sales_owner_email'])
                : null,
            'lead_pipeline_id'         => $pipeline->id,
            'lead_pipeline_stage_id'   => $stage->id,
            'status'                   => 1,
            'expected_close_date'      => $this->formatImportDate($row['expected_close_date'] ?? null),
            'schedule_followup'        => $scheduleFollowup,
            'next_followup_date'       => $nextFollowupDate,
            'person'                   => [
                'name'            => $this->nullableImportValue($row['person_name'] ?? null),
                'organization_name'=> $this->nullableImportValue($row['company'] ?? null),
                'emails'          => filled($row['email'] ?? null)
                    ? [['value' => trim($row['email']), 'label' => 'work']]
                    : [],
                'contact_numbers' => filled($row['phone'] ?? null)
                    ? [['value' => trim((string) $row['phone']), 'label' => 'work']]
                    : [],
            ],
        ];
    }

    protected function resolveImportId(string $table, $value): ?int
    {
        if (! filled($value)) {
            return null;
        }

        if (is_numeric($value) && DB::table($table)->where('id', (int) $value)->exists()) {
            return (int) $value;
        }

        return DB::table($table)
            ->whereRaw('LOWER(name) = ?', [strtolower(trim((string) $value))])
            ->value('id');
    }

    protected function resolveAttributeOptionId(string $attributeCode, $value): ?int
    {
        if (! filled($value)) {
            return null;
        }

        $attributeId = DB::table('attributes')
            ->where('entity_type', 'leads')
            ->where('code', $attributeCode)
            ->value('id');

        if (! $attributeId) {
            return null;
        }

        if (is_numeric($value) && DB::table('attribute_options')->where('id', (int) $value)->where('attribute_id', $attributeId)->exists()) {
            return (int) $value;
        }

        $normalizedValue = strtolower(trim((string) $value));

        $aliases = [
            'fixed'  => 'fixed price',
            'hourly' => 'hourly rate',
        ];

        $normalizedValue = $aliases[$normalizedValue] ?? $normalizedValue;

        return DB::table('attribute_options')
            ->where('attribute_id', $attributeId)
            ->whereRaw('LOWER(name) = ?', [$normalizedValue])
            ->value('id');
    }

    protected function resolveUserId($value): ?int
    {
        if (! filled($value)) {
            return null;
        }

        if (is_numeric($value) && DB::table('users')->where('id', (int) $value)->exists()) {
            return (int) $value;
        }

        $value = strtolower(trim((string) $value));

        return DB::table('users')
            ->whereRaw('LOWER(email) = ?', [$value])
            ->orWhereRaw('LOWER(name) = ?', [$value])
            ->value('id');
    }

    protected function tagsFromImportRow(array $row): array
    {
        return collect(preg_split('/[,;|]/', (string) ($row['tags'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->values()
            ->all();
    }

    protected function nullableImportValue($value)
    {
        return filled($value) ? trim((string) $value) : null;
    }

    protected function booleanImportValue($value, bool $default = false): bool
    {
        if (! filled($value)) {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'y', 'true', 'on'], true);
    }

    protected function formatImportDate($value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return $this->parseImportDate($value)->toDateString();
    }

    protected function formatImportDateTime($value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return $this->parseImportDate($value)->format('Y-m-d H:i:s');
    }

    protected function parseImportDate($value): Carbon
    {
        if (is_numeric($value)) {
            return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value));
        }

        return Carbon::parse($value);
    }

    protected function importResponse(int $created, array $errors = [], int $status = 200): RedirectResponse|JsonResponse
    {
        $message = $created.' lead'.($created === 1 ? '' : 's').' imported.';

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'message' => $message,
                'created' => $created,
                'errors'  => $errors,
            ], $status);
        }

        session()->flash($errors ? ($created ? 'warning' : 'error') : 'success', $errors
            ? $message.' '.count($errors).' row'.(count($errors) === 1 ? '' : 's').' failed. '.implode(' ', array_slice($errors, 0, 5))
            : $message);

        return redirect()->route('admin.leads.index');
    }

    protected function pendingImportPath(string $token): string
    {
        $safeToken = preg_replace('/[^a-zA-Z0-9-]/', '', $token);

        return storage_path('app/imports/pending/'.$safeToken.'.json');
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
            'close_followup'         => ['nullable', 'boolean'],
            'next_followup_date'     => ['required_if:schedule_next_followup,1', 'nullable', 'date', 'after:now'],
        ]);

        $result = DB::transaction(function () use ($id, $data) {
            $lead = $this->leadRepository
                ->getModel()
                ->newQuery()
                ->lockForUpdate()
                ->findOrFail($id);

            if (! $lead->next_followup_date) {
                return ['lead' => $lead, 'closed' => false, 'exhausted' => false];
            }

            if (! Carbon::parse($lead->next_followup_date)->equalTo(Carbon::parse($data['current_followup_date']))) {
                return ['lead' => $lead, 'closed' => false, 'exhausted' => false];
            }

            Event::dispatch('lead.update.before', $id);

            $completedAt = Carbon::now();
            $closeFollowup = request()->boolean('close_followup');
            $manualNext = ! $closeFollowup && request()->boolean('schedule_next_followup')
                ? Carbon::parse($data['next_followup_date'])
                : null;

            $lead->newQuery()
                ->whereKey($lead->getKey())
                ->update([
                    'followup_count'     => ($lead->followup_count ?? 0) + 1,
                    'last_followup_date' => $completedAt,
                ]);

            $lead->refresh();

            $this->followupScheduleService->applyNextFollowup($lead, $manualNext, ! $closeFollowup);

            $lead->refresh();

            $exhausted = ! $closeFollowup
                && ! $manualNext
                && is_null($lead->next_followup_date)
                && optional($lead->loadMissing('stage')->stage)->code === 'lost';

            Event::dispatch('lead.update.after', $lead);

            return ['lead' => $lead, 'closed' => $closeFollowup, 'exhausted' => $exhausted];
        });

        if ($result['closed']) {
            session()->flash('success', trans('admin::app.leads.followup-closed-success'));
        } elseif ($result['exhausted']) {
            session()->flash('warning', trans('admin::app.leads.followup-schedule-ended'));
        } else {
            session()->flash('success', trans('admin::app.leads.followup-complete-success'));
        }

        return redirect()->back();
    }
}
