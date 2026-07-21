<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Lead\Services\FollowupScheduleService;

class FollowupScheduleController extends Controller
{
    public const CONFIG_PREFIX = 'general.settings.followup_schedule.';

    public function __construct(
        protected FollowupScheduleService $followupScheduleService,
    ) {}

    public function index(): View
    {
        return view('admin::settings.followup-schedule.index', [
            'enabled'  => $this->followupScheduleService->isEnabled(),
            'settings' => $this->followupScheduleService->settings(),
            'units'    => FollowupScheduleService::UNITS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled'                => ['nullable', 'boolean'],
            'steps'                  => ['required', 'array', 'min:1'],
            'steps.*.value'          => ['required', 'integer', 'min:1'],
            'steps.*.unit'           => ['required', 'string', Rule::in(FollowupScheduleService::UNITS)],
            'steps.*.frequency'      => ['required', 'integer', 'min:1'],
            'max_days'               => ['required', 'integer', 'min:1'],
        ]);

        $steps = $this->followupScheduleService->normalizeSteps($data['steps']);

        if (empty($steps)) {
            return redirect()->back()->withErrors([
                'steps' => trans('admin::app.settings.followup-schedule.index.intervals-required'),
            ])->withInput();
        }

        $payload = [
            'enabled'   => $request->boolean('enabled') ? '1' : '0',
            'intervals' => json_encode($steps),
            'max_days'  => (string) $data['max_days'],
        ];

        Event::dispatch('core.configuration.save.before');

        foreach ($payload as $key => $value) {
            $code = self::CONFIG_PREFIX.$key;

            DB::table('core_config')->updateOrInsert(
                ['code' => $code],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        Event::dispatch('core.configuration.save.after');

        session()->flash('success', trans('admin::app.settings.followup-schedule.index.update-success'));

        return redirect()->route('admin.settings.followup_schedule.index');
    }
}
