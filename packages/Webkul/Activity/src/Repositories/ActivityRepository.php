<?php

namespace Webkul\Activity\Repositories;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Eloquent\Repository;

class ActivityRepository extends Repository
{
    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(
        protected FileRepository $fileRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Webkul\Activity\Contracts\Activity';
    }

    /**
     * Create pipeline.
     *
     * @return \Webkul\Activity\Contracts\Activity
     */
    public function create(array $data)
    {
        $activity = parent::create($data);

        if (isset($data['file'])) {
            $this->fileRepository->create([
                'name'        => $data['name'] ?? $data['file']->getClientOriginalName(),
                'path'        => $data['file']->store('activities/'.$activity->id),
                'activity_id' => $activity->id,
            ]);
        }

        if (! isset($data['participants'])) {
            return $activity;
        }

        foreach ($data['participants']['users'] ?? [] as $userId) {
            $activity->participants()->create([
                'user_id' => $userId,
            ]);
        }

        foreach ($data['participants']['persons'] ?? [] as $personId) {
            $activity->participants()->create([
                'person_id' => $personId,
            ]);
        }

        return $activity;
    }

    /**
     * Update pipeline.
     *
     * @param  int  $id
     * @param  string  $attribute
     * @return \Webkul\Activity\Contracts\Activity
     */
    public function update(array $data, $id, $attribute = 'id')
    {
        $activity = parent::update($data, $id);

        if (isset($data['participants'])) {
            $activity->participants()->delete();

            foreach ($data['participants']['users'] ?? [] as $userId) {
                /**
                 * In some cases, the component exists in an HTML form, and traditional HTML does not send an empty array.
                 * Therefore, we need to check for an empty string.
                 * This scenario occurs only when all participants are removed.
                 */
                if (empty($userId)) {
                    break;
                }

                $activity->participants()->create([
                    'user_id' => $userId,
                ]);
            }

            foreach ($data['participants']['persons'] ?? [] as $personId) {
                /**
                 * In some cases, the component exists in an HTML form, and traditional HTML does not send an empty array.
                 * Therefore, we need to check for an empty string.
                 * This scenario occurs only when all participants are removed.
                 */
                if (empty($personId)) {
                    break;
                }

                $activity->participants()->create([
                    'person_id' => $personId,
                ]);
            }
        }

        return $activity;
    }

    /**
     * @param  string  $dateRange
     * @return mixed
     */
    public function getActivities($dateRange)
    {
        $tablePrefix = \DB::getTablePrefix();

        return $this->select(
            'activities.id',
            'activities.created_at',
            'activities.title',
            'activities.type',
            'activities.schedule_from as start',
            'activities.schedule_to as end',
            'activities.is_done',
            'activities.meeting_attended',
            'users.name as user_name',
        )
            ->addSelect(\DB::raw('IF('.$tablePrefix.'activities.is_done, "done", "") as class'))
            ->addSelect(\DB::raw('CASE WHEN '.$tablePrefix.'activities.type = "meeting" AND '.$tablePrefix.'activities.is_done = 1 AND '.$tablePrefix.'activities.meeting_attended = 1 THEN "#16A34A" ELSE NULL END as background'))
            ->addSelect(\DB::raw('"#ffffff" as textColor'))
            ->leftJoin('activity_participants', 'activities.id', '=', 'activity_participants.activity_id')
            ->leftJoin('users', 'activities.user_id', '=', 'users.id')
            ->whereIn('type', ['call', 'meeting', 'lunch'])
            ->where(function ($query) {
                $query
                    ->where('activities.type', '!=', 'meeting')
                    ->orWhere(function ($meetingQuery) {
                        $meetingQuery
                            ->where('activities.type', 'meeting')
                            ->where(function ($statusQuery) {
                                $statusQuery
                                    ->where('activities.is_done', 0)
                                    ->orWhere(function ($attendedQuery) {
                                        $attendedQuery
                                            ->where('activities.is_done', 1)
                                            ->where('activities.meeting_attended', 1);
                                    });
                            });
                    });
            })
            ->whereBetween('activities.schedule_from', $dateRange)
            ->where(function ($query) {
                if ($userIds = bouncer()->getAuthorizedUserIds()) {
                    $query->whereIn('activities.user_id', $userIds)
                        ->orWhereIn('activity_participants.user_id', $userIds);
                }
            })
            ->distinct()
            ->get();
    }

    /**
     * @param  string  $startFrom
     * @param  string  $endFrom
     * @param  array  $participants
     * @param  int  $id
     * @return bool
     */
    public function isDurationOverlapping($startFrom, $endFrom, $participants, $id)
    {
        $userIds = collect($participants['users'] ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $personIds = collect($participants['persons'] ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($userIds) && empty($personIds)) {
            return false;
        }

        $queryBuilder = DB::table('activities')
            ->leftJoin('activity_participants', 'activities.id', '=', 'activity_participants.activity_id')
            ->where('activities.type', 'meeting')
            ->where('activities.is_done', 0)
            ->where(function ($query) {
                $query->whereNull('activities.call_status')
                    ->orWhere('activities.call_status', 'scheduled');
            })
            ->where('activities.schedule_from', '<', $endFrom)
            ->where('activities.schedule_to', '>', $startFrom)
            ->where(function ($query) use ($userIds, $personIds) {
                $hasUserCondition = false;

                if (! empty($userIds)) {
                    $query
                        ->whereIn('activities.user_id', $userIds)
                        ->orWhereIn('activity_participants.user_id', $userIds);

                    $hasUserCondition = true;
                }

                if (! empty($personIds)) {
                    $method = $hasUserCondition ? 'orWhereIn' : 'whereIn';

                    $query->{$method}('activity_participants.person_id', $personIds);
                }
            });

        if (! is_null($id)) {
            $queryBuilder->where('activities.id', '!=', $id);
        }

        return $queryBuilder
            ->select('activities.id')
            ->distinct()
            ->exists();
    }
}
