<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Tag\Repositories\TagRepository;

class TagController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected ActivityRepository $activityRepository,
        protected TagRepository $tagRepository
    ) {}

    /**
     * Store a newly created resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function attach($id)
    {
        Event::dispatch('leads.tag.create.before', $id);

        $lead = $this->leadRepository->find($id);
        $tag = $this->tagRepository->find((int) request()->input('tag_id'));

        if ($response = $this->prepareNotAnsweredCallActivity($lead, $tag)) {
            return $response;
        }

        $sourceSync = $this->syncLeadSourceFromSourceTag($lead, $tag);

        if (! $lead->tags->contains(request()->input('tag_id'))) {
            $lead->tags()->attach(request()->input('tag_id'));
        }

        Event::dispatch('leads.tag.create.after', $lead);

        return response()->json([
            'message'          => trans('admin::app.leads.view.tags.create-success'),
            'detached_tag_ids' => $sourceSync['detached_tag_ids'],
            'source_changed'   => $sourceSync['source_changed'],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $leadId
     * @return \Illuminate\Http\Response
     */
    public function detach($leadId)
    {
        Event::dispatch('leads.tag.delete.before', $leadId);

        $lead = $this->leadRepository->find($leadId);

        $lead->tags()->detach(request()->input('tag_id'));

        Event::dispatch('leads.tag.delete.after', $lead);

        return response()->json([
            'message' => trans('admin::app.leads.view.tags.destroy-success'),
        ]);
    }

    /**
     * The SDR dashboard reads not-answered leads from call activities.
     */
    protected function prepareNotAnsweredCallActivity($lead, $tag): ?JsonResponse
    {
        if (strtolower(trim((string) $tag?->name)) !== 'not answer') {
            return null;
        }

        $now = Carbon::now();

        $activity = $lead->activities()
            ->where('type', 'call')
            ->where('is_done', 0)
            ->orderByDesc('schedule_from')
            ->orderByDesc('activities.id')
            ->first();

        if ($activity) {
            $this->activityRepository->update([
                'call_status'            => 'not_answered',
                'call_status_updated_at' => $now,
                'is_done'                => 0,
            ], $activity->id);

            return null;
        }

        if (! request()->filled('schedule_from')) {
            return response()->json([
                'message'                => 'Please add call attempt details before marking this lead as Not Answer.',
                'requires_call_activity' => true,
            ], 409);
        }

        request()->validate([
            'schedule_from' => ['required'],
            'schedule_to'   => ['required'],
            'comment'       => ['required', 'string', 'max:500'],
            'participants'  => ['required', 'array'],
        ]);

        if (! $this->hasParticipants(request('participants', []))) {
            return response()->json([
                'message' => 'Please select at least one participant.',
                'errors'  => [
                    'participants' => ['Please select at least one participant.'],
                ],
            ], 422);
        }

        $activity = $this->activityRepository->create([
            'title'                  => 'No Answer - '.$lead->title,
            'type'                   => 'call',
            'comment'                => request('comment'),
            'schedule_from'          => request('schedule_from'),
            'schedule_to'            => request('schedule_to'),
            'is_done'                => 0,
            'call_status'            => 'not_answered',
            'call_status_updated_at' => $now,
            'user_id'                => $lead->user_id ?: auth()->guard('user')->id(),
            'participants'           => request('participants', []),
        ]);

        $activity->leads()->syncWithoutDetaching([$lead->id]);

        return null;
    }

    /**
     * Source tags are exclusive and control the lead source.
     *
     * @return array{detached_tag_ids: array<int>, source_changed: bool}
     */
    protected function syncLeadSourceFromSourceTag($lead, $tag): array
    {
        $sourceName = $this->sourceNameForTag($tag);

        if (! $sourceName) {
            return [
                'detached_tag_ids' => [],
                'source_changed'   => false,
            ];
        }

        $sourceId = DB::table('lead_sources')
            ->where('name', $sourceName)
            ->value('id');

        if (! $sourceId) {
            return [
                'detached_tag_ids' => [],
                'source_changed'   => false,
            ];
        }

        $oppositeTag = $this->tagRepository
            ->getModel()
            ->newQuery()
            ->where('name', $this->oppositeSourceTagName($sourceName))
            ->first();

        $detachedTagIds = [];

        if ($oppositeTag) {
            $lead->tags()->detach($oppositeTag->id);
            $detachedTagIds[] = (int) $oppositeTag->id;
        }

        $sourceChanged = (int) $lead->lead_source_id !== (int) $sourceId;

        if ($sourceChanged) {
            $this->leadRepository->update([
                'entity_type'    => 'leads',
                'lead_source_id' => $sourceId,
            ], $lead->id, ['lead_source_id']);
        }

        return [
            'detached_tag_ids' => $detachedTagIds,
            'source_changed'   => $sourceChanged,
        ];
    }

    protected function sourceNameForTag($tag): ?string
    {
        return match (strtolower(trim((string) $tag?->name))) {
            'cold lead', 'cold call', 'cold calls' => 'Cold Call',
            'warm lead', 'warm leads'              => 'Warm Leads',
            default                                => null,
        };
    }

    protected function oppositeSourceTagName(string $sourceName): string
    {
        return $sourceName === 'Warm Leads'
            ? 'Cold Lead'
            : 'Warm Lead';
    }

    /**
     * Check if at least one user or person participant was selected.
     */
    protected function hasParticipants(array $participants): bool
    {
        foreach (['users', 'persons'] as $type) {
            if (! empty(array_filter($participants[$type] ?? []))) {
                return true;
            }
        }

        return false;
    }
}
