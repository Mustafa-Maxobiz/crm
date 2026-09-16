<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
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
            'id'              => $this->id,
            'name'            => $this->name,
            'emails'          => $this->safeArrayCast('emails'),
            'contact_numbers' => $this->safeArrayCast('contact_numbers'),
            'address'         => $this->address,
            'address_line'    => $this->address_line,
            'city'            => $this->city,
            'state'           => $this->state,
            'country'         => $this->country,
            'postcode'        => $this->postcode,
            'timezone'        => $this->timezone,
            'website'         => $this->website,
            'organization'    => $this->organization ? new OrganizationResource($this->organization) : null,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ];
    }

    /**
     * Safely return JSON-cast array fields.
     */
    protected function safeArrayCast(string $key): array
    {
        $rawValue = $this->{$key};

        if (is_array($rawValue)) {
            return $rawValue;
        }

        if (is_string($rawValue) && $rawValue !== '') {
            $decodedValue = json_decode($rawValue, true);

            return is_array($decodedValue) ? $decodedValue : [];
        }

        return [];
    }
}
