<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
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
            'id'                   => $this->id,
            'title'                => $this->title,
            'lead_value'           => $this->lead_value,
            'formatted_lead_value' => core()->formatBasePrice($this->lead_value),
            'status'               => $this->status,
            'user_id'              => $this->user_id,
            'lead_owner_id'        => $this->lead_owner_id,
            'forwarded_from_current_user' => (bool) ($this->forwarded_from_current_user ?? false),
            'lead_pipeline_id'     => $this->lead_pipeline_id,
            'lead_pipeline_stage_id'=> $this->lead_pipeline_stage_id,
            'stage_code'           => $this->stage?->code,
            'stage_sort_order'     => $this->stage?->sort_order,
            'expected_close_date'  => $this->expected_close_date,
            'rotten_days'          => $this->rotten_days,
            'closed_at'            => $this->closed_at,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
            'person'               => $this->person ? new PersonResource($this->person) : (object)[
                'id' => null,
                'name' => '-',
                'emails' => [],
                'contact_numbers' => [],
                'organization' => null,
                'created_at' => null,
                'updated_at' => null,
            ],
            'user'                 => $this->user ? new UserResource($this->user) : null,
            'type'                 => $this->type ? new TypeResource($this->type) : (object)[
                'id' => null,
                'name' => '-',
                'created_at' => null,
                'updated_at' => null,
            ],
            'source'               => $this->source ? new SourceResource($this->source) : (object)[
                'id' => null,
                'name' => '-',
                'created_at' => null,
                'updated_at' => null,
            ],
            'sub_source'           => $this->subSource ? new SourceResource($this->subSource) : null,
            'pipeline'             => $this->pipeline ? new PipelineResource($this->pipeline) : null,
            'stage'                => $this->stage ? new StageResource($this->stage) : null,
            'tags'                 => TagResource::collection($this->tags),
        ];
    }
}
