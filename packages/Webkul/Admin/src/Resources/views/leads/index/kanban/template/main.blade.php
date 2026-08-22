        <template v-if="isLoading">
            <div class="flex flex-col gap-4">
                <x-admin::shimmer.leads.index.kanban />
            </div>
        </template>

        <template v-else>
            <div class="flex flex-col gap-4">
                @include('admin::leads.index.kanban.toolbar')

                {!! view_render_event('admin.leads.index.kanban.content.before') !!}

                <div class="flex gap-2.5 overflow-x-auto">
                    <!-- Stage Cards -->
                    <div
                        class="flex min-w-[275px] max-w-[275px] flex-col gap-1 rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
                        v-for="(stage, index) in stageLeads"
                    >
                        {!! view_render_event('admin.leads.index.kanban.content.stage.header.before') !!}

                        <!-- Stage Header -->
                        <div class="flex flex-col px-2 py-3">
                            <!-- Stage Title and Action -->
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium dark:text-white">
                                    @{{ stage.name }} (@{{ stage.leads.meta.total }})
                                </span>

                                @if (bouncer()->hasPermission(lead_permission('create')))
                                    <a
                                        :href="'{{ lead_route('create') }}' + '?stage_id=' + stage.id"
                                        class="icon-add cursor-pointer rounded p-1 text-lg text-gray-600 transition-all hover:bg-gray-200 hover:text-gray-800 dark:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-white"
                                        target="_blank"
                                    >
                                    </a>
                                @endif
                            </div>

                            <!-- Stage Total Leads and Amount -->
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-medium dark:text-white">
                                    @{{ $admin.formatPrice(stage.lead_value) }}
                                </span>

                                <!-- Progress Bar -->
                                <div class="h-1 w-36 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                                    <div
                                        class="h-1 bg-green-500"
                                        :style="{ width: (stage.lead_value / totalStagesAmount) * 100 + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>

                        {!! view_render_event('admin.leads.index.kanban.content.stage.header.after') !!}

                        {!! view_render_event('admin.leads.index.kanban.content.stage.body.before') !!}

                        <!-- Draggable Stage Lead Cards -->
                        <draggable
                            class="flex h-[calc(100vh-317px)] flex-col gap-2 overflow-y-auto p-2"
                            :class="{ 'justify-center': stage.leads.data.length === 0 }"
                            ghost-class="draggable-ghost"
                            handle=".lead-item"
                            v-bind="{animation: 200}"
                            :list="stage.leads.data"
                            item-key="id"
                            group="leads"
                            :move="canMoveLead"
                            @scroll="handleScroll(stage, $event)"
                            @change="handleUpdate(stage, $event)"
                        >
                            <template #header>
                                <div
                                    class="flex flex-col items-center justify-center"
                                    v-if="! stage.leads.data.length"
                                >
                                    <img
                                        class="dark:mix-blend-exclusion dark:invert"
                                        src="{{ vite()->asset('images/empty-placeholders/pipedrive.svg') }}"
                                    >

                                    <div class="flex flex-col items-center gap-4">
                                        <div class="flex flex-col items-center gap-2">
                                            <p class="!text-base font-semibold dark:text-white">
                                                @lang('admin::app.leads.index.kanban.empty-list')
                                            </p>

                                            <p class="!text-sm text-gray-400 dark:text-gray-400">
                                                @lang('admin::app.leads.index.kanban.empty-list-description')
                                            </p>
                                        </div>

                                        @if (bouncer()->hasPermission(lead_permission('create')))
                                            <a
                                                :href="'{{ lead_route('create') }}' + '?stage_id=' + stage.id"
                                                class="secondary-button"
                                            >
                                                @lang('admin::app.leads.index.kanban.create-lead-btn')
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </template>

                            <!-- Lead Card -->
                            <template #item="{ element, index }">
                                {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.before') !!}

                                <component
                                    :is="isStageEditingLocked(element) ? 'div' : 'a'"
                                    class="lead-item flex flex-col gap-5 rounded-md border p-2 transition-all"
                                    :class="[
                                        isHandoffLead(element)
                                            ? 'border-amber-300 border-l-4 border-l-amber-500 bg-amber-50 dark:border-amber-500 dark:border-l-amber-400 dark:bg-amber-950/30'
                                            : 'border-gray-100 bg-gray-50 dark:border-gray-400 dark:bg-gray-400',
                                        isStageEditingLocked(element) ? 'cursor-not-allowed' : 'cursor-pointer hover:border-gray-300'
                                    ]"
                                    :href="isStageEditingLocked(element) ? null : '{{ lead_route('view', 'replaceId') }}'.replace('replaceId', element.id)"
                                    :target="isStageEditingLocked(element) ? null : '_blank'"
                                    :rel="isStageEditingLocked(element) ? null : 'noopener noreferrer'"
                                    :title="isHandoffLead(element) ? 'Sales owner changed. This lead remains visible for tracking.' : null"
                                >
                                    {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.header.before') !!}

                                    <!-- Header -->
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-1">
                                            <x-admin::avatar ::name="element.person.name" />

                                            <div class="flex flex-col gap-0.5">
                                                <span class="text-xs font-medium">
                                                    @{{ element.person.name }}
                                                </span>

                                                <span class="text-[10px] leading-normal">
                                                    @{{ element.person.organization?.name }}
                                                </span>
                                            </div>
                                        </div>

                                        <div
                                            class="group relative"
                                            v-if="element.rotten_days > 0"
                                        >
                                            <span class="icon-rotten cursor-default text-xl text-rose-600"></span>

                                            <div class="absolute -top-1 right-7 hidden w-max flex-col items-center group-hover:flex">
                                                <span class="whitespace-no-wrap relative rounded-md bg-black px-4 py-2 text-xs leading-none text-white shadow-lg">
                                                    @{{ "@lang('admin::app.leads.index.kanban.rotten-days', ['days' => 'replaceDays'])".replace('replaceDays', element.rotten_days) }}
                                                </span>

                                                <div class="absolute -right-1 top-2 h-3 w-3 rotate-45 bg-black"></div>
                                            </div>
                                        </div>

                                        <span
                                            class="rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-800"
                                            v-if="isHandoffLead(element)"
                                        >
                                            Assigned
                                        </span>
                                    </div>

                                    {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.header.after') !!}

                                    {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.title.before') !!}

                                    <!-- Lead Title -->
                                    <p class="text-xs font-medium">
                                        @{{ element.title }}
                                    </p>

                                    {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.title.after') !!}

                                    <div class="flex flex-col gap-1">
                                        <span class="text-[10px] font-semibold uppercase tracking-normal text-gray-500 dark:text-gray-300">
                                            Source
                                        </span>

                                        <div class="flex flex-wrap gap-1">
                                            <div
                                                class="flex items-center gap-1 rounded-xl bg-gray-200 px-2 py-1 text-xs font-medium dark:bg-gray-800 dark:text-white"
                                                v-if="element.user"
                                            >
                                                <span class="icon-settings-user text-sm"></span>

                                                @{{ element.user.name }}
                                            </div>

                                            <div class="rounded-xl bg-gray-200 px-2 py-1 text-xs font-medium dark:bg-gray-800 dark:text-white">
                                                @{{ element.formatted_lead_value }}
                                            </div>

                                            <div class="rounded-xl bg-gray-200 px-2 py-1 text-xs font-medium dark:bg-gray-800 dark:text-white">
                                                @{{ element.source.name }}
                                            </div>

                                            <div class="rounded-xl bg-gray-200 px-2 py-1 text-xs font-medium dark:bg-gray-800 dark:text-white">
                                                @{{ element.type.name }}
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="flex flex-col gap-1"
                                        v-if="element.tags.length"
                                    >
                                        <span class="text-[10px] font-semibold uppercase tracking-normal text-gray-500 dark:text-gray-300">
                                            Tags
                                        </span>

                                        <div class="flex flex-wrap gap-1">
                                            <!-- Tags -->
                                            <template v-for="tag in element.tags">
                                                {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.tag.before') !!}

                                                <div
                                                    class="rounded-xl bg-gray-200 px-2 py-1 text-xs font-medium dark:bg-gray-800"
                                                    :style="{
                                                        backgroundColor: tag.color,
                                                        color: getTagTextColor(tag.color)
                                                    }"
                                                >
                                                    @{{ tag.name }}
                                                </div>

                                                {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.tag.after') !!}
                                            </template>
                                        </div>
                                    </div>
                                </component>

                                {!! view_render_event('admin.leads.index.kanban.content.stage.body.card.after') !!}
                            </template>
                        </draggable>

                        {!! view_render_event('admin.leads.index.kanban.content.stage.body.after') !!}
                    </div>
                </div>

                {!! view_render_event('admin.leads.index.kanban.content.after') !!}
            </div>
