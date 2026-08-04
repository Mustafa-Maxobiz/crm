<?php

namespace Webkul\Admin\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Attribute\Repositories\AttributeValueRepository;
use Webkul\Core\Contracts\Validations\Decimal;
use Webkul\Lead\Services\SourceAccessService;

class LeadForm extends FormRequest
{
    /**
     * @var array
     */
    protected $rules = [];

    /**
     * Create a new form request instance.
     *
     * @return void
     */
    public function __construct(
        protected AttributeRepository $attributeRepository,
        protected AttributeValueRepository $attributeValueRepository,
        protected SourceAccessService $sourceAccessService,
    ) {}

    /**
     * Determine if the product is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        foreach (['leads', 'persons'] as $key => $entityType) {
            $attributes = $this->attributeRepository->scopeQuery(function ($query) use ($entityType) {
                $attributeCodes = $entityType == 'persons'
                    ? array_keys(request('person') ?? [])
                    : array_keys(request()->all());

                $query = $query->whereIn('code', $attributeCodes)
                    ->where('entity_type', $entityType);

                if (request()->has('quick_add')) {
                    $query = $query->where('quick_add', 1);
                }

                return $query;
            })->get();

            foreach ($attributes as $attribute) {
                if ($entityType == 'persons') {
                    $attribute->code = 'person.'.$attribute->code;
                }

                $validations = [];

                if ($attribute->type == 'boolean') {
                    continue;
                } elseif ($attribute->type == 'address') {
                    if (! $attribute->is_required) {
                        continue;
                    }

                    $validations = [
                        $attribute->code.'.address'  => 'required',
                        $attribute->code.'.country'  => 'required',
                        $attribute->code.'.state'    => 'required',
                        $attribute->code.'.city'     => 'required',
                        $attribute->code.'.postcode' => 'required',
                    ];
                } elseif ($attribute->type == 'email') {
                    $validations = [
                        $attribute->code              => [$attribute->is_required ? 'required' : 'nullable'],
                        $attribute->code.'.*.value'   => [$attribute->is_required ? 'required' : 'nullable', 'email'],
                        $attribute->code.'.*.label'   => $attribute->is_required ? 'required' : 'nullable',
                    ];
                } elseif ($attribute->type == 'phone') {
                    $validations = [
                        $attribute->code              => [$attribute->is_required ? 'required' : 'nullable'],
                        $attribute->code.'.*.value'   => [$attribute->is_required ? 'required' : 'nullable'],
                        $attribute->code.'.*.label'   => $attribute->is_required ? 'required' : 'nullable',
                    ];
                } else {
                    $validations[$attribute->code] = [$attribute->is_required ? 'required' : 'nullable'];

                    if ($attribute->type == 'text' && $attribute->validation) {
                        array_push($validations[$attribute->code],
                            $attribute->validation == 'decimal'
                            ? new Decimal
                            : $attribute->validation
                        );
                    }

                    if ($attribute->type == 'price') {
                        array_push($validations[$attribute->code], new Decimal);
                    }
                }

                if ($attribute->is_unique) {
                    array_push($validations[in_array($attribute->type, ['email', 'phone'])
                        ? $attribute->code.'.*.value'
                        : $attribute->code
                    ], function ($field, $value, $fail) use ($attribute, $entityType) {
                        if (! $this->attributeValueRepository->isValueUnique(
                            $entityType == 'persons' ? request('person.id') : $this->id,
                            $attribute->entity_type,
                            $attribute,
                            request($field)
                        )
                        ) {
                            $fail('The value has already been taken.');
                        }
                    });
                }

                $this->rules = array_merge($this->rules, $validations);
            }
        }

        $this->rules['expected_close_date'] = request()->has('quick_add')
            ? ['nullable', 'date_format:Y-m-d']
            : [
                'nullable',
                'date_format:Y-m-d',
                'after:'.Carbon::yesterday()->format('Y-m-d'),
            ];

        return [
            ...$this->rules,
            'schedule_followup'            => ['nullable', 'boolean'],
            'next_followup_date'           => ['nullable', 'date'],
            'tags'                         => ['nullable', 'array'],
            'tags.*'                       => ['nullable', 'string', 'max:100'],
            'person.name'                  => ['nullable', 'string', 'max:100'],
            'person.emails'                => ['nullable', 'array'],
            'person.emails.*.value'        => ['nullable', 'email'],
            'person.contact_numbers'       => ['nullable', 'array'],
            'person.contact_numbers.*.value' => ['nullable'],
            'person.organization_id'       => ['nullable'],
            'person.organization_name'     => ['nullable', 'string', 'max:255'],
            'organization_id'              => ['nullable', 'integer', 'exists:organizations,id'],
            'organization_name'            => ['nullable', 'string', 'max:255'],
            'person.website'               => ['nullable', 'string', 'max:255'],
            'person.address'               => ['nullable', 'array'],
            'person.address.address'       => ['nullable', 'string'],
            'person.address.country'       => ['nullable', 'string'],
            'person.address.state'         => ['nullable', 'string'],
            'person.address.city'          => ['nullable', 'string'],
            'person.address.postcode'      => ['nullable', 'string'],
            'products'              => 'array',
            'products.*.product_id' => 'sometimes|required|exists:products,id',
            'products.*.name'       => 'required_with:products.*.product_id',
            'products.*.price'      => 'required_with:products.*.product_id',
            'products.*.quantity'   => 'required_with:products.*.product_id',
        ];
    }

    /**
     * Normalize company/person fields before validation.
     */
    protected function prepareForValidation(): void
    {
        $data = $this->all();

        // Legacy text company field → create-by-name.
        if (! empty($data['companies']) && empty($data['organization_id']) && empty($data['organization_name'])) {
            $data['organization_name'] = $data['companies'];
        }

        if (array_key_exists('organization_id', $data) && $data['organization_id'] === '') {
            $data['organization_id'] = null;
        }

        if (empty($data['organization_name'])) {
            unset($data['organization_name']);
        }

        unset($data['companies']);

        $person = $data['person'] ?? [];

        if (is_array($person) && array_key_exists('website', $person) && $person['website'] === '') {
            $person['website'] = null;
        }

        if (is_array($person) && array_key_exists('organization_id', $person) && $person['organization_id'] === '') {
            $person['organization_id'] = null;
        }

        if (is_array($person) && empty($person['organization_name'])) {
            unset($person['organization_name']);
        }

        // Mirror lead company onto person when person org not explicitly sent.
        if (
            is_array($person)
            && empty($person['organization_id'])
            && empty($person['organization_name'])
        ) {
            if (! empty($data['organization_id'])) {
                $person['organization_id'] = $data['organization_id'];
            } elseif (! empty($data['organization_name'])) {
                $person['organization_name'] = $data['organization_name'];
            }
        }

        $data['person'] = $person;

        $this->replace($data);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $subSourceId = request('lead_sub_source_id') ? (int) request('lead_sub_source_id') : null;

            if ($sourceId = request('lead_source_id')) {
                if (! $this->sourceAccessService->canUseLeadSourceSelection((int) $sourceId, $subSourceId)) {
                    $validator->errors()->add('lead_source_id', trans('admin::app.leads.source-access-denied'));
                }
            }

            if ($subSourceId) {
                if (! $this->sourceAccessService->canAccessSourceId($subSourceId)) {
                    $validator->errors()->add('lead_sub_source_id', trans('admin::app.leads.source-access-denied'));
                }
            }

            $organizationId = request('organization_id')
                ?: request('person.organization_id')
                ?: request()->input('person.organization_id');
            $organizationName = request('organization_name')
                ?: request('person.organization_name')
                ?: request()->input('person.organization_name');
            $isSdr = $this->sourceAccessService->isSdrUser();

            // New company by name is always allowed; SDR/LGE can reassign company on leads they edit.
            if (
                $organizationId
                && ! $organizationName
                && ! $isSdr
                && ! $this->sourceAccessService->canAccessOrganizationId((int) $organizationId)
            ) {
                $validator->errors()->add('organization_id', trans('admin::app.leads.company-access-denied'));
            }
        });
    }

    /**
     * Get the validation messages that apply to the request.
     */
    public function messages(): array
    {
        return [
            'products.*.product_id.exists'      => trans('admin::app.leads.selected-product-not-exist'),
            'products.*.name.required_with'     => trans('admin::app.leads.product-name-required'),
            'products.*.price.required_with'    => trans('admin::app.leads.product-price-required'),
            'products.*.quantity.required_with' => trans('admin::app.leads.product-quantity-required'),
        ];
    }
}
