<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
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

        if (! $lead->tags->contains(request()->input('tag_id'))) {
            $oldTags = $lead->tags->pluck('name')->sort()->values()->implode(', ');

            $lead->tags()->attach(request()->input('tag_id'));

            $newTags = $lead->fresh('tags')->tags->pluck('name')->sort()->values()->implode(', ');

            \Webkul\Lead\Models\Lead::storeSystemActivity(
                $lead,
                'Tags',
                $oldTags !== '' ? $oldTags : null,
                $newTags !== '' ? $newTags : null
            );
        }

        Event::dispatch('leads.tag.create.after', $lead);

        return response()->json([
            'message' => trans('admin::app.leads.view.tags.create-success'),
        ]);
    }

    /**
     * Replace the current tag on a lead with a new one.
     */
    public function replace(int $id): JsonResponse
    {
        $this->validate(request(), [
            'tag_id'     => ['required', 'integer', 'exists:tags,id'],
            'old_tag_id' => ['nullable', 'integer', 'exists:tags,id'],
        ]);

        Event::dispatch('leads.tag.create.before', $id);

        $lead = $this->leadRepository->findOrFail($id);
        $newTagId = (int) request()->input('tag_id');
        $oldTagId = request()->filled('old_tag_id')
            ? (int) request()->input('old_tag_id')
            : null;

        $tag = $this->tagRepository->find($newTagId);

        if ($response = $this->prepareNotAnsweredCallActivity($lead, $tag)) {
            return $response;
        }

        $oldTags = $lead->tags->pluck('name')->sort()->values()->implode(', ');

        if ($oldTagId && $oldTagId !== $newTagId) {
            $lead->tags()->detach($oldTagId);
        }

        if (! $lead->tags()->where('tags.id', $newTagId)->exists()) {
            $lead->tags()->attach($newTagId);
        }

        $lead = $lead->fresh('tags');
        $newTags = $lead->tags->pluck('name')->sort()->values()->implode(', ');

        \Webkul\Lead\Models\Lead::storeSystemActivity(
            $lead,
            'Tags',
            $oldTags !== '' ? $oldTags : null,
            $newTags !== '' ? $newTags : null
        );

        Event::dispatch('leads.tag.create.after', $lead);

        return response()->json([
            'message' => trans('admin::app.leads.view.tags.create-success'),
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

        $oldTags = $lead->tags->pluck('name')->sort()->values()->implode(', ');

        $lead->tags()->detach(request()->input('tag_id'));

        $newTags = $lead->fresh('tags')->tags->pluck('name')->sort()->values()->implode(', ');

        \Webkul\Lead\Models\Lead::storeSystemActivity(
            $lead,
            'Tags',
            $oldTags !== '' ? $oldTags : null,
            $newTags !== '' ? $newTags : null
        );

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
        if (strtolower(trim((string) $tag?->name)) !== 'not answered') {
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
                'message'                => 'Please add call attempt details before marking this lead as Not Answered.',
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
