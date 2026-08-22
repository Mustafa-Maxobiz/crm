{!! view_render_event('admin.leads.create.contact_person.form_controls.before') !!}

@php
    $sourceAccessService = app(\Webkul\Lead\Services\SourceAccessService::class);
    $isSdrUser = $sourceAccessService->isSdrUser();
    $isContactEditContext = $contactEditContext ?? isset($lead);
    $canEditContactDetails = ! ($isSdrUser && $isContactEditContext);
    $canEditLeadCompany = $canEditContactDetails && (
        bouncer()->hasPermission('contacts.organizations.edit')
        || bouncer()->hasPermission('contacts.organizations.create')
    );
@endphp

<v-contact-component
    :data="person"
    :can-edit-company='@json($canEditLeadCompany)'
    :can-edit-contact-details='@json($canEditContactDetails)'
></v-contact-component>

{!! view_render_event('admin.leads.create.contact_person.form_controls.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-contact-component-template"
    >
        <!-- Person Search Lookup -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                @lang('admin::app.leads.common.contact.name')
            </x-admin::form.control-group.label>

            <template v-if="canEditContactDetails">
                <x-admin::lookup
                    ::src="src"
                    name="person[id]"
                    ::params="params"
                    ::rules="nameValidationRule"
                    :label="trans('admin::app.leads.common.contact.name')"
                    ::value="{id: person.id, name: person.name}"
                    :placeholder="trans('admin::app.leads.common.contact.name')"
                    @on-selected="addPerson"
                    :can-add-new="true"
                />

                <x-admin::form.control-group.control
                    type="hidden"
                    name="person[name]"
                    v-model="person.name"
                    v-if="person.name"
                />
            </template>

            <template v-else>
                <x-admin::form.control-group.control
                    type="text"
                    name="person[name]"
                    ::value="person.name"
                    disabled
                    class="cursor-not-allowed opacity-70"
                    :label="trans('admin::app.leads.common.contact.name')"
                />
            </template>

            <x-admin::form.control-group.error control-name="person[id]" />
        </x-admin::form.control-group>

        <!-- Person Email -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                @lang('admin::app.leads.common.contact.email')
            </x-admin::form.control-group.label>

            <x-admin::attributes.edit.email />

            <v-email-component
                :attribute="{'id': person?.id, 'code': 'person[emails]', 'name': 'Email'}"
                :value="person.emails"
                :is-disabled="! canEditContactDetails || !! person?.id"
            ></v-email-component>
        </x-admin::form.control-group>

        <!-- Person Contact Numbers -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                @lang('admin::app.leads.common.contact.contact-number')
            </x-admin::form.control-group.label>

            <x-admin::attributes.edit.phone />

            <v-phone-component
                :attribute="{'id': person?.id, 'code': 'person[contact_numbers]', 'name': 'Contact Numbers'}"
                :value="person.contact_numbers"
                :is-disabled="! canEditContactDetails || !! person?.id"
            ></v-phone-component>
        </x-admin::form.control-group>

        <!-- Person Organization -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                @lang('admin::app.leads.common.contact.organization')
            </x-admin::form.control-group.label>

            @php
                $organizationAttribute = app('Webkul\Attribute\Repositories\AttributeRepository')->findOneWhere([
                    'entity_type' => 'persons',
                    'code'        => 'organization_id'
                ]);

                // Display-only lookup field name so we can submit real company values separately.
                $organizationAttribute->code = '_person_organization_lookup';
            @endphp

            <x-admin::attributes.edit.lookup />

            <v-lookup-component
                :key="'org-' + (person.id || 'new') + '-' + (person.organization?.id || person.organization_name || 'none')"
                :attribute='@json($organizationAttribute)'
                :value="person.organization"
                :is-disabled="isCompanyDisabled"
                :can-add-new="! isCompanyDisabled"
                @lookup-added="onCompanySelected"
                @lookup-removed="onCompanyRemoved"
            ></v-lookup-component>

            <x-admin::form.control-group.control
                type="hidden"
                name="person[organization_id]"
                ::value="person.organization_id ?? ''"
            />

            <x-admin::form.control-group.control
                type="hidden"
                name="person[organization_name]"
                ::value="person.organization_name || ''"
            />

            <x-admin::form.control-group.control
                type="hidden"
                name="person[id]"
                ::value="person.id || ''"
                v-if="person.id"
            />
        </x-admin::form.control-group>

        <!-- Person Address -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                @lang('admin::app.leads.common.contact.address')
            </x-admin::form.control-group.label>

            @php
                $addressAttribute = app('Webkul\Attribute\Repositories\AttributeRepository')->findOneWhere([
                    'entity_type' => 'persons',
                    'code'        => 'address',
                ]);
            @endphp

            @if ($addressAttribute)
                @php
                    $addressAttribute->code = 'person['.$addressAttribute->code.']';
                @endphp

                <v-address-component
                    :key="(person.id || 'new-address') + '-' + (isCompanyDisabled ? 'locked' : 'editable')"
                    :attribute='@json($addressAttribute)'
                    :data="person.address"
                    validations=""
                    :is-disabled="isCompanyDisabled"
                ></v-address-component>
            @endif
        </x-admin::form.control-group>

        <!-- Person Website -->
        <x-admin::form.control-group>
            <x-admin::form.control-group.label>
                @lang('admin::app.leads.common.contact.website')
            </x-admin::form.control-group.label>

            <x-admin::form.control-group.control
                type="text"
                name="person[website]"
                ::value="person.website ?? ''"
                ::disabled="isCompanyDisabled"
                :label="trans('admin::app.leads.common.contact.website')"
                :placeholder="trans('admin::app.leads.common.contact.website')"
            />
        </x-admin::form.control-group>
    </script>

    <script type="module">
        app.component('v-contact-component', {
            template: '#v-contact-component-template',

            props: {
                data: {
                    type: Object,
                    default: () => ({}),
                },

                canEditCompany: {
                    type: Boolean,
                    default: @json($canEditLeadCompany),
                },

                canEditContactDetails: {
                    type: Boolean,
                    default: @json($canEditContactDetails),
                },
            },

            data () {
                return {
                    is_searching: false,

                    person: this.normalizePerson(this.data),

                    persons: [],
                }
            },

            watch: {
                data: {
                    handler(value) {
                        this.person = this.normalizePerson(value);
                    },
                    deep: true,
                },
            },

            computed: {
                src() {
                    return "{{ route('admin.contacts.persons.search') }}";
                },

                params() {
                    return {
                        params: {
                            query: this.person['name']
                        }
                    }
                },

                nameValidationRule() {
                    return '';
                },

                isCompanyDisabled() {
                    if (! this.canEditContactDetails) {
                        return true;
                    }

                    if (this.canEditCompany) {
                        return false;
                    }

                    return !! this.person?.id;
                },
            },

            methods: {
                normalizePerson(value) {
                    const person = value && typeof value === 'object' ? { ...value } : {};

                    return {
                        id: person.id ?? null,
                        name: person.name ?? '',
                        emails: Array.isArray(person.emails) && person.emails.length
                            ? person.emails
                            : [{ value: '', label: 'work' }],
                        contact_numbers: Array.isArray(person.contact_numbers) && person.contact_numbers.length
                            ? person.contact_numbers
                            : [{ value: '', label: 'work' }],
                        organization_id: person.organization_id ?? person.organization?.id ?? null,
                        organization: person.organization
                            ? {
                                id: person.organization.id,
                                name: person.organization.name,
                            }
                            : null,
                        organization_name: person.organization_name ?? '',
                        address: person.address ?? null,
                        website: person.website ?? '',
                    };
                },

                addPerson (person) {
                    this.person = this.normalizePerson(person);
                },

                onCompanySelected(company) {
                    if (! company) {
                        this.onCompanyRemoved();

                        return;
                    }

                    if (company.id) {
                        this.person.organization = {
                            id: company.id,
                            name: company.name,
                        };
                        this.person.organization_id = company.id;
                        this.person.organization_name = '';

                        return;
                    }

                    this.person.organization = {
                        id: '',
                        name: company.name,
                    };
                    this.person.organization_id = null;
                    this.person.organization_name = company.name;
                },

                onCompanyRemoved() {
                    this.person.organization = null;
                    this.person.organization_id = null;
                    this.person.organization_name = '';
                },
            }
        });
    </script>
@endPushOnce

@include('admin::components.attributes.edit.address', ['attribute' => null, 'validations' => '', 'value' => null])
