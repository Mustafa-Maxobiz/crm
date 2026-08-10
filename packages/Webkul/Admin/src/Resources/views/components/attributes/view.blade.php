@props([
    'customAttributes'     => [],
    'entity'               => null,
    'allowEdit'            => false,
    'url'                  => null,
    'lockedAttributeCodes' => [],
])

<div class="flex flex-col gap-1">
    @foreach ($customAttributes as $attribute)
        @if (view()->exists($typeView = 'admin::components.attributes.view.' . $attribute->type))
            @php
                $attributeAllowEdit = $allowEdit && ! in_array($attribute->code, $lockedAttributeCodes, true);
            @endphp

            <div class="grid grid-cols-[1fr_2fr] items-center gap-1">
                <div class="label dark:text-white">{{ $attribute->name }}</div>

                <div class="font-medium dark:text-white">
                    @if ($attribute->code === 'user_id' && isset($entity))
                        <span class="flex min-h-[34px] items-center pl-2.5">
                            {{ $entity->user?->name ?: '--' }}
                        </span>
                    @else
                        @include ($typeView, [
                            'attribute' => $attribute,
                            'value'     => isset($entity) ? $entity[$attribute->code] : null,
                            'allowEdit' => $attributeAllowEdit,
                            'url'       => $url,
                            'entity'    => $entity,
                        ])
                    @endif
                </div>
            </div>
        @endif
    @endforeach
</div>
