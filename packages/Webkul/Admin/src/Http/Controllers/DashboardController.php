<?php

namespace Webkul\Admin\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Webkul\Admin\Helpers\Dashboard;
use Webkul\Lead\Services\LeadForwardService;
use Webkul\Lead\Services\SourceAccessService;
use Webkul\Lead\Services\UsStateTimezoneService;

class DashboardController extends Controller
{
    /**
     * Request param functions
     *
     * @var array
     */
    protected $typeFunctions = [
        'over-all'             => 'getOverAllStats',
        'revenue-stats'        => 'getRevenueStats',
        'total-leads'          => 'getTotalLeadsStats',
        'revenue-by-sources'   => 'getLeadsStatsBySources',
        'revenue-by-types'     => 'getLeadsStatsByTypes',
        'top-selling-products' => 'getTopSellingProducts',
        'top-persons'          => 'getTopPersons',
        'open-leads-by-states' => 'getOpenLeadsByStates',
    ];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected Dashboard $dashboardHelper,
        protected UsStateTimezoneService $usStateTimezoneService,
    ) {}

    /**
     * Main / common dashboard.
     */
    public function index()
    {
        $sourceAccessService = app(SourceAccessService::class);

        if ($this->isPrimaryAdminUser() || $sourceAccessService->isAdmin()) {
            return view('admin::dashboard.index')->with([
                'startDate' => $this->dashboardHelper->getStartDate(),
                'endDate'   => $this->dashboardHelper->getEndDate(),
            ]);
        }

        if ($sourceAccessService->isBdeUser()) {
            return redirect()->route('admin.dashboard.bde');
        }

        if ($sourceAccessService->isLeadCloserUser()) {
            return redirect()->route('admin.dashboard.lead_clouser');
        }

        return view('admin::dashboard.index')->with([
            'startDate' => $this->dashboardHelper->getStartDate(),
            'endDate'   => $this->dashboardHelper->getEndDate(),
        ]);
    }

    /**
     * SDR calling dashboard.
     */
    public function sdr(): View
    {
        $this->abortUnlessDashboardRole('sdr');

        return view('admin::dashboard.sdr.index')->with([
            'stateTimezones' => $this->usStateTimezoneService->allStates(),
            'dashboardTitle' => 'SDR Dashboard',
            'showUsFeatures' => true,
            'dashboardVariant' => 'sdr',
            'showMeetings' => false,
            'showDashboardCalendar' => $this->canViewDashboardCalendar(),
        ]);
    }

    /**
     * BD Executive dashboard scoped to the current active sales-owner work.
     */
    public function bde(): View
    {
        $this->abortUnlessDashboardRole('bde');

        return view('admin::dashboard.sdr.index')->with([
            'stateTimezones' => [],
            'dashboardTitle' => 'BD Executive Dashboard',
            'showUsFeatures' => false,
            'dashboardVariant' => 'bde',
            'showMeetings' => false,
            'showDashboardCalendar' => $this->canViewDashboardCalendar(),
        ]);
    }

    /**
     * Lead Clouser dashboard uses the SDR calling dashboard layout.
     */
    public function leadClouser(): View
    {
        $this->abortUnlessDashboardRole('lead_clouser');

        return view('admin::dashboard.sdr.index')->with([
            'stateTimezones' => $this->usStateTimezoneService->allStates(),
            'dashboardTitle' => 'Lead Closer Dashboard',
            'showUsFeatures' => true,
            'dashboardVariant' => 'lead_clouser',
            'showMeetings' => true,
            'showDashboardCalendar' => $this->canViewDashboardCalendar(),
        ]);
    }

    /**
     * LGE calling dashboard (no US timezone features).
     */
    public function lge(): View
    {
        $this->abortUnlessDashboardRole('lge');

        return view('admin::dashboard.sdr.index')->with([
            'stateTimezones' => [],
            'dashboardTitle' => 'Lead Gen Executive Dashboard',
            'showUsFeatures' => false,
            'dashboardVariant' => 'lge',
            'showDashboardCalendar' => $this->canViewDashboardCalendar(),
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        $stats = $this->dashboardHelper->{$this->typeFunctions[request()->query('type')]}();

        return response()->json([
            'statistics' => $stats,
            'date_range' => $this->dashboardHelper->getDateRange(),
        ]);
    }

    /**
     * Returns SDR call, meeting, and lead outcome summary.
     */
    public function callSummary(): JsonResponse
    {
        $this->ensureCallingDashboardAccess();

        $data = request()->validate([
            'variant'    => ['nullable', 'in:bde,sdr,lge,lead_clouser'],
            'period'     => ['nullable', 'in:day,week,month'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date'],
        ]);

        [$startDate, $endDate] = $this->periodRange(
            $data['period'] ?? 'day',
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
        );

        if (($data['variant'] ?? null) === 'lge') {
            return response()->json($this->lgeLinkedInSummary($startDate, $endDate));
        }

        $userId = auth()->guard('user')->id();
        $sourceAccessService = app(SourceAccessService::class);
        $variant = $data['variant'] ?? 'sdr';

        $this->abortUnlessDashboardRole($variant);

        if ($variant === 'bde') {
            return response()->json($this->bdeDashboardSummary($startDate, $endDate, $userId));
        }

        if ($variant === 'lead_clouser') {
            return response()->json($this->leadCloserCallSummary($startDate, $endDate, $userId, $sourceAccessService));
        }

        return response()->json($this->sdrCallSummary($startDate, $endDate, $userId, $sourceAccessService));
    }

    /**
     * Returns week/month meeting and follow-up events for role dashboards.
     */
    public function calendarEvents(): JsonResponse
    {
        $this->ensureDashboardCalendarAccess();

        $data = request()->validate([
            'variant'   => ['nullable', 'in:bde,sdr,lge,lead_clouser'],
            'view_type' => ['nullable', 'in:week,month'],
            'date'      => ['nullable', 'date'],
        ]);

        $variant = $data['variant'] ?? 'sdr';

        $this->abortUnlessDashboardRole($variant);

        [$startDate, $endDate] = $this->dashboardCalendarRange(
            $data['view_type'] ?? 'week',
            $data['date'] ?? null,
        );

        $userId = auth()->guard('user')->id();
        $showUsFeatures = in_array($variant, ['sdr', 'lead_clouser'], true);

        $meetings = $this->dashboardCalendarMeetings($userId, $startDate, $endDate, $showUsFeatures);
        $followups = $this->dashboardCalendarFollowups($userId, $startDate, $endDate, $showUsFeatures);

        $events = $meetings
            ->merge($followups)
            ->sortBy('sort_at')
            ->values();

        return response()->json([
            'events' => $events,
            'period' => [
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ],
        ]);
    }

    /**
     * SDR dashboard: calls performed by the SDR, meetings booked from originated leads, outcomes by lead_owner_id.
     */
    protected function sdrCallSummary(
        Carbon $startDate,
        Carbon $endDate,
        int $userId,
        SourceAccessService $sourceAccessService,
    ): array {
        $callQuery = DB::table('activities')
            ->leftJoin('activity_participants', 'activities.id', '=', 'activity_participants.activity_id')
            ->leftJoin('lead_activities', 'activities.id', '=', 'lead_activities.activity_id')
            ->leftJoin('leads', 'lead_activities.lead_id', '=', 'leads.id')
            ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
            ->where('activities.type', 'call')
            ->whereBetween('activities.schedule_from', [$startDate, $endDate])
            ->where(function ($query) use ($userId) {
                $query
                    ->where('activities.user_id', $userId)
                    ->orWhere('activity_participants.user_id', $userId);
            });

        $sourceAccessService->applyLeadTableScope($callQuery);

        $callStats = $callQuery
            ->selectRaw('COUNT(DISTINCT activities.id) as total_calls')
            ->selectRaw("COUNT(DISTINCT CASE WHEN activities.call_status = 'done' OR (activities.call_status IS NULL AND activities.is_done = 1) THEN activities.id END) as answered_calls")
            ->first();

        $totalCalls = (int) ($callStats->total_calls ?? 0);
        $answeredCalls = (int) ($callStats->answered_calls ?? 0);

        $meetingQuery = DB::table('activities')
            ->join('lead_activities', 'activities.id', '=', 'lead_activities.activity_id')
            ->join('leads', 'lead_activities.lead_id', '=', 'leads.id')
            ->where('activities.type', 'meeting')
            ->whereNull('leads.deleted_at')
            ->where('activities.user_id', $userId)
            ->whereBetween('activities.schedule_from', [$startDate, $endDate]);

        $sourceAccessService->applyLeadTableScope($meetingQuery);

        $meetingStats = $meetingQuery
            ->selectRaw('COUNT(DISTINCT activities.id) as booked_meetings')
            ->selectRaw("COUNT(DISTINCT CASE WHEN activities.call_status = 'done' OR activities.is_done = 1 THEN activities.id END) as attended_meetings")
            ->first();

        $bookedMeetings = (int) ($meetingStats->booked_meetings ?? 0);
        $attendedMeetings = (int) ($meetingStats->attended_meetings ?? 0);

        $leadQuery = DB::table('leads')
            ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNull('leads.deleted_at')
            ->whereNotNull('leads.closed_at')
            ->whereBetween('leads.closed_at', [$startDate, $endDate])
            ->whereIn('lead_pipeline_stages.code', ['won', 'lost']);

        $sourceAccessService->applyOriginatingCallingOwnerTableScope($leadQuery, $userId);
        $sourceAccessService->applyLeadTableScope($leadQuery);

        $leadStats = $leadQuery
            ->selectRaw("COUNT(DISTINCT CASE WHEN lead_pipeline_stages.code = 'won' THEN leads.id END) as won_leads")
            ->selectRaw("COUNT(DISTINCT CASE WHEN lead_pipeline_stages.code = 'lost' THEN leads.id END) as lost_leads")
            ->first();

        $wonLeads = (int) ($leadStats->won_leads ?? 0);
        $lostLeads = (int) ($leadStats->lost_leads ?? 0);
        $outcomeLeads = $wonLeads + $lostLeads;
        $days = max(1, $startDate->diffInDays($endDate) + 1);

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ],
            'calls' => [
                'total'                    => $totalCalls,
                'answered'                 => $answeredCalls,
                'answer_rate'              => $totalCalls ? round(($answeredCalls / $totalCalls) * 100, 1) : 0,
                'answered_average_per_day' => round($answeredCalls / $days, 1),
            ],
            'outcomes' => [
                'won'         => $wonLeads,
                'lost'        => $lostLeads,
                'won_percent' => $outcomeLeads ? round(($wonLeads / $outcomeLeads) * 100) : 0,
                'lost_percent'=> $outcomeLeads ? round(($lostLeads / $outcomeLeads) * 100) : 0,
            ],
            'meetings' => [
                'booked'       => $bookedMeetings,
                'assigned'     => $bookedMeetings,
                'attended'     => $attendedMeetings,
                'attend_rate'  => $bookedMeetings ? round(($attendedMeetings / $bookedMeetings) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * Lead Closer dashboard: assigned meetings and current-assignee outcomes.
     */
    protected function leadCloserCallSummary(
        Carbon $startDate,
        Carbon $endDate,
        int $userId,
        SourceAccessService $sourceAccessService,
    ): array {
        $meetingQuery = DB::table('activities')
            ->leftJoin('activity_participants', 'activities.id', '=', 'activity_participants.activity_id')
            ->leftJoin('lead_activities', 'activities.id', '=', 'lead_activities.activity_id')
            ->leftJoin('leads', 'lead_activities.lead_id', '=', 'leads.id')
            ->where('activities.type', 'meeting')
            ->whereBetween('activities.schedule_from', [$startDate, $endDate])
            ->where(function ($query) use ($userId) {
                $query
                    ->where('activities.user_id', $userId)
                    ->orWhere('activity_participants.user_id', $userId);
            });

        $sourceAccessService->applyLeadTableScope($meetingQuery);

        $meetingStats = $meetingQuery
            ->selectRaw('COUNT(DISTINCT activities.id) as booked_meetings')
            ->selectRaw("COUNT(DISTINCT CASE WHEN activities.call_status = 'done' OR activities.is_done = 1 THEN activities.id END) as attended_meetings")
            ->first();

        $bookedMeetings = (int) ($meetingStats->booked_meetings ?? 0);
        $attendedMeetings = (int) ($meetingStats->attended_meetings ?? 0);

        $leadQuery = DB::table('leads')
            ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNull('leads.deleted_at')
            ->whereNotNull('leads.closed_at')
            ->whereBetween('leads.closed_at', [$startDate, $endDate])
            ->whereIn('lead_pipeline_stages.code', ['won', 'lost']);

        $sourceAccessService->applyCurrentAssigneeTableScope($leadQuery, $userId);
        $sourceAccessService->applyLeadTableScope($leadQuery);

        $leadStats = $leadQuery
            ->selectRaw("COUNT(DISTINCT CASE WHEN lead_pipeline_stages.code = 'won' THEN leads.id END) as won_leads")
            ->selectRaw("COUNT(DISTINCT CASE WHEN lead_pipeline_stages.code = 'lost' THEN leads.id END) as lost_leads")
            ->first();

        $wonLeads = (int) ($leadStats->won_leads ?? 0);
        $lostLeads = (int) ($leadStats->lost_leads ?? 0);
        $outcomeLeads = $wonLeads + $lostLeads;

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ],
            'calls' => [
                'total'                    => 0,
                'answered'                 => 0,
                'answer_rate'              => 0,
                'answered_average_per_day' => 0,
            ],
            'outcomes' => [
                'won'         => $wonLeads,
                'lost'        => $lostLeads,
                'won_percent' => $outcomeLeads ? round(($wonLeads / $outcomeLeads) * 100) : 0,
                'lost_percent'=> $outcomeLeads ? round(($lostLeads / $outcomeLeads) * 100) : 0,
            ],
            'meetings' => [
                'booked'       => $bookedMeetings,
                'assigned'     => $bookedMeetings,
                'attended'     => $attendedMeetings,
                'attend_rate'  => $bookedMeetings ? round(($attendedMeetings / $bookedMeetings) * 100, 1) : 0,
            ],
        ];
    }

    /**
     * BDE dashboard: scoped to the current BDE's active, created, and forwarded lead work.
     */
    protected function bdeDashboardSummary(Carbon $startDate, Carbon $endDate, int $userId): array
    {
        $totalCreated = (int) $this->applyBdeCreatedLeadScope(
            DB::table('leads')->whereNull('leads.deleted_at'),
            $userId
        )
            ->whereBetween('leads.created_at', [$startDate, $endDate])
            ->distinct('leads.id')
            ->count('leads.id');

        $forwardedLeads = Schema::hasTable('lead_forwards')
            ? (int) DB::table('lead_forwards')
                ->where('from_user_id', $userId)
                ->whereBetween('forwarded_at', [$startDate, $endDate])
                ->distinct('lead_id')
                ->count('lead_id')
            : 0;

        $followups = (int) DB::table('leads')
            ->whereNull('deleted_at')
            ->where('user_id', $userId)
            ->whereNotNull('next_followup_date')
            ->whereBetween('next_followup_date', [$startDate, $endDate])
            ->distinct('id')
            ->count('id');

        $meetingsBooked = (int) $this->applyBdeWorkLeadScope(
            DB::table('activities')
                ->join('lead_activities', 'activities.id', '=', 'lead_activities.activity_id')
                ->join('leads', 'lead_activities.lead_id', '=', 'leads.id')
                ->where('activities.type', 'meeting')
                ->whereNull('leads.deleted_at')
                ->whereBetween('activities.schedule_from', [$startDate, $endDate]),
            $userId
        )
            ->distinct('activities.id')
            ->count('activities.id');

        $outcomeStats = $this->applyBdeWorkLeadScope(
            DB::table('leads')
                ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
                ->whereNull('leads.deleted_at')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query
                        ->whereBetween('leads.closed_at', [$startDate, $endDate])
                        ->orWhereBetween('leads.lead_disqualified_at', [$startDate, $endDate]);
                }),
            $userId
        )
            ->selectRaw("COUNT(DISTINCT CASE WHEN lead_pipeline_stages.code = 'won' THEN leads.id END) as won_leads")
            ->selectRaw("COUNT(DISTINCT CASE WHEN lead_pipeline_stages.code = 'lost' OR leads.lead_disqualification_reason IS NOT NULL OR leads.lost_reason IS NOT NULL THEN leads.id END) as lost_leads")
            ->first();

        $wonLeads = (int) ($outcomeStats->won_leads ?? 0);
        $lostLeads = (int) ($outcomeStats->lost_leads ?? 0);
        $outcomeLeads = $wonLeads + $lostLeads;

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ],
            'bde' => [
                'total_created'   => $totalCreated,
                'forwarded'       => $forwardedLeads,
                'followups'       => $followups,
            ],
            'calls' => [
                'total'                    => 0,
                'answered'                 => 0,
                'answer_rate'              => 0,
                'answered_average_per_day' => 0,
            ],
            'outcomes' => [
                'won'         => $wonLeads,
                'lost'        => $lostLeads,
                'won_percent' => $outcomeLeads ? round(($wonLeads / $outcomeLeads) * 100) : 0,
                'lost_percent'=> $outcomeLeads ? round(($lostLeads / $outcomeLeads) * 100) : 0,
            ],
            'meetings' => [
                'booked'       => $meetingsBooked,
                'assigned'     => $meetingsBooked,
                'attended'     => 0,
                'attend_rate'  => 0,
            ],
        ];
    }

    /**
     * Returns LGE LinkedIn request funnel and lead outcomes.
     */
    protected function lgeLinkedInSummary(Carbon $startDate, Carbon $endDate): array
    {
        $userId = auth()->guard('user')->id();
        $sourceAccessService = app(SourceAccessService::class);

        $requestStats = DB::table('linkedin_entry')
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw("SUM(CASE WHEN status IN ('accepted', 'response') THEN 1 ELSE 0 END) as accepted_requests")
            ->first();

        $totalRequests = (int) ($requestStats->total_requests ?? 0);
        $acceptedRequests = (int) ($requestStats->accepted_requests ?? 0);
        $forwardedColdLeads = Schema::hasTable('lead_forwards')
            ? (int) DB::table('lead_forwards')
                ->where('from_user_id', $userId)
                ->where('forward_type', LeadForwardService::TYPE_COLD_LEAD)
                ->whereBetween('forwarded_at', [$startDate, $endDate])
                ->count()
            : 0;

        $leadBase = DB::table('leads')
            ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNull('leads.deleted_at');

        $sourceAccessService->applyOriginatingCallingOwnerTableScope($leadBase, $userId);

        $leadBase
            ->whereBetween('leads.created_at', [$startDate, $endDate])
            ->whereExists(function ($query) use ($userId) {
                $query
                    ->selectRaw('1')
                    ->from('linkedin_entry')
                    ->where('linkedin_entry.user_id', $userId)
                    ->whereRaw(
                        "TRIM(TRAILING '/' FROM REPLACE(REPLACE(REPLACE(REPLACE(LOWER(linkedin_entry.url), 'https://www.', ''), 'http://www.', ''), 'https://', ''), 'http://', '')) = TRIM(TRAILING '/' FROM REPLACE(REPLACE(REPLACE(REPLACE(LOWER(leads.source_link), 'https://www.', ''), 'http://www.', ''), 'https://', ''), 'http://', ''))"
                    );
            });

        $leadStats = (clone $leadBase)
            ->selectRaw('COUNT(DISTINCT leads.id) as responses')
            ->first();

        $responses = (int) ($leadStats->responses ?? 0);

        $outcomeQuery = DB::table('leads')
            ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNull('leads.deleted_at')
            ->whereNotNull('leads.closed_at');

        $sourceAccessService->applyOriginatingCallingOwnerTableScope($outcomeQuery, $userId);

        $outcomeQuery
            ->whereBetween('leads.closed_at', [$startDate, $endDate])
            ->whereIn('lead_pipeline_stages.code', ['won', 'lost']);

        $outcomeStats = $outcomeQuery
            ->selectRaw("COUNT(DISTINCT CASE WHEN lead_pipeline_stages.code = 'won' THEN leads.id END) as won_leads")
            ->selectRaw("COUNT(DISTINCT CASE WHEN lead_pipeline_stages.code = 'lost' THEN leads.id END) as lost_leads")
            ->first();

        $wonLeads = (int) ($outcomeStats->won_leads ?? 0);
        $lostLeads = (int) ($outcomeStats->lost_leads ?? 0);
        $outcomeLeads = $wonLeads + $lostLeads;

        $meetingCount = DB::table('activities')
            ->join('lead_activities', 'activities.id', '=', 'lead_activities.activity_id')
            ->join('leads', 'lead_activities.lead_id', '=', 'leads.id')
            ->where('activities.type', 'meeting')
            ->whereNull('leads.deleted_at');

        $sourceAccessService->applyOriginatingCallingOwnerTableScope($meetingCount, $userId);

        $meetingCount = $meetingCount
            ->whereBetween('activities.schedule_from', [$startDate, $endDate])
            ->distinct('activities.id')
            ->count('activities.id');

        return [
            'type' => 'linkedin',
            'period' => [
                'start' => $startDate->toDateString(),
                'end'   => $endDate->toDateString(),
            ],
            'linkedin' => [
                'requests'        => $totalRequests,
                'accepted'        => $acceptedRequests,
                'responses'       => $responses,
                'forwarded'        => $forwardedColdLeads,
                'acceptance_rate' => $totalRequests ? round(($acceptedRequests / $totalRequests) * 100, 1) : 0,
                'response_rate'   => $totalRequests ? round(($responses / $totalRequests) * 100, 1) : 0,
            ],
            'meetings' => [
                'booked'  => (int) $meetingCount,
                'percent' => $responses ? round(((int) $meetingCount / $responses) * 100, 1) : 0,
            ],
            'outcomes' => [
                'won'          => $wonLeads,
                'lost'         => $lostLeads,
                'won_percent'  => $outcomeLeads ? round(($wonLeads / $outcomeLeads) * 100) : 0,
                'lost_percent' => $outcomeLeads ? round(($lostLeads / $outcomeLeads) * 100) : 0,
            ],
        ];
    }

    /**
     * Returns compact SDR lead work queues.
     */
    public function leadSections(): JsonResponse
    {
        $this->ensureCallingDashboardAccess();

        $data = request()->validate([
            'date' => ['nullable', 'date'],
        ]);

        $userId = auth()->guard('user')->id();
        $calendarDate = ! empty($data['date'])
            ? Carbon::parse($data['date'])
            : Carbon::now();
        $todayStart = $calendarDate->copy()->startOfDay();
        $todayEnd = $calendarDate->copy()->endOfDay();
        $sourceAccessService = app(SourceAccessService::class);
        $variant = request()->query('variant', 'sdr');

        $this->abortUnlessDashboardRole($variant);

        $showUsFeatures = in_array($variant, ['sdr', 'lead_clouser'], true);
        $showMeetings = request()->has('show_meetings')
            ? request()->boolean('show_meetings')
            : $variant === 'lead_clouser';

        if ($variant === 'bde') {
            return response()->json($this->bdeLeadSections($userId, $todayStart, $todayEnd));
        }

        $meetingsCount = 0;
        $todayMeetings = collect();

        if ($showMeetings) {
            $meetingsBase = DB::table('activities')
                ->leftJoin('activity_participants', 'activities.id', '=', 'activity_participants.activity_id')
                ->leftJoin('lead_activities', 'activities.id', '=', 'lead_activities.activity_id')
                ->leftJoin('leads', 'lead_activities.lead_id', '=', 'leads.id')
                ->leftJoin('lead_sources', 'leads.lead_source_id', '=', 'lead_sources.id')
                ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
                ->where('activities.type', 'meeting')
                ->whereBetween('activities.schedule_from', [$todayStart, $todayEnd])
                ->where(function ($query) use ($userId) {
                    $query
                        ->where('activities.user_id', $userId)
                        ->orWhere('activity_participants.user_id', $userId);
                });

            $this->applyVisibleLeadJoinScope($meetingsBase);

            $meetingsCount = (int) (clone $meetingsBase)
                ->selectRaw('COUNT(DISTINCT activities.id) as aggregate')
                ->value('aggregate');

            $todayMeetings = (clone $meetingsBase)
                ->select(
                    'activities.id',
                    'activities.title',
                    'activities.schedule_from',
                    'activities.schedule_to',
                    'activities.location',
                    'leads.id as lead_id',
                    'persons.name as person_name',
                    'persons.city as person_city',
                    'persons.state as person_state',
                    'persons.country as person_country',
                    'persons.timezone as person_timezone',
                    'lead_sources.name as source_name'
                )
                ->orderBy('activities.schedule_from')
                ->get()
                ->unique('id')
                ->values()
                ->map(function ($activity) use ($showUsFeatures) {
                    $leadUrl = $activity->lead_id ? lead_route_for_user('view', $activity->lead_id) : null;

                    return $this->mapDashboardCalendarItem([
                        'id'         => 'meeting-'.$activity->id,
                        'type'       => 'Meeting',
                        'source'     => $activity->source_name,
                        'title'      => $activity->title ?: 'Meeting',
                        'person'     => $activity->person_name,
                        'city'       => $activity->person_city,
                        'state'      => $activity->person_state,
                        'country'    => $activity->person_country,
                        'timezone'   => $activity->person_timezone,
                        'fallback_meta' => $activity->location ?: 'Scheduled meeting',
                        'at'         => $activity->schedule_from,
                        'url'        => route('admin.activities.edit', $activity->id),
                        'lead_url'   => $leadUrl,
                    ], $showUsFeatures);
                });
        }

        $followupsBase = DB::table('leads')
            ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
            ->leftJoin('organizations', 'persons.organization_id', '=', 'organizations.id')
            ->leftJoin('lead_sources', 'leads.lead_source_id', '=', 'lead_sources.id')
            ->whereNull('leads.deleted_at')
            ->where('leads.user_id', $userId)
            ->whereNotNull('leads.next_followup_date')
            ->whereBetween('leads.next_followup_date', [$todayStart, $todayEnd]);

        $sourceAccessService->applyLeadTableScope($followupsBase);

        $followupsCount = (clone $followupsBase)->count('leads.id');

        $todayFollowups = (clone $followupsBase)
            ->select(
                'leads.id',
                'leads.title',
                'leads.next_followup_date',
                'persons.name as person_name',
                'persons.city as person_city',
                'persons.state as person_state',
                'persons.country as person_country',
                'persons.timezone as person_timezone',
                'organizations.name as organization_name',
                'lead_sources.name as source_name'
            )
            ->orderBy('leads.next_followup_date')
            ->get()
            ->map(function ($lead) use ($showUsFeatures) {
                $leadUrl = lead_route_for_user('view', $lead->id);

                return $this->mapDashboardCalendarItem([
                    'id'         => 'followup-'.$lead->id,
                    'type'       => 'Follow-up',
                    'source'     => $lead->source_name,
                    'title'      => $lead->title,
                    'person'     => $lead->person_name,
                    'city'       => $lead->person_city,
                    'state'      => $lead->person_state,
                    'country'    => $lead->person_country,
                    'timezone'   => $lead->person_timezone,
                    'fallback_meta' => $lead->organization_name ?: 'Lead follow-up',
                    'at'         => $lead->next_followup_date,
                    'url'        => $leadUrl,
                    'lead_url'   => $leadUrl,
                ], $showUsFeatures);
            });

        $calendar = $todayMeetings->merge($todayFollowups);

        if ($showUsFeatures) {
            $calendar = $calendar->sortBy([
                ['priority_rank', 'asc'],
                ['us_sort_at', 'asc'],
                ['sort_at', 'asc'],
            ]);
        } else {
            $calendar = $calendar->sortBy([
                ['sort_at', 'asc'],
            ]);
        }

        return response()->json([
            'summary' => [
                'meetings'  => (int) $meetingsCount,
                'followups' => (int) $followupsCount,
                'total'     => (int) $meetingsCount + (int) $followupsCount,
            ],
            'today_calendar' => $calendar->values(),
            'calendar_date' => $todayStart->toDateString(),
            'show_us_features' => $showUsFeatures,
            'show_meetings' => $showMeetings,
        ]);
    }

    protected function bdeLeadSections(int $userId, Carbon $todayStart, Carbon $todayEnd): array
    {
        $followupsBase = DB::table('leads')
            ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
            ->leftJoin('organizations', 'persons.organization_id', '=', 'organizations.id')
            ->leftJoin('lead_sources', 'leads.lead_source_id', '=', 'lead_sources.id')
            ->whereNull('leads.deleted_at')
            ->where('leads.user_id', $userId)
            ->whereNotNull('leads.next_followup_date')
            ->whereBetween('leads.next_followup_date', [$todayStart, $todayEnd]);

        $todayFollowups = (clone $followupsBase)
            ->select(
                'leads.id',
                'leads.title',
                'leads.next_followup_date',
                'persons.name as person_name',
                'persons.city as person_city',
                'persons.state as person_state',
                'persons.country as person_country',
                'persons.timezone as person_timezone',
                'organizations.name as organization_name',
                'lead_sources.name as source_name'
            )
            ->orderBy('leads.next_followup_date')
            ->get()
            ->map(function ($lead) {
                return $this->mapDashboardCalendarItem([
                    'id'            => 'followup-'.$lead->id,
                    'type'          => 'Follow-up',
                    'source'        => $lead->source_name,
                    'title'         => $lead->title,
                    'person'        => $lead->person_name,
                    'city'          => $lead->person_city,
                    'state'         => $lead->person_state,
                    'country'       => $lead->person_country,
                    'timezone'      => $lead->person_timezone,
                    'fallback_meta' => $lead->organization_name ?: 'Lead follow-up',
                    'at'            => $lead->next_followup_date,
                    'url'           => lead_route_for_user('view', $lead->id),
                    'lead_url'      => lead_route_for_user('view', $lead->id),
                ], false);
            });

        $calendar = $todayFollowups
            ->sortBy('sort_at')
            ->values();

        $followupsCount = $todayFollowups->count();

        return [
            'summary' => [
                'meetings'  => 0,
                'followups' => (int) $followupsCount,
                'total'     => $calendar->count(),
            ],
            'today_calendar' => $calendar,
            'calendar_date' => $todayStart->toDateString(),
            'show_us_features' => false,
            'show_meetings' => false,
        ];
    }

    protected function dashboardCalendarRange(string $viewType, ?string $date): array
    {
        $selectedDate = $date
            ? Carbon::parse($date, config('app.timezone'))
            : Carbon::now(config('app.timezone'));

        if ($viewType === 'month') {
            return [
                $selectedDate->copy()->startOfMonth()->startOfDay(),
                $selectedDate->copy()->endOfMonth()->endOfDay(),
            ];
        }

        return [
            $selectedDate->copy()->startOfWeek()->startOfDay(),
            $selectedDate->copy()->endOfWeek()->endOfDay(),
        ];
    }

    protected function dashboardCalendarMeetings(
        int $userId,
        Carbon $startDate,
        Carbon $endDate,
        bool $showUsFeatures,
    ) {
        $query = DB::table('activities')
            ->leftJoin('activity_participants', 'activities.id', '=', 'activity_participants.activity_id')
            ->leftJoin('lead_activities', 'activities.id', '=', 'lead_activities.activity_id')
            ->leftJoin('leads', 'lead_activities.lead_id', '=', 'leads.id')
            ->leftJoin('lead_sources', 'leads.lead_source_id', '=', 'lead_sources.id')
            ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
            ->where('activities.type', 'meeting')
            ->where(function ($query) use ($startDate, $endDate) {
                $query
                    ->whereBetween('activities.schedule_from', [$startDate, $endDate])
                    ->orWhereBetween('activities.schedule_to', [$startDate, $endDate])
                    ->orWhere(function ($query) use ($startDate, $endDate) {
                        $query
                            ->where('activities.schedule_from', '<=', $startDate)
                            ->where('activities.schedule_to', '>=', $endDate);
                    });
            })
            ->where(function ($query) use ($userId) {
                $query
                    ->where('activities.user_id', $userId)
                    ->orWhere('activity_participants.user_id', $userId);
            });

        $this->applyVisibleLeadJoinScope($query);

        return $query
            ->select(
                'activities.id',
                'activities.title',
                'activities.comment',
                'activities.schedule_from',
                'activities.schedule_to',
                'activities.location',
                'leads.id as lead_id',
                'leads.title as lead_title',
                'persons.name as person_name',
                'persons.city as person_city',
                'persons.state as person_state',
                'persons.country as person_country',
                'persons.timezone as person_timezone',
                'lead_sources.name as source_name'
            )
            ->orderBy('activities.schedule_from')
            ->get()
            ->unique('id')
            ->values()
            ->map(function ($activity) use ($showUsFeatures) {
                $leadUrl = $activity->lead_id ? lead_route_for_user('view', $activity->lead_id) : null;
                $startsAt = Carbon::parse($activity->schedule_from, config('app.timezone'));
                $endsAt = $activity->schedule_to
                    ? Carbon::parse($activity->schedule_to, config('app.timezone'))
                    : $startsAt->copy()->addHour();

                $item = $this->mapDashboardCalendarItem([
                    'id'            => 'meeting-'.$activity->id,
                    'type'          => 'Meeting',
                    'source'        => $activity->source_name,
                    'title'         => $activity->lead_title ?: ($activity->title ?: 'Meeting'),
                    'person'        => $activity->person_name,
                    'city'          => $activity->person_city,
                    'state'         => $activity->person_state,
                    'country'       => $activity->person_country,
                    'timezone'      => $activity->person_timezone,
                    'fallback_meta' => $activity->location ?: 'Scheduled meeting',
                    'at'            => $activity->schedule_from,
                    'url'           => route('admin.activities.edit', $activity->id),
                    'lead_url'      => $leadUrl,
                ], $showUsFeatures);

                return array_merge($item, [
                    'activity_id'   => $activity->id,
                    'lead_id'       => $activity->lead_id,
                    'lead_title'    => $activity->lead_title,
                    'description'   => str($activity->comment ?: $activity->location ?: '')->limit(120)->toString(),
                    'schedule_from' => $startsAt->timezone(config('app.timezone'))->toIso8601String(),
                    'schedule_to'   => $endsAt->timezone(config('app.timezone'))->toIso8601String(),
                    'date'          => $startsAt->timezone(config('app.timezone'))->toDateString(),
                    'time_label'    => $startsAt->timezone(config('app.timezone'))->format('g:i A'),
                ]);
            });
    }

    protected function dashboardCalendarFollowups(
        int $userId,
        Carbon $startDate,
        Carbon $endDate,
        bool $showUsFeatures,
    ) {
        $sourceAccessService = app(SourceAccessService::class);

        $query = DB::table('leads')
            ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
            ->leftJoin('organizations', 'persons.organization_id', '=', 'organizations.id')
            ->leftJoin('lead_sources', 'leads.lead_source_id', '=', 'lead_sources.id')
            ->whereNull('leads.deleted_at')
            ->where('leads.user_id', $userId)
            ->whereNotNull('leads.next_followup_date')
            ->whereBetween('leads.next_followup_date', [$startDate, $endDate]);

        $sourceAccessService->applyLeadTableScope($query);

        return $query
            ->select(
                'leads.id',
                'leads.title',
                'leads.next_followup_date',
                'leads.followup_notes',
                'persons.name as person_name',
                'persons.city as person_city',
                'persons.state as person_state',
                'persons.country as person_country',
                'persons.timezone as person_timezone',
                'organizations.name as organization_name',
                'lead_sources.name as source_name'
            )
            ->orderBy('leads.next_followup_date')
            ->get()
            ->map(function ($lead) use ($showUsFeatures) {
                $leadUrl = lead_route_for_user('view', $lead->id);
                $startsAt = Carbon::parse($lead->next_followup_date, config('app.timezone'));

                $item = $this->mapDashboardCalendarItem([
                    'id'            => 'followup-'.$lead->id,
                    'type'          => 'Follow-up',
                    'source'        => $lead->source_name,
                    'title'         => $lead->title,
                    'person'        => $lead->person_name,
                    'city'          => $lead->person_city,
                    'state'         => $lead->person_state,
                    'country'       => $lead->person_country,
                    'timezone'      => $lead->person_timezone,
                    'fallback_meta' => $lead->organization_name ?: 'Lead follow-up',
                    'at'            => $lead->next_followup_date,
                    'url'           => $leadUrl,
                    'lead_url'      => $leadUrl,
                ], $showUsFeatures);

                return array_merge($item, [
                    'activity_id'   => null,
                    'lead_id'       => $lead->id,
                    'lead_title'    => $lead->title,
                    'description'   => str($lead->followup_notes ?: $lead->organization_name ?: '')->limit(120)->toString(),
                    'schedule_from' => $startsAt->timezone(config('app.timezone'))->toIso8601String(),
                    'schedule_to'   => null,
                    'date'          => $startsAt->timezone(config('app.timezone'))->toDateString(),
                    'time_label'    => $startsAt->timezone(config('app.timezone'))->format('g:i A'),
                ]);
            });
    }

    /**
     * Show all US state times.
     */
    public function usTimezones(): View
    {
        if (
            ! bouncer()->hasPermission('sdr_dashboard')
            && ! bouncer()->hasPermission('lead_clouser_dashboard')
        ) {
            abort(401);
        }

        return view('admin::dashboard.sdr.timezones')->with([
            'stateTimezones' => $this->usStateTimezoneService->allStates(),
        ]);
    }

    /**
     * Allow SDR/LGE dashboard API access when the user has either calling dashboard permission.
     */
    protected function ensureCallingDashboardAccess(): void
    {
        if (
            bouncer()->hasPermission('sdr_dashboard')
            || bouncer()->hasPermission('bde_dashboard')
            || bouncer()->hasPermission('lead_clouser_dashboard')
            || bouncer()->hasPermission('lge_dashboard')
        ) {
            return;
        }

        abort(401);
    }

    protected function ensureDashboardCalendarAccess(): void
    {
        if ($this->canViewDashboardCalendar()) {
            return;
        }

        abort(403);
    }

    protected function canViewDashboardCalendar(): bool
    {
        return bouncer()->hasPermission('role_dashboard_calendar_view');
    }

    protected function abortUnlessDashboardRole(string $variant): void
    {
        if ($this->isPrimaryAdminUser()) {
            abort(403);
        }

        $sourceAccessService = app(SourceAccessService::class);

        $allowed = match ($variant) {
            'bde'          => $sourceAccessService->isBdeUser(),
            'sdr'          => $sourceAccessService->isSdrUser(),
            'lge'          => $sourceAccessService->isLgeUser(),
            'lead_clouser' => $sourceAccessService->isLeadCloserUser(),
            default        => false,
        };

        if (! $allowed) {
            abort(403);
        }
    }

    protected function isPrimaryAdminUser(): bool
    {
        $user = auth()->guard('user')->user();

        if (! $user || empty($user->role_id)) {
            return false;
        }

        $role = DB::table('roles')->where('id', (int) $user->role_id)->first(['name', 'permission_type']);

        if (! $role) {
            return false;
        }

        $roleName = strtolower(trim((string) $role->name));

        return $role->permission_type === 'all'
            || in_array($roleName, ['admin', 'administrator', 'super admin'], true);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function mapDashboardCalendarItem(array $item, bool $showUsFeatures): array
    {
        $at = Carbon::parse($item['at']);
        $addressLabel = $this->formatPersonAddressLabel([
            'city'    => $item['city'] ?? null,
            'state'   => $item['state'] ?? null,
            'country' => $item['country'] ?? null,
        ]);

        $payload = [
            'id'                 => $item['id'],
            'type'               => $item['type'],
            'source'             => $item['source'],
            'source_group'       => $this->sourceGroup($item['source'] ?? null),
            'title'              => $item['title'],
            'person'             => $item['person'],
            'address'            => $addressLabel,
            'meta'               => $addressLabel ?: ($item['fallback_meta'] ?? ''),
            'sort_at'            => $at->timestamp,
            'url'                => $item['url'],
            'lead_url'           => $item['lead_url'] ?? null,
            'in_priority_window' => false,
            'priority_rank'      => 1,
            'us_sort_at'         => $at->timestamp,
            'time_us'            => null,
        ];

        if ($showUsFeatures) {
            $usTimezone = ($item['timezone'] ?? null)
                ?: $this->usStateTimezoneService->timezoneForState($item['state'] ?? null);
            $dual = $this->usStateTimezoneService->formatDualTime($item['at'], $usTimezone);
            $priority = $this->usStateTimezoneService->priorityWindowSortMeta($item['at'], $usTimezone);

            $payload['time'] = $dual['label'];
            $payload['time_local'] = $dual['local'];
            $payload['time_us'] = $dual['us'];
            $payload['in_priority_window'] = $priority['in_priority_window'];
            $payload['priority_rank'] = $priority['priority_rank'];
            $payload['us_sort_at'] = $priority['us_sort_at'];
        } else {
            $local = $at->timezone(config('app.timezone'))->format('g:i A');
            $payload['time'] = $local;
            $payload['time_local'] = $local;
        }

        return $payload;
    }

    protected function applyVisibleLeadJoinScope($query): void
    {
        if (app(SourceAccessService::class)->isAdmin()) {
            return;
        }

        $query->where(function ($query) {
            $query
                ->whereNull('leads.id')
                ->orWhereNull('leads.lead_disqualification_reason');
        });
    }

    protected function applyBdeWorkLeadScope($query, int $userId)
    {
        return $query->where(function ($query) use ($userId) {
            $query->where('leads.user_id', $userId);

            $this->applyBdeCreatedLeadOrScope($query, $userId);

            if (Schema::hasTable('lead_forwards')) {
                $query->orWhereExists(function ($query) use ($userId) {
                    $query
                        ->selectRaw('1')
                        ->from('lead_forwards')
                        ->whereColumn('lead_forwards.lead_id', 'leads.id')
                        ->where('lead_forwards.from_user_id', $userId);
                });
            }
        });
    }

    protected function applyBdeCreatedLeadScope($query, int $userId)
    {
        return $query->where(function ($query) use ($userId) {
            if (Schema::hasColumn('leads', 'created_by')) {
                $query->where('leads.created_by', $userId);

                return;
            }

            $query->where('leads.user_id', $userId);

            $this->applyBdeCreatedLeadOrScope($query, $userId);
        });
    }

    protected function applyBdeCreatedLeadOrScope($query, int $userId): void
    {
        if (Schema::hasColumn('leads', 'created_by')) {
            $query->orWhere('leads.created_by', $userId);

            return;
        }

        $query->orWhereExists(function ($query) use ($userId) {
            $query
                ->selectRaw('1')
                ->from('lead_activities')
                ->join('activities', 'activities.id', '=', 'lead_activities.activity_id')
                ->whereColumn('lead_activities.lead_id', 'leads.id')
                ->where('activities.type', 'system')
                ->where('activities.title', trans('admin::app.activities.created'))
                ->where('activities.user_id', $userId);
        });
    }

    protected function sourceGroup(?string $sourceName): string
    {
        // Warm Lead is a tag; "warm" leads are any source other than Cold Call.
        return strcasecmp((string) $sourceName, 'Cold Call') === 0
            ? 'cold'
            : 'warm';
    }

    protected function dashboardActivityTypeLabel(?string $type): string
    {
        return match ($type) {
            'meeting' => 'Meeting',
            'call'    => 'Call',
            'note'    => 'Note',
            'file'    => 'File',
            default   => 'Activity',
        };
    }

    /**
     * Build a compact US address label (city, state) for dashboard rows.
     */
    protected function formatPersonAddressLabel(mixed $address): ?string
    {
        if (is_string($address)) {
            $decoded = json_decode($address, true);
            $address = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if (! is_array($address)) {
            return null;
        }

        $parts = array_filter([
            trim((string) ($address['city'] ?? '')),
            trim((string) ($address['state'] ?? '')),
        ]);

        if (empty($parts)) {
            return null;
        }

        $label = implode(', ', $parts);

        $country = strtoupper(trim((string) ($address['country'] ?? '')));

        if ($country !== '' && ! in_array($country, ['US', 'USA', 'UNITED STATES'], true)) {
            $label .= ' · '.$country;
        }

        return $label;
    }

    protected function periodRange(string $period, ?string $startDate = null, ?string $endDate = null): array
    {
        if ($startDate || $endDate) {
            $start = $startDate
                ? Carbon::parse($startDate)->startOfDay()
                : Carbon::now()->startOfDay();

            $end = $endDate
                ? Carbon::parse($endDate)->endOfDay()
                : Carbon::parse($start)->endOfDay();

            return [$start, $end];
        }

        return match ($period) {
            'week'  => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            default => [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()],
        };
    }

}
