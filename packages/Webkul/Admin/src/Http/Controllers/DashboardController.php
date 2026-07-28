<?php

namespace Webkul\Admin\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Webkul\Admin\Helpers\Dashboard;
use Webkul\Lead\Services\SourceAccessService;

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
    public function __construct(protected Dashboard $dashboardHelper) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        if ($this->isSdrUser()) {
            return view('admin::dashboard.sdr.index')->with([
                'stateTimezones' => $this->usStateTimezones(),
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
                'lead_sources.name as source_name'
            )
            ->orderBy('activities.schedule_from')
            ->limit(8)
            ->get()
            ->unique('id')
            ->values()
            ->map(fn ($activity) => [
                'id'           => 'meeting-'.$activity->id,
                'type'         => 'Meeting',
                'source'       => $activity->source_name,
                'source_group' => $this->sourceGroup($activity->source_name),
                'title'        => $activity->title ?: 'Meeting',
                'person'       => $activity->person_name,
                'meta'         => $activity->location ?: 'Scheduled meeting',
                'time'         => Carbon::parse($activity->schedule_from)->format('h:i A'),
                'sort_at'      => Carbon::parse($activity->schedule_from)->timestamp,
                'url'          => route('admin.activities.edit', $activity->id),
                'lead_url'     => $activity->lead_id ? route('admin.leads.view', $activity->lead_id) : null,
            ]);

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
                'organizations.name as organization_name',
                'lead_sources.name as source_name'
            )
            ->orderBy('leads.next_followup_date')
            ->limit(8)
            ->get()
            ->map(fn ($lead) => [
                'id'           => 'followup-'.$lead->id,
                'type'         => 'Follow-up',
                'source'       => $lead->source_name,
                'source_group' => $this->sourceGroup($lead->source_name),
                'title'        => $lead->title,
                'person'       => $lead->person_name,
                'meta'         => $lead->organization_name ?: 'Lead follow-up',
                'time'         => Carbon::parse($lead->next_followup_date)->format('h:i A'),
                'sort_at'      => Carbon::parse($lead->next_followup_date)->timestamp,
                'url'          => route('admin.leads.view', $lead->id),
                'lead_url'     => route('admin.leads.view', $lead->id),
            ]);

        return response()->json([
            'summary' => [
                'meetings'  => (int) $meetingsCount,
                'followups' => (int) $followupsCount,
                'total'     => (int) $meetingsCount + (int) $followupsCount,
            ],
            'today_calendar' => $todayMeetings
                ->merge($todayFollowups)
                ->sortBy('sort_at')
                ->values(),
        ]);
    }

    /**
     * Show all US state times.
     */
    public function usTimezones(): View
    {
        return view('admin::dashboard.sdr.timezones')->with([
            'stateTimezones' => $this->usStateTimezones(),
        ]);
    }

    protected function isSdrUser(): bool
    {
        return strtolower((string) auth()->guard('user')->user()?->role?->name) === 'sdr';
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
        return strcasecmp((string) $sourceName, 'Warm Leads') === 0
            ? 'warm'
            : 'cold';
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

    protected function usStateTimezones(): array
    {
        return [
            ['state' => 'Alabama', 'abbr' => 'AL', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Alaska', 'abbr' => 'AK', 'timezone' => 'America/Anchorage', 'popular' => false],
            ['state' => 'Arizona', 'abbr' => 'AZ', 'timezone' => 'America/Phoenix', 'popular' => true],
            ['state' => 'Arkansas', 'abbr' => 'AR', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'California', 'abbr' => 'CA', 'timezone' => 'America/Los_Angeles', 'popular' => true],
            ['state' => 'Colorado', 'abbr' => 'CO', 'timezone' => 'America/Denver', 'popular' => true],
            ['state' => 'Connecticut', 'abbr' => 'CT', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'Delaware', 'abbr' => 'DE', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'Florida', 'abbr' => 'FL', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'Georgia', 'abbr' => 'GA', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'Hawaii', 'abbr' => 'HI', 'timezone' => 'Pacific/Honolulu', 'popular' => false],
            ['state' => 'Idaho', 'abbr' => 'ID', 'timezone' => 'America/Boise', 'popular' => false],
            ['state' => 'Illinois', 'abbr' => 'IL', 'timezone' => 'America/Chicago', 'popular' => true],
            ['state' => 'Indiana', 'abbr' => 'IN', 'timezone' => 'America/Indiana/Indianapolis', 'popular' => false],
            ['state' => 'Iowa', 'abbr' => 'IA', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Kansas', 'abbr' => 'KS', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Kentucky', 'abbr' => 'KY', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'Louisiana', 'abbr' => 'LA', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Maine', 'abbr' => 'ME', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'Maryland', 'abbr' => 'MD', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'Massachusetts', 'abbr' => 'MA', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'Michigan', 'abbr' => 'MI', 'timezone' => 'America/Detroit', 'popular' => true],
            ['state' => 'Minnesota', 'abbr' => 'MN', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Mississippi', 'abbr' => 'MS', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Missouri', 'abbr' => 'MO', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Montana', 'abbr' => 'MT', 'timezone' => 'America/Denver', 'popular' => false],
            ['state' => 'Nebraska', 'abbr' => 'NE', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Nevada', 'abbr' => 'NV', 'timezone' => 'America/Los_Angeles', 'popular' => true],
            ['state' => 'New Hampshire', 'abbr' => 'NH', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'New Jersey', 'abbr' => 'NJ', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'New Mexico', 'abbr' => 'NM', 'timezone' => 'America/Denver', 'popular' => false],
            ['state' => 'New York', 'abbr' => 'NY', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'North Carolina', 'abbr' => 'NC', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'North Dakota', 'abbr' => 'ND', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Ohio', 'abbr' => 'OH', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'Oklahoma', 'abbr' => 'OK', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Oregon', 'abbr' => 'OR', 'timezone' => 'America/Los_Angeles', 'popular' => true],
            ['state' => 'Pennsylvania', 'abbr' => 'PA', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'Rhode Island', 'abbr' => 'RI', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'South Carolina', 'abbr' => 'SC', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'South Dakota', 'abbr' => 'SD', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Tennessee', 'abbr' => 'TN', 'timezone' => 'America/Chicago', 'popular' => true],
            ['state' => 'Texas', 'abbr' => 'TX', 'timezone' => 'America/Chicago', 'popular' => true],
            ['state' => 'Utah', 'abbr' => 'UT', 'timezone' => 'America/Denver', 'popular' => true],
            ['state' => 'Vermont', 'abbr' => 'VT', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'Virginia', 'abbr' => 'VA', 'timezone' => 'America/New_York', 'popular' => true],
            ['state' => 'Washington', 'abbr' => 'WA', 'timezone' => 'America/Los_Angeles', 'popular' => true],
            ['state' => 'West Virginia', 'abbr' => 'WV', 'timezone' => 'America/New_York', 'popular' => false],
            ['state' => 'Wisconsin', 'abbr' => 'WI', 'timezone' => 'America/Chicago', 'popular' => false],
            ['state' => 'Wyoming', 'abbr' => 'WY', 'timezone' => 'America/Denver', 'popular' => false],
        ];
    }
}
