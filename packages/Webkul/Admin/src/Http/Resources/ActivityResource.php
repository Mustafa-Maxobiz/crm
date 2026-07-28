<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'parent_id'     => $this->parent_id ?? null,
            'title'         => $this->title,
            'type'          => $this->type,
            'comment'       => $this->comment,
            'additional'    => is_array($this->resource->additional)
                ? $this->resource->additional
                : ($this->resource->additional ? json_decode($this->resource->additional, true) : null),
            'schedule_from' => $this->schedule_from,
            'schedule_to'   => $this->schedule_to,
            'is_done'       => $this->is_done,
            'call_status'   => $this->call_status,
            'call_status_updated_at' => $this->call_status_updated_at,
            'user'          => new UserResource($this->user),
            'files'         => ActivityFileResource::collection($this->files),
            'participants'  => ActivityParticipantResource::collection($this->participants),
            'location'      => $this->location,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
