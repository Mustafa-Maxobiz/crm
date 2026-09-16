@props([
    'attribute'   => null,
    'value'       => null,
    'validations' => '',
    'disabled'    => false,
])

@php
    $options = $attribute->lookup_type
        ? app('Webkul\Attribute\Repositories\AttributeRepository')->getLookUpOptions($attribute->lookup_type)
        : $attribute->options()->orderBy('sort_order')->get();

    $isDisabled = (bool) $disabled;
    $routeName = request()->route()?->getName();
    $isLeadCreateSalesOwner = $attribute?->code === 'user_id'
        && $attribute?->lookup_type === 'users'
        && in_array($routeName, [
            'admin.leads.create',
            'admin.leads.bde.create',
            'admin.leads.sdr.create',
            'admin.leads.lge.create',
            'admin.leads.lead_clouser.create',
        ], true);

    $roleNamesByUserId = $isLeadCreateSalesOwner
        ? \Illuminate\Support\Facades\DB::table('users')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->whereIn('users.id', $options->pluck('id')->filter()->values())
            ->pluck('roles.name', 'users.id')
        : collect();
@endphp

<x-admin::form.control-group.control
    type="select"
    id="{{ $attribute->code }}"
    name="{{ $attribute->code }}"
    rules="{{ $validations }}"
    :label="$attribute->name"
    :placeholder="$attribute->name"
    :value="old($attribute->code) ?? $value"
    :disabled="$isDisabled"
    :class="$isDisabled ? 'cursor-not-allowed opacity-70' : ''"
>
    @foreach ($options as $option)
        <option value="{{ $option->id }}">
            @if ($isLeadCreateSalesOwner && $roleNamesByUserId->get($option->id))
                {!! e($option->name) !!} &mdash; {!! e($roleNamesByUserId->get($option->id)) !!}
            @else
                {{ $option->name }}
            @endif
        </option>
    @endforeach
</x-admin::form.control-group.control>
