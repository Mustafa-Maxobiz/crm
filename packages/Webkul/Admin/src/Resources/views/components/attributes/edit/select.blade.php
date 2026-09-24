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
    $isDeDataScraperCreateSalesOwner = $attribute?->code === 'user_id'
        && $attribute?->lookup_type === 'users'
        && $routeName === 'admin.leads.de_data_scraper.create';
    $isLeadCreateSalesOwner = $attribute?->code === 'user_id'
        && $attribute?->lookup_type === 'users'
        && in_array($routeName, [
            'admin.leads.create',
            'admin.leads.bde.create',
            'admin.leads.sdr.create',
            'admin.leads.lge.create',
            'admin.leads.de_data_scraper.create',
            'admin.leads.lead_clouser.create',
        ], true);

    if ($isDeDataScraperCreateSalesOwner) {
        $currentUserId = (int) auth()->guard('user')->id();

        if (\Illuminate\Support\Facades\Schema::hasTable('user_roles')) {
            $options = \Illuminate\Support\Facades\DB::table('users')
                ->leftJoin('user_roles', 'user_roles.user_id', '=', 'users.id')
                ->leftJoin('roles as assigned_roles', 'assigned_roles.id', '=', 'user_roles.role_id')
                ->leftJoin('roles as primary_roles', 'primary_roles.id', '=', 'users.role_id')
                ->where('users.status', 1)
                ->groupBy('users.id', 'users.name', 'primary_roles.name')
                ->orderByRaw('users.id = ? desc', [$currentUserId])
                ->orderBy('users.name')
                ->get([
                    'users.id',
                    'users.name',
                    \Illuminate\Support\Facades\DB::raw('COALESCE(GROUP_CONCAT(DISTINCT assigned_roles.name ORDER BY assigned_roles.name SEPARATOR ", "), primary_roles.name) as role_name'),
                ]);
        } else {
            $options = \Illuminate\Support\Facades\DB::table('users')
                ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->where('users.status', 1)
                ->orderByRaw('users.id = ? desc', [$currentUserId])
                ->orderBy('users.name')
                ->get(['users.id', 'users.name', 'roles.name as role_name']);
        }
    }

    $roleNamesByUserId = $isLeadCreateSalesOwner
        ? ($isDeDataScraperCreateSalesOwner
            ? $options->pluck('role_name', 'id')
            : \Illuminate\Support\Facades\DB::table('users')
                ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                ->whereIn('users.id', $options->pluck('id')->filter()->values())
                ->pluck('roles.name', 'users.id'))
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
