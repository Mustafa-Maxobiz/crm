<?php

namespace Webkul\Admin\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\Helpers\Dashboard;
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
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        if ($this->isSdrUser()) {
            $roleName = trim((string) auth()->guard('user')->user()?->role?->name);
            $dashboardTitle = strcasecmp($roleName, 'lge') === 0
                ? 'LGE Dashboard'
                : 'SDR Dashboard';

            return view('admin::dashboard.sdr.index')->with([
                'stateTimezones' => $this->usStateTimezoneService->allStates(),
                'dashboardTitle' => $dashboardTitle,
            ]);
        }

        return view('admin::dashboard.index')->with([
            'startDate' => $this->dashboardHelper->getStartDate(),
            'endDate'   => $this->dashboardHelper->getEndDate(),
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
        $data = request()->validate([
            'period'     => ['nullable', 'in:day,week,month'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date'],
        ]);

        [$startDate, $endDate] = $this->periodRange(
            $data['period'] ?? 'day',
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
        );

        $userId = auth()->guard('user')->id();
        $sourceAccessService = app(SourceAccessService::class);

        $activityQuery = DB::table('activities')
            ->leftJoin('activity_participants', 'activities.id', '=', 'activity_participants.activity_id')
            ->leftJoin('lead_activities', 'activities.id', '=', 'lead_activities.activity_id')
            ->leftJoin('leads', 'lead_activities.lead_id', '=', 'leads.id')
            ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
            ->whereIn('activities.type', ['call', 'meeting'])
            ->whereBetween('activities.schedule_from', [$startDate, $endDate])
            ->where(function ($query) use ($userId) {
                $query
                    ->where('activities.user_id', $userId)
                    ->orWhere('activity_participants.user_id', $userId);
            });

        $sourceAccessService->applyLeadTableScope($activityQuery);

        $activityStats = $activityQuery
            ->selectRaw("COUNT(DISTINCT CASE WHEN activities.type = 'call' THEN activities.id END) as total_calls")
            ->selectRaw("COUNT(DISTINCT CASE WHEN activities.type = 'call' AND (activities.call_status = 'done' OR (activities.call_status IS NULL AND activities.is_done = 1)) THEN activities.id END) as answered_calls")
            ->selectRaw("COUNT(DISTINCT CASE WHEN activities.type = 'meeting' THEN activities.id END) as booked_meetings")
            ->first();

        $totalCalls = (int) ($activityStats->total_calls ?? 0);
        $answeredCalls = (int) ($activityStats->answered_calls ?? 0);
        $bookedMeetings = (int) ($activityStats->booked_meetings ?? 0);

        $leadQuery = DB::table('leads')
            ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
            ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNull('leads.deleted_at')
            ->where('leads.user_id', $userId)
            ->whereBetween('leads.updated_at', [$startDate, $endDate]);

        $sourceAccessService->applyLeadTableScope($leadQuery);

        $leadStats = $leadQuery
            ->selectRaw("SUM(CASE WHEN lead_pipeline_stages.code = 'won' THEN 1 ELSE 0 END) as won_leads")
            ->selectRaw("SUM(CASE WHEN lead_pipeline_stages.code = 'lost' THEN 1 ELSE 0 END) as lost_leads")
            ->first();

        $wonLeads = (int) ($leadStats->won_leads ?? 0);
        $lostLeads = (int) ($leadStats->lost_leads ?? 0);
        $outcomeLeads = $wonLeads + $lostLeads;
        $days = max(1, $startDate->diffInDays($endDate) + 1);

        return response()->json([
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
                'won_percent' => $outcomeLeads ? round(($wonLeads / $outcomeLeads) * 100, 1) : 0,
                'lost_percent'=> $outcomeLeads ? round(($lostLeads / $outcomeLeads) * 100, 1) : 0,
            ],
            'meetings' => [
                'booked' => $bookedMeetings,
            ],
        ]);
    }

    /**
     * Returns compact SDR lead work queues.
     */
    public function leadSections(): JsonResponse
    {
        $userId = auth()->guard('user')->id();
        $todayStart = Carbon::now()->startOfDay();
        $todayEnd = Carbon::now()->endOfDay();
        $sourceAccessService = app(SourceAccessService::class);

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
            ->map(function ($activity) {
                $usTimezone = $activity->person_timezone
                    ?: $this->usStateTimezoneService->timezoneForState($activity->person_state);
                $dual = $this->usStateTimezoneService->formatDualTime(
                    $activity->schedule_from,
                    $usTimezone
                );
                $priority = $this->usStateTimezoneService->priorityWindowSortMeta(
                    $activity->schedule_from,
                    $usTimezone
                );
                $addressLabel = $this->formatPersonAddressLabel([
                    'city'    => $activity->person_city,
                    'state'   => $activity->person_state,
                    'country' => $activity->person_country,
                ]);

                return [
                    'id'                 => 'meeting-'.$activity->id,
                    'type'               => 'Meeting',
                    'source'             => $activity->source_name,
                    'source_group'       => $this->sourceGroup($activity->source_name),
                    'title'              => $activity->title ?: 'Meeting',
                    'person'             => $activity->person_name,
                    'address'            => $addressLabel,
                    'meta'               => $addressLabel
                        ?: ($activity->location ?: 'Scheduled meeting'),
                    'time'               => $dual['label'],
                    'time_local'         => $dual['local'],
                    'time_us'            => $dual['us'],
                    'in_priority_window' => $priority['in_priority_window'],
                    'priority_rank'      => $priority['priority_rank'],
                    'us_sort_at'         => $priority['us_sort_at'],
                    'sort_at'            => Carbon::parse($activity->schedule_from)->timestamp,
                    'url'                => route('admin.activities.edit', $activity->id),
                    'lead_url'           => $activity->lead_id ? route('admin.leads.view', $activity->lead_id) : null,
                ];
            });

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
            ->map(function ($lead) {
                $usTimezone = $lead->person_timezone
                    ?: $this->usStateTimezoneService->timezoneForState($lead->person_state);
                $dual = $this->usStateTimezoneService->formatDualTime(
                    $lead->next_followup_date,
                    $usTimezone
                );
                $priority = $this->usStateTimezoneService->priorityWindowSortMeta(
                    $lead->next_followup_date,
                    $usTimezone
                );
                $addressLabel = $this->formatPersonAddressLabel([
                    'city'    => $lead->person_city,
                    'state'   => $lead->person_state,
                    'country' => $lead->person_country,
                ]);

                return [
                    'id'                 => 'followup-'.$lead->id,
                    'type'               => 'Follow-up',
                    'source'             => $lead->source_name,
                    'source_group'       => $this->sourceGroup($lead->source_name),
                    'title'              => $lead->title,
                    'person'             => $lead->person_name,
                    'address'            => $addressLabel,
                    'meta'               => $addressLabel
                        ?: ($lead->organization_name ?: 'Lead follow-up'),
                    'time'               => $dual['label'],
                    'time_local'         => $dual['local'],
                    'time_us'            => $dual['us'],
                    'in_priority_window' => $priority['in_priority_window'],
                    'priority_rank'      => $priority['priority_rank'],
                    'us_sort_at'         => $priority['us_sort_at'],
                    'sort_at'            => Carbon::parse($lead->next_followup_date)->timestamp,
                    'url'                => route('admin.leads.view', $lead->id),
                    'lead_url'           => route('admin.leads.view', $lead->id),
                ];
            });

        return response()->json([
            'summary' => [
                'meetings'  => (int) $meetingsCount,
                'followups' => (int) $followupsCount,
                'total'     => (int) $meetingsCount + (int) $followupsCount,
            ],
            'today_calendar' => $todayMeetings
                ->merge($todayFollowups)
                ->sortBy([
                    ['priority_rank', 'asc'],
                    ['us_sort_at', 'asc'],
                    ['sort_at', 'asc'],
                ])
                ->values(),
        ]);
    }

    /**
     * Show all US state times.
     */
    public function usTimezones(): View
    {
        return view('admin::dashboard.sdr.timezones')->with([
            'stateTimezones' => $this->usStateTimezoneService->allStates(),
        ]);
    }

    protected function isSdrUser(): bool
    {
        return app(SourceAccessService::class)->isSdrUser();
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

    protected function sourceGroup(?string $sourceName): string
    {
        // Warm Lead is a tag; "warm" leads are any source other than Cold Call.
        return strcasecmp((string) $sourceName, 'Cold Call') === 0
            ? 'cold'
            : 'warm';
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
