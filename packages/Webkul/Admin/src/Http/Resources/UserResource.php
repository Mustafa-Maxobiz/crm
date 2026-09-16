<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        $roleName = $this->relationLoaded('role') ? $this->role?->name : null;
        $label = trim((string) $this->name).($roleName ? ' — '.$roleName : '');

        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'role_name'  => $roleName,
            'label'      => $label,
            'email'      => $this->email,
            'image'      => $this->image,
            'created_at' => $this->created_at,
            'updated_at' => $this->name,
        ];
    }
}
