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
use Illuminate\Validation\Rule;
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
use Webkul\Contact\Support\ContactPhoneCollection;
use Webkul\Lead\Helpers\MagicAI;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\ProductRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\StageRepository;
use Webkul\Lead\Repositories\TypeRepository;
use Webkul\Lead\Repositories\ServiceRepository;
use Webkul\Lead\Services\FollowupScheduleService;
use Webkul\Lead\Services\LeadForwardService;
use Webkul\Lead\Services\LinkedInProfileAccessService;
use Webkul\Lead\Services\LinkedInUrlNormalizer;
use Webkul\Lead\Services\MagicAIService;
use Webkul\Lead\Services\MeetingHandoffService;
use Webkul\Lead\Services\SourceAccessService;
use Webkul\Lead\Services\UsStateTimezoneService;
use Webkul\Tag\StaticTags;
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
        protected ServiceRepository $serviceRepository,
        protected PipelineRepository $pipelineRepository,
        protected StageRepository $stageRepository,
        protected LeadRepository $leadRepository,
        protected ProductRepository $productRepository,
        protected PersonRepository $personRepository,
        protected OrganizationRepository $organizationRepository,
        protected TeamRepository $teamRepository,
        protected TagRepository $tagRepository,
        protected SourceAccessService $sourceAccessService,
        protected LinkedInProfileAccessService $linkedInProfileAccessService,
        protected FollowupScheduleService $followupScheduleService,
        protected MeetingHandoffService $meetingHandoffService,
        protected LeadForwardService $leadForwardService,
        protected UsStateTimezoneService $usStateTimezoneService,
    ) {
        request()->request->add(['entity_type' => 'leads']);
    }

    /**
     * Main leads list.
     */
    public function index()
    {
        return $this->listIndex('main');
    }

    /**
     * SDR leads list.
     */
    public function sdr()
    {
        return $this->listIndex('sdr');
    }

    /**
     * LGE leads list.
     */
    public function lge()
    {
        return $this->listIndex('lge');
    }

    /**
     * Lead Clouser leads list.
     */
    public function leadClouser()
    {
        return $this->listIndex('lead_clouser');
    }

    /**
     * Shared list page for main and SDR lead screens.
     */
    protected function listIndex(string $leadVariant)
    {
        $this->shareLeadVariant($leadVariant);

        $leadsIndexRoute = lead_route_name('index', $leadVariant);

        if (request()->ajax()) {
            return datagrid(LeadDataGrid::class)->process();
        }

        if (! request()->has('view_type')) {
            return redirect()->route($leadsIndexRoute, array_merge(request()->query(), [
                'view_type' => 'table',
            ]));
        }

        if (request('pipeline_id')) {
            $pipeline = $this->pipelineRepository->find(request('pipeline_id'));
        } else {
            $pipeline = $this->pipelineRepository->getDefaultPipeline();
        }

        return view('admin::leads.index', [
            'pipeline'        => $pipeline,
            'columns'         => $this->getKanbanColumns(),
            'leadVariant'     => $leadVariant,
            'leadsIndexRoute' => $leadsIndexRoute,
            'meetingOwnerOptions' => [],
            'linkedInProfileFilterOptions' => $leadVariant === 'lge'
                ? $this->linkedInProfileAccessService->getFilterOptionsWithHistoricalLeads()
                : [],
        ]);
    }

    /**
     * Share lead variant helpers with all views for this request.
     */
    protected function shareLeadVariant(?string $variant = null): string
    {
        $leadVariant = $variant ?? lead_variant();

        $this->ensureLeadVariantMatchesCurrentUser($leadVariant);

        view()->share('leadVariant', $leadVariant);
        view()->share('leadsIndexRoute', lead_route_name('index', $leadVariant));

        return $leadVariant;
    }

    /**
     * Prevent users from opening another role's lead screen by URL.
     */
    protected function ensureLeadVariantMatchesCurrentUser(string $leadVariant): void
    {
        if ($leadVariant === 'main' || $this->sourceAccessService->isAdmin()) {
            return;
        }

        $allowed = match ($leadVariant) {
            'sdr'          => $this->sourceAccessService->isSdrUser(),
            'lge'          => $this->sourceAccessService->isLgeUser(),
            'lead_clouser' => $this->sourceAccessService->isLeadCloserUser(),
            default        => false,
        };

        abort_unless($allowed, 403, 'You are not allowed to access this lead screen.');
    }

    /**
     * Index route name for the active lead variant.
     */
    protected function leadsIndexRouteName(): string
    {
        return lead_route_name('index');
    }

    /**
     * Download lead import template.
     */
    public function importTemplate(): StreamedResponse
    {
        $headers = [
            'companies*',
            'lead_value*',
            'type*',
            'pricing_type*',
            'person_name',
            'email',
            'phone',
            'company',
            'address',
            'city',
            'state',
            'country',
            'postcode',
            'sales_owner_email',
            'pipeline',
            'stage',
            'expected_close_date',
            'schedule_followup',
            'next_followup_date',
            'description',
            lead_variant() === 'lge' ? 'source_link*' : 'source_link',
            'source_sub_type',
            'tags',
        ];

        $sample = [
            'Sample Lead',
            '0',
            'Existing Business',
            'Fixed Price',
            'John Smith',
            'john@example.com',
            '+15551234567,+15557654321',
            'Sample Company',
            '123 Main St Suite 100',
            'San Jose',
            'CA',
            'US',
            '95120',
            'sdr@example.com',
            'Default Pipeline',
            'New',
            Carbon::now()->addDays(14)->toDateString(),
            'yes',
            Carbon::now()->addDay()->format('Y-m-d H:i:s'),
            'Imported lead description',
            'https://example.com/source',
            '',
            'priority',
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
     * Import leads from CSV/XLSX.
     */
    public function import(): RedirectResponse|JsonResponse
    {
        $data = request()->validate([
            'file'           => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'lead_source_id' => ['required', 'integer', 'exists:lead_sources,id'],
        ]);

        $assignment = $this->validatedBulkImportAssignment();

        if ($assignment instanceof JsonResponse || $assignment instanceof RedirectResponse) {
            return $assignment;
        }

        $batchLinkedInProfileId = $this->validatedLgeImportProfileId();

        if ($batchLinkedInProfileId instanceof JsonResponse || $batchLinkedInProfileId instanceof RedirectResponse) {
            return $batchLinkedInProfileId;
        }

        $importTagId = $this->validatedImportTagId();

        if ($importTagId instanceof JsonResponse || $importTagId instanceof RedirectResponse) {
            return $importTagId;
        }

        $sourceId = (int) $data['lead_source_id'];

        if (! $this->sourceAccessService->canUseLeadSourceSelection($sourceId)) {
            return $this->importResponse(0, [
                'You do not have access to the selected lead source.',
            ], 403);
        }

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

        $importableCount = 0;

        foreach ($rows as $row) {
            if (! $this->isEmptyImportRow($row)) {
                $importableCount++;
            }
        }

        if ($importableCount === 0) {
            return $this->importResponse(0, [
                'The import file has no importable rows.',
            ], 422);
        }

        if ($importableCount > $this->maxBulkLeadImportRows()) {
            return $this->importResponse(0, [
                'Bulk upload is limited to '.$this->maxBulkLeadImportRows().' leads per file. This file has '.$importableCount.' rows. Please split the file and try again.',
            ], 422);
        }

        $created = 0;
        $skipped = 0;
        $errors = [];
        $assignIndex = 0;
        $seenDuplicateKeys = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            if ($this->isEmptyImportRow($row)) {
                continue;
            }

            $rowData = $this->mapImportRow($headers, $row);
            $rowErrors = $this->validateImportRow($rowData, ! empty($assignment['assignee_user_ids']), $batchLinkedInProfileId);

            if (! empty($rowErrors)) {
                $errors[] = 'Row '.$rowNumber.': '.implode(' ', $rowErrors);

                continue;
            }

            $duplicateMessage = $this->importDuplicateSkipMessage($rowData, $seenDuplicateKeys);

            if ($duplicateMessage) {
                $skipped++;

                continue;
            }

            try {
                $lead = $this->createImportedLeadFromRow(
                    $rowData,
                    $sourceId,
                    $assignment,
                    $assignIndex,
                    $batchLinkedInProfileId,
                    $importTagId,
                );

                $created++;
                $assignIndex++;
            } catch (Throwable $exception) {
                $errors[] = 'Row '.$rowNumber.': '.$exception->getMessage();
            }
        }

        return $this->importResponse($created, $errors, $created || empty($errors) ? 200 : 422, $skipped);
    }

    /**
     * Start an AJAX lead import and persist normalized rows for chunked processing.
     */
    public function importStart(): JsonResponse|RedirectResponse
    {
        $data = request()->validate([
            'file'           => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'lead_source_id' => ['required', 'integer', 'exists:lead_sources,id'],
        ]);

        $assignment = $this->validatedBulkImportAssignment();

        if ($assignment instanceof JsonResponse || $assignment instanceof RedirectResponse) {
            return $assignment;
        }

        $batchLinkedInProfileId = $this->validatedLgeImportProfileId();

        if ($batchLinkedInProfileId instanceof JsonResponse || $batchLinkedInProfileId instanceof RedirectResponse) {
            return $batchLinkedInProfileId;
        }

        $importTagId = $this->validatedImportTagId();

        if ($importTagId instanceof JsonResponse || $importTagId instanceof RedirectResponse) {
            return $importTagId;
        }

        $sourceId = (int) $data['lead_source_id'];

        if (! $this->sourceAccessService->canUseLeadSourceSelection($sourceId)) {
            return response()->json([
                'message' => 'You do not have access to the selected lead source.',
            ], 403);
        }

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

        if (count($importRows) > $this->maxBulkLeadImportRows()) {
            return response()->json([
                'message' => 'Bulk upload is limited to '.$this->maxBulkLeadImportRows().' leads per file. This file has '.count($importRows).' rows. Please split the file and try again.',
            ], 422);
        }

        $token = (string) Str::uuid();
        $directory = storage_path('app/imports/pending');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->pendingImportPath($token), json_encode([
            'lead_source_id'             => $sourceId,
            'import_linkedin_profile_id' => $batchLinkedInProfileId,
            'import_tag_id'              => $importTagId,
            'assignee_user_ids'          => $assignment['assignee_user_ids'],
            'industry_id'               => $assignment['industry_id'],
            'rows'                      => $importRows,
            'created'              => 0,
            'skipped'              => 0,
            'assign_index'         => 0,
            'seen_duplicate_keys'  => [],
            'errors'               => [],
            'failed'               => [],
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
        $sourceId = (int) ($payload['lead_source_id'] ?? 0);
        $batchLinkedInProfileId = isset($payload['import_linkedin_profile_id'])
            ? (int) $payload['import_linkedin_profile_id']
            : null;
        $importTagId = (int) ($payload['import_tag_id'] ?? 0);
        $assigneeUserIds = $this->normalizeImportAssigneeIds($payload['assignee_user_ids'] ?? []);
        $industryId = $this->normalizeImportIndustryId($payload['industry_id'] ?? null);

        if (! $sourceId || ! $this->sourceAccessService->canUseLeadSourceSelection($sourceId)) {
            @unlink($path);

            return response()->json([
                'message' => 'Import session is missing a valid lead source. Please upload the file again.',
            ], 422);
        }

        if ($importTagId <= 0) {
            @unlink($path);

            return response()->json([
                'message' => 'Import session is missing a valid tag. Please upload the file again.',
            ], 422);
        }

        $rows = $payload['rows'] ?? [];
        $total = count($rows);
        $offset = (int) $data['offset'];
        $chunkSize = 1;
        $chunk = array_slice($rows, $offset, $chunkSize);

        if (! isset($payload['failed']) || ! is_array($payload['failed'])) {
            $payload['failed'] = [];
        }

        if (! isset($payload['seen_duplicate_keys']) || ! is_array($payload['seen_duplicate_keys'])) {
            $payload['seen_duplicate_keys'] = [];
        }

        if (! isset($payload['skipped'])) {
            $payload['skipped'] = 0;
        }

        if (! isset($payload['assign_index'])) {
            $payload['assign_index'] = 0;
        }

        $seenDuplicateKeys = $payload['seen_duplicate_keys'];

        foreach ($chunk as $row) {
            $rowData = $row['data'] ?? [];
            $rowErrors = $this->validateImportRow($rowData, ! empty($assigneeUserIds), $batchLinkedInProfileId);

            if (! empty($rowErrors)) {
                $errorMessage = implode(' ', $rowErrors);
                $payload['errors'][] = 'Row '.$row['row_number'].': '.$errorMessage;
                $payload['failed'][] = [
                    'row_number' => $row['row_number'],
                    'data'       => $rowData,
                    'error'      => $errorMessage,
                ];

                continue;
            }

            $duplicateMessage = $this->importDuplicateSkipMessage($rowData, $seenDuplicateKeys);

            if ($duplicateMessage) {
                $payload['skipped']++;

                continue;
            }

            try {
                $lead = $this->createImportedLeadFromRow(
                    $rowData,
                    $sourceId,
                    [
                        'assignee_user_ids' => $assigneeUserIds,
                        'industry_id'       => $industryId,
                    ],
                    (int) $payload['assign_index'],
                    $batchLinkedInProfileId,
                    $importTagId,
                );

                $payload['created']++;
                $payload['assign_index']++;
            } catch (Throwable $exception) {
                $payload['errors'][] = 'Row '.$row['row_number'].': '.$exception->getMessage();
                $payload['failed'][] = [
                    'row_number' => $row['row_number'],
                    'data'       => $rowData,
                    'error'      => $exception->getMessage(),
                ];
            }
        }

        $payload['seen_duplicate_keys'] = array_values($seenDuplicateKeys);

        $processed = min($offset + count($chunk), $total);
        $done = $processed >= $total;
        $failedRows = $payload['failed'] ?? [];

        if ($done) {
            @unlink($path);
        } else {
            file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
        }

        return response()->json([
            'processed'      => $processed,
            'total'          => $total,
            'created'        => $payload['created'],
            'skipped'        => $payload['skipped'],
            'errors'         => $payload['errors'],
            'failed_rows'    => $done ? array_values($failedRows) : [],
            'lead_source_id' => $sourceId,
            'done'           => $done,
            'message'        => $payload['created'].' lead'.($payload['created'] === 1 ? '' : 's').' imported.'
                .($payload['skipped'] ? ' '.$payload['skipped'].' duplicate'.($payload['skipped'] === 1 ? '' : 's').' skipped.' : ''),
        ]);
    }

    /**
     * Retry importing corrected failed rows.
     */
    public function importRetry(): JsonResponse|RedirectResponse
    {
        $data = request()->validate([
            'lead_source_id'    => ['required', 'integer', 'exists:lead_sources,id'],
            'rows'              => ['required', 'array', 'min:1'],
            'rows.*.row_number' => ['required', 'integer', 'min:1'],
            'rows.*.data'       => ['required', 'array'],
        ]);

        $assignment = $this->validatedBulkImportAssignment();

        if ($assignment instanceof JsonResponse || $assignment instanceof RedirectResponse) {
            return $assignment;
        }

        $batchLinkedInProfileId = $this->validatedLgeImportProfileId();

        if ($batchLinkedInProfileId instanceof JsonResponse || $batchLinkedInProfileId instanceof RedirectResponse) {
            return $batchLinkedInProfileId;
        }

        $importTagId = $this->validatedImportTagId();

        if ($importTagId instanceof JsonResponse || $importTagId instanceof RedirectResponse) {
            return $importTagId;
        }

        $sourceId = (int) $data['lead_source_id'];

        if (! $this->sourceAccessService->canUseLeadSourceSelection($sourceId)) {
            return response()->json([
                'message' => 'You do not have access to the selected lead source.',
            ], 403);
        }

        $created = 0;
        $skipped = 0;
        $failedRows = [];
        $assignIndex = 0;
        $seenDuplicateKeys = [];

        foreach ($data['rows'] as $row) {
            $rowNumber = (int) $row['row_number'];
            $rowData = $this->normalizeRetriedImportRow($row['data'] ?? []);
            $rowErrors = $this->validateImportRow($rowData, ! empty($assignment['assignee_user_ids']), $batchLinkedInProfileId);

            if (! empty($rowErrors)) {
                $failedRows[] = [
                    'row_number' => $rowNumber,
                    'data'       => $rowData,
                    'error'      => implode(' ', $rowErrors),
                ];

                continue;
            }

            $duplicateMessage = $this->importDuplicateSkipMessage($rowData, $seenDuplicateKeys);

            if ($duplicateMessage) {
                $skipped++;

                continue;
            }

            try {
                $lead = $this->createImportedLeadFromRow(
                    $rowData,
                    $sourceId,
                    $assignment,
                    $assignIndex,
                    $batchLinkedInProfileId,
                    $importTagId,
                );

                $created++;
                $assignIndex++;
            } catch (Throwable $exception) {
                $failedRows[] = [
                    'row_number' => $rowNumber,
                    'data'       => $rowData,
                    'error'      => $exception->getMessage(),
                ];
            }
        }

        return response()->json([
            'created'        => $created,
            'skipped'        => $skipped,
            'failed_rows'    => $failedRows,
            'lead_source_id' => $sourceId,
            'message'        => $created.' lead'.($created === 1 ? '' : 's').' imported from retry.'
                .($skipped ? ' '.$skipped.' duplicate'.($skipped === 1 ? '' : 's').' skipped.' : ''),
        ], empty($failedRows) ? 200 : 422);
    }

    /**
     * Normalize editable retry payload values.
     */
    protected function normalizeRetriedImportRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $column = $this->normalizeImportColumnName((string) $key);

            if (! $column) {
                continue;
            }

            $column = $this->importColumnAliases()[$column] ?? $column;
            $normalized[$column] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }

    /**
     * Display DNC and incorrect-info leads.
     */
    public function disqualified(): View|RedirectResponse
    {
        $this->shareLeadVariant();

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

        $stages = $this->sourceAccessService->getVisibleStagesForLeadListing($stages, (int) $pipeline->id);

        // Get sort parameters (default: newest first)
        $sortBy = request()->query('sort_by', 'created_at');
        $sortOrder = request()->query('sort_order', 'desc');

        $data = [];

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

            $this->sourceAccessService->applyLeadOwnerVisibilityScope($query);
            $this->sourceAccessService->applyLeadQueryScope($query);

            $this->addForwardedOriginFlagForKanban($query);

            $this->applyKanbanSearch($query, request()->query('lead_search'));

            $this->applyWarmLeadPriority($query);

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

    protected function addForwardedOriginFlagForKanban(mixed $query): void
    {
        if (! in_array(lead_variant(), ['sdr', 'lge'], true)) {
            return;
        }

        $userId = (int) auth()->guard('user')->id();

        if (! $userId) {
            return;
        }

        $addForwardedSelect = function ($builder) use ($userId) {
            return $builder->addSelect([
                'forwarded_from_current_user' => DB::table('lead_forwards')
                    ->selectRaw('1')
                    ->whereColumn('lead_forwards.lead_id', 'leads.id')
                    ->where('lead_forwards.from_user_id', $userId)
                    ->limit(1),
            ]);
        };

        if (method_exists($query, 'scopeQuery')) {
            $query->scopeQuery($addForwardedSelect);

            return;
        }

        $addForwardedSelect($query);
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
                ->where('id', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('source_link', 'like', "%{$search}%")
                ->orWhere('lead_value', 'like', "%{$search}%")
                ->orWhereHas('person', function ($query) use ($search) {
                    $like = '%'.ContactPhoneCollection::escapeLike($search).'%';
                    $digits = ContactPhoneCollection::compareKey($search);

                    $query
                        ->where('name', 'like', $like)
                        ->orWhere('contact_numbers', 'like', $like)
                        ->when($digits && strlen($digits) >= 7 && $digits !== $search, function ($query) use ($digits) {
                            $query->orWhere('contact_numbers', 'like', '%'.ContactPhoneCollection::escapeLike($digits).'%');
                        })
                        ->orWhereHas('organization', function ($query) use ($search) {
                            $query->where('name', 'like', '%'.ContactPhoneCollection::escapeLike($search).'%');
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
     * Warm Lead tags / non-Cold Call sources first, then Cold Lead / Cold Call.
     */
    protected function applyWarmLeadPriority(mixed $query): void
    {
        $coldCallSourceId = DB::table('lead_sources')->where('name', 'Cold Call')->value('id');

        $query->orderByRaw(
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
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $this->shareLeadVariant();

        return view('admin::leads.create', [
            'linkedInProfiles' => lead_variant() === 'lge'
                ? $this->linkedInProfileAccessService->getAssignedProfiles()
                : collect(),
            'tagOptions'       => $this->leadCreateTagOptions(),
            'coldLeadTagId'    => $this->leadForwardService->coldLeadTagId(),
            'activeSdrUsers'   => lead_variant() === 'lge'
                ? $this->leadForwardService->activeSdrUsers()
                : collect(),
        ]);
    }

    public function checkLinkedInSourceLink(): JsonResponse
    {
        if (! bouncer()->hasPermission('lge_leads.create')) {
            abort(401);
        }

        $sourceLink = request()->query('source_link');
        $entry = $this->findLinkedInEntryBySourceLink($sourceLink);
        $profileName = null;

        if ($entry && $entry->linkedin_profile_id) {
            $profileName = DB::table('linkedin_profiles')
                ->where('id', $entry->linkedin_profile_id)
                ->value('name');
        }

        return response()->json([
            'exists'                     => (bool) $entry,
            'entry_id'                   => $entry->id ?? null,
            'linkedin_profile_id'        => $entry->linkedin_profile_id ?? null,
            'linkedin_profile_name'      => $profileName,
            'requires_profile_selection' => ! $entry || ! $entry->linkedin_profile_id,
        ]);
    }

    protected function leadCreateTagOptions()
    {
        $allowedNames = collect(StaticTags::names())
            ->map(fn ($name) => strtolower($name))
            ->all();

        return $this->tagRepository
            ->getModel()
            ->newQuery()
            ->whereIn(DB::raw('LOWER(TRIM(name))'), $allowedNames)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validatedColdLeadForwardSdrId(array $data): ?int
    {
        if (
            ! $this->sourceAccessService->isLgeUser()
            || ! $this->leadForwardService->isColdLeadTagSelected($data['tags'] ?? [])
        ) {
            return null;
        }

        return $this->leadForwardService->validateActiveSdrId(
            request()->input('cold_lead_sdr_user_id'),
            'cold_lead_sdr_user_id',
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LeadForm $request): RedirectResponse|JsonResponse
    {
        Event::dispatch('lead.create.before');

        $data = request()->all();

        $data['status'] = 1;

        // Normalize checkbox: unmarked must disable auto follow-up.
        if (array_key_exists('schedule_followup', $data)) {
            $data['schedule_followup'] = request()->boolean('schedule_followup');
        }

        if (! ($data['schedule_followup'] ?? true)) {
            $data['next_followup_date'] = null;
        }

        $isMainCreate = lead_variant() === 'main';
        $isLgeCreate = lead_variant() === 'lge';
        $isCallingRoleCreate = in_array(lead_variant(), ['lge', 'sdr'], true);

        if ($isLgeCreate) {
            try {
                $this->applyLinkedInProfileToLeadData($data);
                $coldLeadForwardSdrId = $this->validatedColdLeadForwardSdrId($data);
            } catch (\Illuminate\Validation\ValidationException $exception) {
                if (request()->ajax()) {
                    return response()->json([
                        'message' => collect($exception->errors())->flatten()->first(),
                        'errors'  => $exception->errors(),
                    ], 422);
                }

                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors($exception->errors());
            }
        } else {
            $coldLeadForwardSdrId = null;
        }

        unset($data['cold_lead_sdr_user_id']);

        // Main create: default Sales Owner to creator (editable to forward); always start in New stage.
        if ($isMainCreate || $isCallingRoleCreate) {
            if (empty($data['user_id'])) {
                $data['user_id'] = auth()->guard('user')->id();
            }

            if ($isCallingRoleCreate) {
                $data['user_id'] = auth()->guard('user')->id();
            }

            if ($isCallingRoleCreate && empty($data['lead_owner_id'])) {
                $data['lead_owner_id'] = auth()->guard('user')->id();
            }

            if (empty($data['lead_pipeline_id'])) {
                $pipeline = $this->pipelineRepository->getDefaultPipeline();
                $data['lead_pipeline_id'] = $pipeline->id;
            } else {
                $pipeline = $this->pipelineRepository->findOrFail($data['lead_pipeline_id']);
            }

            $newStage = $pipeline->stages()->where('code', 'new')->first();

            if ($newStage) {
                $data['lead_pipeline_stage_id'] = $newStage->id;
                $stage = $newStage;
            } else {
                $stage = $pipeline->stages()->first();
                $data['lead_pipeline_stage_id'] = $stage->id;
            }
        } elseif (! empty($data['lead_pipeline_stage_id'])) {
            $stage = $this->stageRepository->findOrFail($data['lead_pipeline_stage_id']);

            if (! $this->sourceAccessService->canAccessStageId((int) $stage->id)) {
                if (request()->ajax()) {
                    return response()->json([
                        'message' => trans('admin::app.leads.source-access-denied'),
                    ], 403);
                }

                session()->flash('error', trans('admin::app.leads.source-access-denied'));

                return redirect()->back();
            }

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

        $lead = DB::transaction(function () use ($data, $isLgeCreate, $coldLeadForwardSdrId) {
            $lead = $this->leadRepository->create($data);

            $this->syncLeadTags($lead, $data['tags'] ?? []);

            $this->syncLeadServices($lead, $data['services'] ?? []);

            $this->syncSourceTagForLead($lead);

            if ($isLgeCreate) {
                $this->backfillLinkedInEntryProfile($data['source_link'] ?? null, (int) ($data['linkedin_profile_id'] ?? 0));
                $this->markLinkedInSourceLinkAsResponse($data['source_link'] ?? null);
            }

            if ($coldLeadForwardSdrId) {
                $lead = $this->leadForwardService->forwardColdLeadToSdr(
                    $lead,
                    (int) auth()->guard('user')->id(),
                    $coldLeadForwardSdrId,
                );
            }

            return $lead;
        });

        if (request()->ajax()) {
            return response()->json([
                'message' => trans('admin::app.leads.create-success'),
                'data'    => new LeadResource($lead),
            ]);
        }

        Event::dispatch('lead.create.after', $lead);

        session()->flash('success', trans('admin::app.leads.create-success'));

        if (request()->input('redirect_to') === 'linkedin_entries') {
            $linkedinEntriesUrl = route('admin.linkedin_entries.index');

            // Break out of the LinkedIn Entries create-lead iframe/modal.
            if (request()->boolean('embed')) {
                return response(
                    '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Lead created</title></head><body>'
                    .'<script>window.top.location.href = '.json_encode($linkedinEntriesUrl).';</script>'
                    .'<p>Lead created. Redirecting...</p></body></html>',
                    200,
                    ['Content-Type' => 'text/html; charset=UTF-8']
                );
            }

            return redirect()->to($linkedinEntriesUrl);
        }

        if (! empty($data['lead_pipeline_id'])) {
            $params['pipeline_id'] = $data['lead_pipeline_id'];
        }

        return redirect()->route($this->leadsIndexRouteName(), $params ?? []);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View|RedirectResponse
    {
        $this->shareLeadVariant();

        $lead = $this->leadRepository->findOrFail($id);

        if (! $this->sourceAccessService->canViewLead($lead)) {
            return redirect()->route($this->leadsIndexRouteName());
        }

        if (! $this->sourceAccessService->canEditLead($lead)) {
            return redirect()->route(lead_route_name('view'), $lead->id);
        }

        $lead->load(['person.organization', 'products']);

        $person = $this->leadPersonFormPayload($lead->person);

        return view('admin::leads.edit', compact('lead', 'person'));
    }

    /**
     * Return lead form values for the table edit modal.
     */
    public function formData(int $id): JsonResponse|RedirectResponse
    {
        $lead = $this->leadRepository->findOrFail($id);

        if (! $this->sourceAccessService->canViewLead($lead)) {
            abort(403);
        }

        if (! $this->sourceAccessService->canEditLead($lead)) {
            abort(403);
        }

        $lead->load(['person.organization', 'organization', 'tags', 'pipeline.stages']);

        $data = $lead->attributesToArray();

        /**
         * Prefer native lead columns over stale EAV copies for system fields
         * (attributesToArray can overwrite FKs like lead_type_id with invalid option ids).
         */
        foreach ([
            'title',
            'description',
            'lead_value',
            'lead_source_id',
            'lead_type_id',
            'user_id',
            'lead_pipeline_id',
            'lead_pipeline_stage_id',
            'lead_sub_source_id',
            'organization_id',
            'expected_close_date',
            'next_followup_date',
            'last_followup_date',
            'closed_at',
            'lead_disqualified_at',
        ] as $column) {
            if (array_key_exists($column, $lead->getAttributes())) {
                $data[$column] = $lead->getAttributes()[$column];
            }
        }

        $organization = $lead->organization ?: $lead->person?->organization;

        $data['organization_id'] = $organization?->id
            ? (string) $organization->id
            : ($data['organization_id'] ? (string) $data['organization_id'] : null);

        // Lookup components expect {id, name} under the attribute code for edit values.
        $data['organization'] = $organization
            ? ['id' => $organization->id, 'name' => $organization->name]
            : null;

        // Prefer company as Title only when Title is empty (main create/edit uses a separate Title).
        if ($organization && empty($data['title'])) {
            $data['title'] = $organization->name;
        }

        unset($data['companies']);

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

        foreach (['lead_type_id', 'user_id', 'lead_pipeline_id', 'lead_pipeline_stage_id', 'lead_source_id', 'lead_sub_source_id', 'organization_id'] as $idField) {
            if (isset($data[$idField]) && $data[$idField] !== null && $data[$idField] !== '') {
                $data[$idField] = (string) $data[$idField];
            }
        }

        $data['entity_type'] = 'leads';
        $data['quick_add'] = 1;
        $data['tags'] = $lead->tags->pluck('name')->filter()->values()->all();
        $data['person'] = $this->leadPersonFormPayload($lead->person);

        $stages = $lead->pipeline
            ? $this->sourceAccessService->filterAccessibleStages($lead->pipeline->stages)
            : collect();

        if ($lead->pipeline && in_array(lead_variant(), ['sdr', 'lge'], true)) {
            $meetingStage = $lead->pipeline->stages->firstWhere('code', 'meeting');

            if ($meetingStage) {
                $stages = $stages
                    ->filter(fn ($stage) => (int) $stage->sort_order <= (int) $meetingStage->sort_order)
                    ->values();
            }
        }

        $data['stages'] = $stages
                ->map(fn ($stage) => [
                    'id'   => $stage->id,
                    'name' => $stage->name,
                ])->values()->all()
        ;

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Eligible meeting handoff owners for a specific lead (service-filtered).
     */
    public function eligibleMeetingOwners(int $id): JsonResponse
    {
        $lead = $this->leadRepository->findOrFail($id);

        if (! $this->sourceAccessService->canViewLead($lead)) {
            abort(403);
        }

        return response()->json([
            'data'               => $this->meetingHandoffService->getEligibleMeetingOwnersForLead($lead),
            'scheduling_context' => $this->leadSchedulingContext($lead),
        ]);
    }

    /**
     * Timezone context for follow-up and meeting scheduling modals.
     */
    public function schedulingContext(int $id): JsonResponse
    {
        $lead = $this->leadRepository->findOrFail($id);

        if (! $this->sourceAccessService->canViewLead($lead)) {
            abort(403);
        }

        return response()->json([
            'data' => $this->leadSchedulingContext($lead),
        ]);
    }

    /**
     * Build prospect timezone metadata without changing stored schedule values.
     */
    protected function leadSchedulingContext(Lead $lead): array
    {
        $lead->loadMissing('person');

        $person = $lead->person;
        $timezone = $this->usStateTimezoneService->timezoneFromPerson($person);
        $state = $person?->state;

        return [
            'customer_timezone' => $timezone,
            'customer_state'    => $state,
            'customer_city'     => $person?->city,
            'customer_country'  => $person?->country,
            'app_timezone'      => $this->usStateTimezoneService->appTimezone(),
            'pakistan_timezone' => 'Asia/Karachi',
        ];
    }

    /**
     * Display a resource.
     */
    public function view(int $id)
    {
        $this->shareLeadVariant();

        $lead = $this->leadRepository->findOrFail($id);

        if (! $this->sourceAccessService->canViewLead($lead)) {
            return redirect()->route($this->leadsIndexRouteName());
        }

        $lead->load(['tags', 'user', 'person']);

        return view('admin::leads.view', [
            'lead'            => $lead,
            'meetingOwnerOptions' => $this->meetingHandoffService->getEligibleMeetingOwnersForLead($lead),
            'schedulingContext' => $this->leadSchedulingContext($lead),
            'readOnlyForCurrentUser' => ! $this->sourceAccessService->canEditLead($lead),
        ]);
    }

    protected function isSdrUser(): bool
    {
        return $this->sourceAccessService->isSdrUser();
    }

    protected function linkedInSourceLinkExists(mixed $sourceLink): bool
    {
        return $this->findLinkedInEntryBySourceLink($sourceLink) !== null;
    }

    protected function findLinkedInEntryBySourceLink(mixed $sourceLink): ?object
    {
        $sourceLink = trim((string) $sourceLink);

        if ($sourceLink === '') {
            return null;
        }

        $url = $this->normalizeLinkedInSourceLink($sourceLink);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $normalizedUrl = $this->normalizeLinkedInSourceLinkForCompare($url);

        return DB::table('linkedin_entry')
            ->whereRaw(
                LinkedInUrlNormalizer::sqlCompareExpression('url').' = ?',
                [$normalizedUrl]
            )
            ->first(['id', 'user_id', 'linkedin_profile_id', 'url', 'status']);
    }

    /**
     * Resolve and validate LinkedIn working profile for LGE lead create/update payloads.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function applyLinkedInProfileToLeadData(array &$data, ?int $batchProfileId = null): void
    {
        $user = auth()->guard('user')->user();
        $ownerUserId = (int) ($user?->id ?? 0);
        $entry = $this->findLinkedInEntryBySourceLink($data['source_link'] ?? null);

        if ($entry && $entry->linkedin_profile_id) {
            $profileId = (int) $entry->linkedin_profile_id;
            $this->linkedInProfileAccessService->assertCanUseProfile($profileId, $user, $ownerUserId);

            if (! empty($data['linkedin_profile_id']) && (int) $data['linkedin_profile_id'] !== $profileId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'linkedin_profile_id' => ['LinkedIn working profile must match the existing LinkedIn Entry.'],
                ]);
            }

            $data['linkedin_profile_id'] = $profileId;

            return;
        }

        $profileId = (int) ($data['linkedin_profile_id'] ?? $batchProfileId ?? 0);
        $this->linkedInProfileAccessService->assertCanUseProfile($profileId, $user, $ownerUserId);
        $data['linkedin_profile_id'] = $profileId;
    }

    protected function backfillLinkedInEntryProfile(mixed $sourceLink, int $profileId): void
    {
        if ($profileId <= 0) {
            return;
        }

        $entry = $this->findLinkedInEntryBySourceLink($sourceLink);

        if (! $entry || $entry->linkedin_profile_id) {
            return;
        }

        DB::table('linkedin_entry')
            ->where('id', $entry->id)
            ->update([
                'linkedin_profile_id' => $profileId,
                'updated_at'          => now(),
            ]);
    }

    protected function resolveImportedLinkedInProfileId(array $row, ?int $batchProfileId = null): int
    {
        $entry = $this->findLinkedInEntryBySourceLink($row['source_link'] ?? null);

        if ($entry && $entry->linkedin_profile_id) {
            return (int) $entry->linkedin_profile_id;
        }

        return (int) ($batchProfileId ?? 0);
    }

    protected function markLinkedInSourceLinkAsResponse(mixed $sourceLink): void
    {
        $sourceLink = trim((string) $sourceLink);

        if ($sourceLink === '') {
            return;
        }

        $url = $this->normalizeLinkedInSourceLink($sourceLink);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        DB::table('linkedin_entry')
            ->whereRaw(
                LinkedInUrlNormalizer::sqlCompareExpression('url').' = ?',
                [$this->normalizeLinkedInSourceLinkForCompare($url)]
            )
            ->update([
                'status'     => 'response',
                'updated_at' => now(),
            ]);
    }

    protected function normalizeLinkedInSourceLink(string $url): string
    {
        $url = trim($url);

        if (! preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if (! $parts || empty($parts['host'])) {
            return $url;
        }

        $host = preg_replace('/^www\./i', '', strtolower($parts['host']));
        $path = strtolower($parts['path'] ?? '');
        $path = preg_replace('#/+#', '/', $path);
        $path = rtrim($path, '/');

        return 'https://'.$host.$path;
    }

    protected function normalizeLinkedInSourceLinkForCompare(string $url): string
    {
        $normalized = strtolower(trim($url));
        $normalized = preg_replace('/^https?:\/\//', '', $normalized);
        $normalized = preg_replace('/^www\./', '', $normalized);

        return rtrim($normalized, '/');
    }

    protected function isLgeUser(): bool
    {
        return lead_variant() === 'lge' || $this->sourceAccessService->isLgeUser();
    }

    protected function isCallingRoleUser(): bool
    {
        return $this->isSdrUser() || $this->isLgeUser();
    }

    /**
     * Build person + company payload for lead create/edit forms.
     */
    protected function leadPersonFormPayload($person): array
    {
        if (! $person) {
            return [
                'id'              => null,
                'name'            => '',
                'emails'          => [['value' => '', 'label' => 'work']],
                'contact_numbers' => [['value' => '', 'label' => 'work']],
                'organization_id' => null,
                'organization'    => null,
                'address'         => null,
                'website'         => '',
            ];
        }

        $organization = $person->organization;

        return [
            'id'              => $person->id,
            'name'            => $person->name,
            'emails'          => ! empty($person->emails)
                ? $person->emails
                : [['value' => '', 'label' => 'work']],
            'contact_numbers' => ! empty($person->contact_numbers)
                ? $person->contact_numbers
                : [['value' => '', 'label' => 'work']],
            'organization_id' => $person->organization_id,
            'organization'    => $organization
                ? [
                    'id'   => $organization->id,
                    'name' => $organization->name,
                ]
                : null,
            'address'         => $person->address,
            'website'         => $person->website ?? '',
        ];
    }

    /**
     * Lead fields that can be viewed but must not change after create.
     *
     * @return array<int, string>
     */
    protected function lockedLeadAttributeCodes(): array
    {
        return [
            'lead_source_id',
            'lead_type_id',
            'lead_sub_source_id',
            'industry',
            'linkedin_profile_id',
        ];
    }

    /**
     * Remove locked lead fields so existing values are preserved on update.
     */
    protected function stripLockedLeadFields(array $data): array
    {
        foreach ($this->lockedLeadAttributeCodes() as $code) {
            unset($data[$code]);
        }

        return $data;
    }

    /**
     * @deprecated Use lockedLeadAttributeCodes()
     *
     * @return array<int, string>
     */
    protected function sdrLockedLeadAttributeCodes(): array
    {
        return $this->lockedLeadAttributeCodes();
    }

    /**
     * @deprecated Use stripLockedLeadFields()
     */
    protected function stripSdrLockedLeadFields(array $data): array
    {
        return $this->stripLockedLeadFields($data);
    }

    /**
     * Auto-assign Warm/Cold Lead tag from the lead source.
     * Non-Cold Call sources get Warm Lead; Cold Call gets Cold Lead.
     * Does not change the source when tags change.
     */
    protected function syncSourceTagForLead($lead): void
    {
        if ($this->leadForwardService->leadHasClassification($lead)) {
            return;
        }

        $sourceName = DB::table('lead_sources')
            ->where('id', $lead->lead_source_id)
            ->value('name');

        if (! $sourceName) {
            return;
        }

        $isColdCall = strtolower(trim((string) $sourceName)) === 'cold call';
        $tagName = $isColdCall ? 'Cold Lead' : 'Warm Lead';

        $tag = $this->findSourceTag($tagName);

        if (! $tag) {
            return;
        }

        $this->leadForwardService->syncClassificationTag($lead, (int) $tag->id);
    }

    /**
     * Apply the batch-selected tag to an imported lead.
     */
    protected function syncImportTagForLead($lead, int $tagId): void
    {
        $tag = $this->tagRepository->find($tagId);

        if (! $tag) {
            return;
        }

        $normalizedName = strtolower(trim((string) $tag->name));

        if (in_array($normalizedName, ['warm lead', 'cold lead'], true)) {
            $this->leadForwardService->syncClassificationTag($lead, (int) $tag->id);

            return;
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
        $existingLead = $this->leadRepository->findOrFail($id);

        if (! $this->sourceAccessService->canEditLead($existingLead)) {
            if (request()->ajax()) {
                return response()->json([
                    'message' => trans('admin::app.leads.source-access-denied'),
                ], 403);
            }

            session()->flash('error', trans('admin::app.leads.source-access-denied'));

            return redirect()->back();
        }

        Event::dispatch('lead.update.before', $id);

        $data = $this->stripLockedLeadFields($request->all());

        if ($this->isSdrUser()) {
            unset($data['person'], $data['organization_id'], $data['organization_name']);
        }

        if (isset($data['lead_pipeline_stage_id'])) {
            $stage = $this->stageRepository->findOrFail($data['lead_pipeline_stage_id']);

            if (! $this->sourceAccessService->canAccessStageId((int) $stage->id)) {
                if (request()->ajax()) {
                    return response()->json([
                        'message' => trans('admin::app.leads.source-access-denied'),
                    ], 403);
                }

                session()->flash('error', trans('admin::app.leads.source-access-denied'));

                return redirect()->back();
            }

            $data['lead_pipeline_id'] = $stage->lead_pipeline_id;
        } else {
            $pipeline = $this->pipelineRepository->getDefaultPipeline();

            $stage = $pipeline->stages()->first();

            $data['lead_pipeline_id'] = $pipeline->id;

            $data['lead_pipeline_stage_id'] = $stage->id;
        }

        $lead = $this->leadRepository->update($data, $id);

        $this->syncLeadTags($lead, $data['tags'] ?? []);

        if (array_key_exists('services', $data)) {
            $this->syncLeadServices($lead, $data['services']);
        }

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
            return redirect()->route($this->leadsIndexRouteName(), $data['lead_pipeline_id']);
        }
    }

    /**
     * Create a new services offered option from lead forms.
     */
    public function storeServiceOfferedOption(): JsonResponse
    {
        $canCreate = bouncer()->hasPermission('settings.lead.services_offered.create')
            || bouncer()->hasPermission(lead_permission('create'))
            || bouncer()->hasPermission(lead_permission('edit'))
            || bouncer()->hasPermission('leads.create')
            || bouncer()->hasPermission('leads.edit')
            || bouncer()->hasPermission('sdr_leads.create')
            || bouncer()->hasPermission('sdr_leads.edit')
            || bouncer()->hasPermission('lge_leads.create')
            || bouncer()->hasPermission('lge_leads.edit')
            || bouncer()->hasPermission('lead_clouser_leads.edit')
            || $this->isSdrUser();

        abort_unless($canCreate, 403);

        $this->validate(request(), [
            'name' => [
                'required',
                'max:255',
                Rule::unique('services', 'name'),
            ],
        ]);

        $sortOrder = ((int) DB::table('services')->max('sort_order')) + 1;

        $service = $this->serviceRepository->create([
            'name'       => request('name'),
            'sort_order' => $sortOrder,
        ]);

        return response()->json([
            'data'    => $service,
            'message' => trans('admin::app.leads.services-offered.create-success'),
        ]);
    }

    /**
     * Update the lead attributes.
     */
    public function updateAttributes(int $id)
    {
        $data = request()->all();
        $lead = $this->leadRepository->findOrFail($id);

        if (! $this->sourceAccessService->canEditLead($lead)) {
            return response()->json([
                'message' => trans('admin::app.leads.source-access-denied'),
            ], 403);
        }

        $lockedCodes = $this->lockedLeadAttributeCodes();
        $attemptedLocked = array_values(array_intersect(array_keys($data), $lockedCodes));

        if (! empty($attemptedLocked)) {
            return response()->json([
                'message' => trans('admin::app.leads.locked-fields'),
            ], 403);
        }

        if (array_key_exists('services', $data) || array_key_exists('service_offered', $data)) {
            Event::dispatch('lead.update.before', $id);

            $this->syncLeadServices($lead, $data['services'] ?? $data['service_offered'] ?? []);

            Event::dispatch('lead.update.after', $lead);

            return response()->json([
                'message' => trans('admin::app.leads.update-success'),
            ]);
        }

        if (array_key_exists('lead_source_id', $data) || array_key_exists('lead_sub_source_id', $data)) {
            $sourceId = ! empty($data['lead_source_id']) ? (int) $data['lead_source_id'] : null;
            $subSourceId = ! empty($data['lead_sub_source_id']) ? (int) $data['lead_sub_source_id'] : null;

            if ($sourceId && ! $this->sourceAccessService->canUseLeadSourceSelection($sourceId, $subSourceId)) {
                return response()->json([
                    'message' => trans('admin::app.leads.source-access-denied'),
                ], 403);
            }

            if ($subSourceId && ! $this->sourceAccessService->canAccessSourceId($subSourceId)) {
                return response()->json([
                    'message' => trans('admin::app.leads.source-access-denied'),
                ], 403);
            }
        }

        $attributes = $this->attributeRepository->findWhere([
            'entity_type' => 'leads',
            ['code', 'NOTIN', ['title', 'companies', 'organization_id', 'description', 'service_offered']],
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

            if (! $this->sourceAccessService->canViewLead($lead)) {
                return response()->json([
                    'message' => trans('admin::app.leads.source-access-denied'),
                ], 403);
            }

            $isSharedPoolLead = $this->isSdrUser()
                && $this->sourceAccessService->leadIsInSharedStage($lead);

            $stage = $lead->pipeline->stages()
                ->where('id', request()->input('lead_pipeline_stage_id'))
                ->firstOrFail();

            if (! $this->meetingHandoffService->canCurrentUserEditStage($lead)) {
                return response()->json([
                    'message' => 'You can view this lead, but stage changes are locked after meeting assignment.',
                ], 403);
            }

            if ($this->isCallingRoleUser() && $this->stageIsBeyondMeeting($lead, $stage)) {
                return response()->json([
                    'message' => 'You can move SDR/LGE leads up to Meeting only.',
                ], 403);
            }

            $isLgeHandoffStage = $this->requiresLgeSdrHandoff($lead, $stage);

            if (
                ! $isLgeHandoffStage
                && ! $this->sourceAccessService->canAccessStageId((int) $stage->id)
            ) {
                return response()->json([
                    'message' => trans('admin::app.leads.source-access-denied'),
                ], 403);
            }

            if ($response = $this->validateMeetingStageMove($lead, $stage)) {
                return $response;
            }

            $handoffSdrUserId = null;
            $assignedMeetingOwnerId = null;

            if ($isLgeHandoffStage) {
                $this->validate(request(), [
                    'sdr_user_id' => [
                        'required',
                        'integer',
                        function ($attribute, $value, $fail) use ($lead) {
                            if (! $this->meetingHandoffService->isEligibleMeetingOwnerForLead($lead, (int) $value)) {
                                $message = empty($this->meetingHandoffService->getLeadServiceIds($lead))
                                    ? 'Please select a valid Admin or Lead user.'
                                    : 'The selected owner is not assigned to handle this lead\'s services.';

                                $fail($message);
                            }
                        },
                    ],
                ]);

                $handoffSdrUserId = (int) request()->input('sdr_user_id');
            }

            if (
                $stage->code === 'meeting'
                && $this->isCallingRoleUser()
                && request()->filled('assigned_user_id')
            ) {
                $this->validate(request(), [
                    'assigned_user_id' => [
                        'required',
                        'integer',
                        function ($attribute, $value, $fail) use ($lead) {
                            if (! $this->meetingHandoffService->isEligibleMeetingOwnerForLead($lead, (int) $value)) {
                                $message = empty($this->meetingHandoffService->getLeadServiceIds($lead))
                                    ? 'Please select a valid Admin or Lead user.'
                                    : 'The selected owner is not assigned to handle this lead\'s services.';

                                $fail($message);
                            }
                        },
                    ],
                ]);

                $assignedMeetingOwnerId = (int) request()->input('assigned_user_id');
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

            if ($isSharedPoolLead) {
                $payload['user_id'] = auth()->guard('user')->id();
                $attributes[] = 'user_id';
            }

            if ($handoffSdrUserId) {
                $payload['user_id'] = $handoffSdrUserId;
                $attributes[] = 'user_id';
            }

            if ($assignedMeetingOwnerId) {
                $payload['user_id'] = $assignedMeetingOwnerId;
                $attributes[] = 'user_id';

                if (empty($lead->lead_owner_id)) {
                    $payload['lead_owner_id'] = auth()->guard('user')->id();
                    $attributes[] = 'lead_owner_id';
                }
            }

            $attributes = array_values(array_unique($attributes));

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

        $movingBeyondMeeting = $targetStage->sort_order > $meetingStage->sort_order;

        if ($targetStage->code !== 'meeting' && ! $movingBeyondMeeting) {
            return null;
        }

        $hasMeetingActivity = $lead->activities()
            ->where('type', 'meeting')
            ->exists();

        if (! $hasMeetingActivity) {
            return response()->json([
                'message'                    => trans('admin::app.leads.meeting-stage-requires-activity'),
                'requires_meeting_activity'  => true,
            ], 422);
        }

        if ($movingBeyondMeeting) {
            $lead->activities()
                ->where('type', 'meeting')
                ->where('is_done', 0)
                ->update([
                    'is_done'    => 1,
                    'updated_at' => Carbon::now(),
                ]);
        }

        return null;
    }

    protected function stageIsBeyondMeeting($lead, $targetStage): bool
    {
        $meetingStage = $lead->pipeline->stages()
            ->where('code', 'meeting')
            ->first();

        if (! $meetingStage) {
            return false;
        }

        return (int) $targetStage->sort_order > (int) $meetingStage->sort_order;
    }

    protected function requiresLgeSdrHandoff($lead, $targetStage): bool
    {
        if (! $this->isLgeUser()) {
            return false;
        }

        $currentStage = $lead->stage;
        $meetingStage = $lead->pipeline->stages()
            ->where('code', 'meeting')
            ->first();

        if (! $currentStage || ! $meetingStage) {
            return false;
        }

        return $currentStage->code === 'meeting'
            && $targetStage->sort_order > $meetingStage->sort_order;
    }

    protected function canCurrentUserEditStage($lead): bool
    {
        return $this->meetingHandoffService->canCurrentUserEditStage($lead);
    }

    protected function meetingOwnerOptions(?Lead $lead = null): array
    {
        return $this->meetingHandoffService->getEligibleMeetingOwnersForLead($lead);
    }

    protected function isActiveMeetingOwnerId(int $userId): bool
    {
        return $this->meetingHandoffService->isActiveMeetingOwnerId($userId);
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

        if (! $this->sourceAccessService->canEditLead($lead)) {
            return redirect()->route($this->leadsIndexRouteName());
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

        return redirect()->route($this->leadsIndexRouteName());
    }

    /**
     * Restore a disqualified lead.
     */
    public function restoreDisqualified(int $id): RedirectResponse
    {
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
     * Reassign a review lead back to an owner.
     */
    protected function reassignDisqualifiedLead(int $id, string $expectedReason): RedirectResponse
    {
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
     * Search lead results.
     */
    public function search(): AnonymousResourceCollection
    {
        $limit = min(max((int) request('limit', 20), 1), 50);
        $queryTerm = trim((string) request('query', ''));

        $repository = $this->leadRepository
            ->with([
                'tags.user',
                'type',
                'source',
                'subSource',
                'user',
                'person.organization',
                'pipeline.stages',
                'stage',
            ]);


        if ($queryTerm === '' && request()->filled('search')) {
            $repository->pushCriteria(app(RequestCriteria::class));
        }

        $results = $repository
            ->scopeQuery(function ($query) use ($limit, $queryTerm) {
                $this->sourceAccessService->applyLeadOwnerVisibilityScope($query);
                $this->sourceAccessService->applyLeadQueryScope($query);

                if ($queryTerm !== '') {
                    $this->applyKanbanSearch($query, $queryTerm);
                }

                return $query->limit($limit);
            })
            ->all();

        return LeadResource::collection($results);
    }

    public function destroy(int $id): JsonResponse
    {
        $lead = $this->leadRepository->findOrFail($id);

        if (! $this->sourceAccessService->canEditLead($lead)) {
            return response()->json([
                'message' => trans('admin::app.leads.source-access-denied'),
            ], 403);
        }

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
        $stageId = (int) $massUpdateRequest->input('value');

        if (! $this->sourceAccessService->canAccessStageId($stageId)) {
            return response()->json([
                'message' => trans('admin::app.leads.source-access-denied'),
            ], 403);
        }

        $leads = $this->leadRepository->findWhereIn('id', $massUpdateRequest->input('indices'));

        try {
            foreach ($leads as $lead) {
                if (! $this->sourceAccessService->canEditLead($lead)) {
                    continue;
                }

                Event::dispatch('lead.update.before', $lead->id);

                $this->leadRepository->update([
                    'entity_type'            => 'leads',
                    'lead_pipeline_stage_id' => $stageId,
                ], $lead->id, ['lead_pipeline_stage_id']);

                Event::dispatch('lead.update.after', $this->leadRepository->find($lead->id));
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
                if (! $this->sourceAccessService->canEditLead($lead)) {
                    continue;
                }

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
        $columns = [
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

        if (lead_variant() === 'lge') {
            $columns[] = [
                'index'                 => 'linkedin_profile_id',
                'label'                 => 'LinkedIn Profile',
                'type'                  => 'string',
                'searchable'            => false,
                'search_field'          => 'in',
                'filterable'            => true,
                'filterable_type'       => 'dropdown',
                'filterable_options'    => $this->linkedInProfileAccessService->getFilterOptionsWithHistoricalLeads(),
                'allow_multiple_values' => false,
                'sortable'              => false,
                'visibility'            => false,
            ];
        }

        return $columns;
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
        $oldTags = $lead->tags()->pluck('name')->sort()->values()->implode(', ');

        $tagIds = collect($tagNames)
            ->filter(fn ($name) => filled($name))
            ->map(fn ($name) => is_string($name) ? trim($name) : $name)
            ->filter(fn ($name) => $name !== '' && $name !== null)
            ->unique()
            ->map(function ($value): ?int {
                $allowedNames = collect(StaticTags::names())
                    ->map(fn ($name) => strtolower($name))
                    ->all();

                if (is_numeric($value)) {
                    $tag = $this->tagRepository->find((int) $value);

                    return $tag && in_array(strtolower($tag->name), $allowedNames, true)
                        ? $tag->id
                        : null;
                }

                $name = (string) $value;

                $tag = $this->tagRepository->findOneWhere([
                    'name'    => $name,
                    'user_id' => auth()->id(),
                ]);

                if (! $tag) {
                    $tag = $this->tagRepository->findOneWhere(['name' => $name]);
                }

                if (
                    ! $tag
                    || ! in_array(strtolower($tag->name), $allowedNames, true)
                ) {
                    return null;
                }

                return $tag->id;
            })
            ->filter()
            ->values()
            ->all();

        $tagIds = $this->leadForwardService->normalizeClassificationTagIds($tagIds);

        if ($this->requiresColdLeadForwardForTagSync($lead, $tagIds)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'tags' => ['Forward this cold lead to an SDR from the lead detail tag popup.'],
            ]);
        }

        $lead->tags()->sync($tagIds);

        $newTags = $lead->tags()->pluck('name')->sort()->values()->implode(', ');

        \Webkul\Lead\Models\Lead::storeSystemActivity(
            $lead,
            'Tags',
            $oldTags !== '' ? $oldTags : null,
            $newTags !== '' ? $newTags : null
        );
    }

    /**
     * Generic tag form submissions do not include the SDR needed for the Warm
     * -> Cold handoff, so block that transition outside the dedicated popup.
     */
    private function requiresColdLeadForwardForTagSync($lead, array $tagIds): bool
    {
        $user = auth()->guard('user')->user();
        $userId = (int) ($user?->id ?? 0);
        $coldLeadTagId = $this->leadForwardService->coldLeadTagId();
        $warmLeadTagId = $this->leadForwardService->warmLeadTagId();

        if (
            ! $userId
            || ! $coldLeadTagId
            || ! $warmLeadTagId
            || ! $this->sourceAccessService->isLgeUser($user)
            || ! in_array($coldLeadTagId, $tagIds, true)
        ) {
            return false;
        }

        $lead->loadMissing('tags');

        if (! $lead->tags->contains('id', $warmLeadTagId) || $lead->tags->contains('id', $coldLeadTagId)) {
            return false;
        }

        return (int) $lead->user_id === $userId
            && (int) ($lead->lead_owner_id ?? $lead->user_id) === $userId;
    }

    /**
     * Sync service ids on a lead.
     */
    private function syncLeadServices($lead, $serviceIds): void
    {
        if ($serviceIds === null) {
            return;
        }

        if (! is_array($serviceIds)) {
            $serviceIds = array_filter(array_map('intval', explode(',', (string) $serviceIds)));
        }

        $ids = collect($serviceIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $oldServices = $lead->services()->orderBy('name')->pluck('name')->implode(', ');

        $lead->services()->sync($ids);

        $newServices = $lead->services()->orderBy('name')->pluck('name')->implode(', ');

        \Webkul\Lead\Models\Lead::storeSystemActivity(
            $lead,
            'Services Offered',
            $oldServices !== '' ? $oldServices : null,
            $newServices !== '' ? $newServices : null
        );
    }

    protected function requiredImportColumns(): array
    {
        $columns = [
            'companies',
            'lead_value',
            'type',
            'pricing_type',
        ];

        if (lead_variant() === 'lge') {
            $columns[] = 'source_link';
        }

        return $columns;
    }

    protected function importColumnAliases(): array
    {
        return [
            'title'              => 'companies',
            'lead_title'         => 'companies',
            'company_name'       => 'companies',
            'organization'       => 'companies',
            'organization_name'  => 'companies',
            'value'              => 'lead_value',
            'amount'             => 'lead_value',
            'lead_source'        => 'source',
            'lead_type'          => 'type',
            'owner'              => 'sales_owner_email',
            'sales_owner'        => 'sales_owner_email',
            'owner_email'        => 'sales_owner_email',
            'contact_name'       => 'person_name',
            'person'             => 'person_name',
            'contact_email'      => 'email',
            'contact_phone'      => 'phone',
            'phone_number'       => 'phone',
            'mobile'             => 'phone',
            'street'             => 'address',
            'address_line'       => 'address',
            'zip'                => 'postcode',
            'zipcode'            => 'postcode',
            'postal_code'         => 'postcode',
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
            $value = $this->mapLeadImportCell($column, $index, $row);
            $data[$column] = is_string($value) ? trim($value) : $value;
        }

        return $data;
    }

    protected function mapLeadImportCell(string $column, int $index, array $row): mixed
    {
        $value = $row[$index] ?? null;

        if (in_array($column, ['source_link'], true)
            && is_string($value)
            && in_array(strtolower($value), ['http', 'https'], true)
            && isset($row[$index + 1])
            && is_string($row[$index + 1])
            && str_starts_with($row[$index + 1], '//')
        ) {
            return $value.':'.$row[$index + 1];
        }

        return $value;
    }

    protected function validateImportRow(array $row, bool $skipSalesOwnerEmail = false, ?int $batchLinkedInProfileId = null): array
    {
        $errors = [];

        foreach ($this->requiredImportColumns() as $column) {
            if (! filled($row[$column] ?? null)) {
                $errors[] = $column.' is required.';
            }
        }

        if (lead_variant() === 'lge' && filled($row['source_link'] ?? null)) {
            $profileId = $this->resolveImportedLinkedInProfileId($row, $batchLinkedInProfileId);

            if ($profileId <= 0) {
                $errors[] = 'LinkedIn working profile is required for rows without a matching entry profile.';
            } else {
                try {
                    $this->linkedInProfileAccessService->assertCanUseProfile(
                        $profileId,
                        auth()->guard('user')->user(),
                        (int) auth()->guard('user')->id(),
                    );
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    $errors[] = collect($exception->errors())->flatten()->first();
                }
            }
        }

        if (filled($row['lead_value'] ?? null) && ! is_numeric($row['lead_value'])) {
            $errors[] = 'lead_value must be numeric.';
        }

        if (filled($row['email'] ?? null) && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'email must be a valid email address.';
        }

        if (filled($row['phone'] ?? null)) {
            foreach (ContactPhoneCollection::invalidTokens($row['phone']) as $invalidPhone) {
                $errors[] = 'phone "'.$invalidPhone.'" is not a valid phone number.';
            }
        }

        foreach (['type' => 'lead_types'] as $column => $table) {
            if (filled($row[$column] ?? null) && ! $this->resolveImportId($table, $row[$column])) {
                $errors[] = $column.' "'.$row[$column].'" was not found.';
            }
        }

        if (filled($row['pricing_type'] ?? null) && ! $this->resolveAttributeOptionId('pricing_type', $row['pricing_type'])) {
            $errors[] = 'pricing_type "'.$row['pricing_type'].'" was not found.';
        }

        if (
            ! $skipSalesOwnerEmail
            && lead_variant() !== 'lge'
            && filled($row['sales_owner_email'] ?? null)
            && ! $this->resolveUserId($row['sales_owner_email'])
        ) {
            $errors[] = 'sales_owner_email "'.$row['sales_owner_email'].'" was not found.';
        }

        if (filled($row['pipeline'] ?? null) && ! $this->resolveImportId('lead_pipelines', $row['pipeline'])) {
            $errors[] = 'pipeline "'.$row['pipeline'].'" was not found.';
        }

        return $errors;
    }

    protected function prepareImportedLeadData(
        array $row,
        int $leadSourceId,
        ?int $assigneeUserId = null,
        ?int $industryId = null,
        ?int $batchLinkedInProfileId = null,
    ): array {
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

        if ($stage->code === 'new') {
            $scheduleFollowup = false;
            $nextFollowupDate = null;
        }

        if (! $this->sourceAccessService->canUseLeadSourceSelection($leadSourceId)) {
            throw new \InvalidArgumentException('You do not have access to the selected lead source.');
        }

        $addressLine = $this->nullableImportValue($row['address'] ?? null);
        $city = $this->nullableImportValue($row['city'] ?? null);
        $state = $this->nullableImportValue($row['state'] ?? null);
        $country = $this->nullableImportValue($row['country'] ?? null);
        $postcode = $this->nullableImportValue($row['postcode'] ?? null);

        $personAddress = null;

        if (collect([$addressLine, $city, $state, $country, $postcode])->contains(fn ($value) => filled($value))) {
            $personAddress = [
                'address'  => $addressLine,
                'city'     => $city,
                'state'    => $state,
                'country'  => $country,
                'postcode' => $postcode,
            ];
        }

        $ownerId = $assigneeUserId
            ?? (in_array(lead_variant(), ['lge', 'sdr'], true)
                ? auth()->guard('user')->id()
                : (filled($row['sales_owner_email'] ?? null)
                    ? $this->resolveUserId($row['sales_owner_email'])
                    : null));

        $lead = [
            'entity_type'              => 'leads',
            'organization_name'        => trim((string) ($row['companies'] ?? $row['title'] ?? $row['company'] ?? '')),
            'description'              => $this->nullableImportValue($row['description'] ?? null),
            'lead_value'               => (float) $row['lead_value'],
            'lead_source_id'           => $leadSourceId,
            'lead_sub_source_id'       => null,
            'lead_type_id'             => $this->resolveImportId('lead_types', $row['type']),
            'pricing_type'             => $this->resolveAttributeOptionId('pricing_type', $row['pricing_type']),
            'source_sub_type'          => $this->nullableImportValue($row['source_sub_type'] ?? null),
            'source_link'              => $this->nullableImportValue($row['source_link'] ?? null),
            'user_id'                  => $ownerId,
            'lead_owner_id'            => $assigneeUserId
                ?? (in_array(lead_variant(), ['lge', 'sdr'], true)
                    ? auth()->guard('user')->id()
                    : null),
            'lead_pipeline_id'         => $pipeline->id,
            'lead_pipeline_stage_id'   => $stage->id,
            'status'                   => 1,
            'expected_close_date'      => $this->formatImportDate($row['expected_close_date'] ?? null),
            'schedule_followup'        => $scheduleFollowup,
            'next_followup_date'       => $nextFollowupDate,
            'person'                   => [
                'name'             => $this->nullableImportValue($row['person_name'] ?? null),
                'organization_name'=> $this->nullableImportValue($row['company'] ?? $row['companies'] ?? null),
                'emails'           => filled($row['email'] ?? null)
                    ? [['value' => trim($row['email']), 'label' => 'work']]
                    : [],
                'contact_numbers'  => ContactPhoneCollection::fromImportValue($row['phone'] ?? null),
                'address'          => $personAddress,
            ],
        ];

        if ($industryId) {
            $lead['industry'] = $industryId;
        }

        if (lead_variant() === 'lge' && filled($row['source_link'] ?? null)) {
            $profileId = $this->resolveImportedLinkedInProfileId($row, $batchLinkedInProfileId);

            if ($profileId <= 0) {
                throw new \InvalidArgumentException('LinkedIn working profile is required for this import row.');
            }

            $this->linkedInProfileAccessService->assertCanUseProfile(
                $profileId,
                auth()->guard('user')->user(),
                (int) auth()->guard('user')->id(),
            );

            $lead['linkedin_profile_id'] = $profileId;
        }

        return $lead;
    }

    protected function createImportedLeadFromRow(
        array $rowData,
        int $sourceId,
        array $assignment,
        int $assignIndex,
        ?int $batchLinkedInProfileId,
        int $importTagId,
    ): Lead {
        $assigneeUserId = $this->assigneeForImportIndex($assignment['assignee_user_ids'] ?? [], $assignIndex);
        $isLgeColdForward = $this->isLgeColdForwardImport($importTagId);

        if ($isLgeColdForward && ! $assigneeUserId) {
            throw new \InvalidArgumentException('Please select one or more SDR users to forward cold leads.');
        }

        return DB::transaction(function () use (
            $rowData,
            $sourceId,
            $assignment,
            $batchLinkedInProfileId,
            $importTagId,
            $assigneeUserId,
            $isLgeColdForward,
        ) {
            Event::dispatch('lead.create.before');

            $lead = $this->leadRepository->create($this->prepareImportedLeadData(
                $rowData,
                $sourceId,
                $isLgeColdForward ? null : $assigneeUserId,
                $assignment['industry_id'] ?? null,
                $batchLinkedInProfileId,
            ));

            $this->syncLeadTags($lead, $this->tagsFromImportRow($rowData));

            $this->syncImportTagForLead($lead, $importTagId);

            if (lead_variant() === 'lge') {
                $this->backfillLinkedInEntryProfile(
                    $rowData['source_link'] ?? null,
                    (int) ($lead->linkedin_profile_id ?? 0),
                );
                $this->markLinkedInSourceLinkAsResponse($rowData['source_link'] ?? null);
            }

            if ($isLgeColdForward) {
                $lead = $this->leadForwardService->forwardColdLeadToSdr(
                    $lead,
                    (int) auth()->guard('user')->id(),
                    (int) $assigneeUserId,
                    false,
                );
            }

            Event::dispatch('lead.create.after', $lead);

            return $lead;
        });
    }

    /**
     * @return int|null|JsonResponse|RedirectResponse
     */
    protected function validatedImportTagId(): int|null|JsonResponse|RedirectResponse
    {
        $tagId = (int) request()->input('import_tag_id', 0);

        if ($tagId <= 0) {
            $message = 'Please select a tag for this import.';

            if (request()->ajax() || request()->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->back()->withErrors(['import_tag_id' => $message]);
        }

        $tag = $this->tagRepository->find($tagId);

        $allowedNames = collect(StaticTags::names())
            ->map(fn ($name) => strtolower($name))
            ->all();

        if (
            ! $tag
            || ! in_array(strtolower(trim((string) $tag->name)), $allowedNames, true)
        ) {
            $message = 'The selected tag is invalid.';

            if (request()->ajax() || request()->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->back()->withErrors(['import_tag_id' => $message]);
        }

        return $tagId;
    }

    /**
     * @return int|null|JsonResponse|RedirectResponse
     */
    protected function validatedLgeImportProfileId(): int|null|JsonResponse|RedirectResponse
    {
        if (lead_variant() !== 'lge') {
            return null;
        }

        $profileId = (int) request()->input('import_linkedin_profile_id', 0);

        if ($profileId <= 0) {
            $message = 'Please select a LinkedIn working profile for this import.';

            if (request()->ajax() || request()->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->back()->withErrors(['import_linkedin_profile_id' => $message]);
        }

        try {
            $this->linkedInProfileAccessService->assertCanUseProfile(
                $profileId,
                auth()->guard('user')->user(),
                (int) auth()->guard('user')->id(),
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            if (request()->ajax() || request()->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors'  => $exception->errors(),
                ], 422);
            }

            return redirect()->back()->withErrors($exception->errors());
        }

        return $profileId;
    }

    /**
     * @return array{assignee_user_ids: array<int, int>, industry_id: int|null}|JsonResponse|RedirectResponse
     */
    protected function validatedBulkImportAssignment(): array|JsonResponse|RedirectResponse
    {
        $isLgeColdForwardImport = $this->isLgeColdForwardImport((int) request()->input('import_tag_id', 0));

        if ($isLgeColdForwardImport) {
            $assigneeUserIds = $this->normalizeImportAssigneeIds(request()->input('assignee_user_ids', []));

            try {
                $assigneeUserIds = $this->leadForwardService->validateActiveSdrIds(
                    $assigneeUserIds,
                    'assignee_user_ids',
                );
            } catch (\Illuminate\Validation\ValidationException) {
                return $this->bulkImportAssignmentError('Please select one or more active SDR users to forward cold leads.');
            }

            return [
                'assignee_user_ids' => array_values($assigneeUserIds),
                'industry_id'       => null,
            ];
        }

        if (! $this->sourceAccessService->isAdmin()) {
            return [
                'assignee_user_ids' => [],
                'industry_id'       => null,
            ];
        }

        $assigneeUserIds = $this->normalizeImportAssigneeIds(request()->input('assignee_user_ids', []));
        $industryId = $this->normalizeImportIndustryId(request()->input('industry_id'));
        $allowedSdrIds = $this->sdrUserIdsForBulkImport();

        if (empty($allowedSdrIds)) {
            return $this->bulkImportAssignmentError('No active SDR users are available to assign these leads.');
        }

        if (empty($assigneeUserIds) || array_diff($assigneeUserIds, $allowedSdrIds)) {
            return $this->bulkImportAssignmentError('Please select one or more SDR users to assign these leads.');
        }

        if (! $industryId || ! $this->industryOptionExists($industryId)) {
            return $this->bulkImportAssignmentError('Please select a valid industry for this import.');
        }

        return [
            'assignee_user_ids' => array_values($assigneeUserIds),
            'industry_id'       => $industryId,
        ];
    }

    protected function bulkImportAssignmentError(string $message): JsonResponse|RedirectResponse
    {
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'message' => $message,
            ], 422);
        }

        return $this->importResponse(0, [$message], 422);
    }

    /**
     * @return array<int, int>
     */
    protected function sdrUserIdsForBulkImport(): array
    {
        return $this->leadForwardService->activeSdrUsers()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function isLgeColdForwardImport(int $importTagId): bool
    {
        return lead_variant() === 'lge'
            && $this->sourceAccessService->isLgeUser()
            && $this->leadForwardService->isColdLeadTagSelected([$importTagId]);
    }

    protected function industryOptionExists(int $industryId): bool
    {
        return DB::table('attribute_options')
            ->join('attributes', 'attributes.id', '=', 'attribute_options.attribute_id')
            ->where('attributes.entity_type', 'leads')
            ->where('attributes.code', 'industry')
            ->where('attribute_options.id', $industryId)
            ->exists();
    }

    /**
     * @return array<int, int>
     */
    protected function normalizeImportAssigneeIds($ids): array
    {
        return collect(is_array($ids) ? $ids : [$ids])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function normalizeImportIndustryId($id): ?int
    {
        $id = (int) $id;

        return $id > 0 ? $id : null;
    }

    protected function assigneeForImportIndex(array $ids, int $index): ?int
    {
        if (empty($ids)) {
            return null;
        }

        return $ids[$index % count($ids)];
    }

    protected function maxBulkLeadImportRows(): int
    {
        return 500;
    }

    /**
     * Skip when company + email + phone all match a prior row or an existing lead.
     *
     * @param  array<int, string>  $seenDuplicateKeys
     */
    protected function importDuplicateSkipMessage(array $rowData, array &$seenDuplicateKeys): ?string
    {
        $keys = $this->importDuplicateKeysFromRow($rowData);

        if (empty($keys)) {
            return null;
        }

        if (array_intersect($keys, $seenDuplicateKeys)) {
            return 'skipped duplicate lead (same company, email, and phone in this file).';
        }

        if ($this->leadExistsWithCompanyEmailPhone($rowData)) {
            array_push($seenDuplicateKeys, ...$keys);

            return 'skipped duplicate lead (same company, email, and phone already exist).';
        }

        array_push($seenDuplicateKeys, ...$keys);

        return null;
    }

    protected function importDuplicateKeyFromRow(array $rowData): ?string
    {
        $company = $this->normalizeImportCompanyName(
            $rowData['companies'] ?? $rowData['title'] ?? $rowData['company'] ?? null
        );
        $email = $this->normalizeImportEmail($rowData['email'] ?? null);
        $phones = ContactPhoneCollection::compareKeys($rowData['phone'] ?? null);

        if ($company === null || $email === null || empty($phones)) {
            return null;
        }

        return $company.'|'.$email.'|'.implode(',', $phones);
    }

    /**
     * @return array<int, string>
     */
    protected function importDuplicateKeysFromRow(array $rowData): array
    {
        $company = $this->normalizeImportCompanyName(
            $rowData['companies'] ?? $rowData['title'] ?? $rowData['company'] ?? null
        );
        $email = $this->normalizeImportEmail($rowData['email'] ?? null);
        $phones = ContactPhoneCollection::compareKeys($rowData['phone'] ?? null);

        if ($company === null || $email === null || empty($phones)) {
            return [];
        }

        return array_map(
            fn (string $phone) => $company.'|'.$email.'|'.$phone,
            $phones
        );
    }

    protected function normalizeImportCompanyName($value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeImportEmail($value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeImportPhone($value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === null || $digits === '') {
            return null;
        }

        return $digits;
    }

    protected function leadExistsWithCompanyEmailPhone(array $rowData): bool
    {
        $company = $this->normalizeImportCompanyName(
            $rowData['companies'] ?? $rowData['title'] ?? $rowData['company'] ?? null
        );
        $email = $this->normalizeImportEmail($rowData['email'] ?? null);
        $phones = ContactPhoneCollection::compareKeys($rowData['phone'] ?? null);

        if ($company === null || $email === null || empty($phones)) {
            return false;
        }

        $organizationIds = DB::table('organizations')
            ->whereRaw('LOWER(TRIM(name)) = ?', [$company])
            ->pluck('id');

        if ($organizationIds->isEmpty()) {
            return false;
        }

        $candidates = DB::table('leads')
            ->join('persons', 'leads.person_id', '=', 'persons.id')
            ->whereNull('leads.deleted_at')
            ->whereIn('leads.organization_id', $organizationIds->all())
            ->whereNotNull('leads.person_id')
            ->select('persons.emails', 'persons.contact_numbers')
            ->get();

        foreach ($candidates as $candidate) {
            $emails = is_string($candidate->emails)
                ? (json_decode($candidate->emails, true) ?: [])
                : (array) $candidate->emails;
            $candidatePhones = ContactPhoneCollection::compareKeys(
                is_string($candidate->contact_numbers)
                    ? (json_decode($candidate->contact_numbers, true) ?: [])
                    : (array) $candidate->contact_numbers
            );

            $candidateEmail = $this->normalizeImportEmail($emails[0]['value'] ?? null);

            if ($candidateEmail === $email && array_intersect($phones, $candidatePhones)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @deprecated Bulk import now uses the source selected in the import modal.
     */
    protected function resolveColdCallSourceId(): int
    {
        $sourceId = DB::table('lead_sources')
            ->whereRaw('LOWER(name) = ?', ['cold call'])
            ->value('id');

        if (! $sourceId) {
            throw new \InvalidArgumentException('Cold Call source was not found. Please create it before importing leads.');
        }

        return (int) $sourceId;
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

    protected function importResponse(int $created, array $errors = [], int $status = 200, int $skipped = 0): RedirectResponse|JsonResponse
    {
        $message = $created.' lead'.($created === 1 ? '' : 's').' imported.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' duplicate'.($skipped === 1 ? '' : 's').' skipped.';
        }

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'message' => $message,
                'created' => $created,
                'skipped' => $skipped,
                'errors'  => $errors,
            ], $status);
        }

        session()->flash($errors ? ($created ? 'warning' : 'error') : 'success', $errors
            ? $message.' '.count($errors).' row'.(count($errors) === 1 ? '' : 's').' failed. '.implode(' ', array_slice($errors, 0, 5))
            : $message);

        return redirect()->route($this->leadsIndexRouteName());
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

        if (! $this->sourceAccessService->canEditLead($lead)) {
            return redirect()->route($this->leadsIndexRouteName());
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
                    $this->syncLeadServices($replicatedLead, $lead->services()->pluck('services.id')->all());

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
                    $this->syncLeadServices($replicatedLead, $lead->services()->pluck('services.id')->all());

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
        $payload['organization_id'] = $organizationId;
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

        $lead = $this->leadRepository->findOrFail($id);

        if (! $this->sourceAccessService->canEditLead($lead)) {
            abort(403);
        }

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
