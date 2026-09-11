<?php

namespace Webkul\Admin\Http\Controllers\SmrtPhone;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Lead\Services\SourceAccessService;
use Webkul\SmrtPhone\Services\SmrtPhoneConfigService;

class SettingsController extends Controller
{
    public function __construct(
        protected SmrtPhoneConfigService $configService,
        protected SourceAccessService $sourceAccessService,
    ) {}

    public function index(): View
    {
        $this->ensureAdmin();

        return view('admin::smrtphone.settings', [
            'settings'   => $this->configService->all(),
            'webhookUrl' => $this->configService->webhookUrl(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'webhook_secret'                    => ['nullable', 'string', 'min:8', 'max:255'],
            'create_activities'                 => ['nullable', 'boolean'],
            'save_ai_summary'                   => ['nullable', 'boolean'],
            'auto_match_lead'                   => ['nullable', 'boolean'],
            'create_activity_only_when_matched' => ['nullable', 'boolean'],
            'allowed_numbers'                   => ['nullable', 'array'],
            'allowed_numbers.*'                 => ['nullable', 'string', 'max:30'],
        ]);

        $this->configService->save([
            'webhook_secret'                    => $data['webhook_secret'] ?? '',
            'create_activities'                 => $request->boolean('create_activities'),
            'save_ai_summary'                   => $request->boolean('save_ai_summary'),
            'auto_match_lead'                   => $request->boolean('auto_match_lead'),
            'create_activity_only_when_matched' => $request->boolean('create_activity_only_when_matched'),
            'allowed_numbers'                   => $data['allowed_numbers'] ?? [],
        ]);

        session()->flash('success', trans('admin::app.smrtphone.settings.update-success'));

        return redirect()->route('admin.smrtphone.settings');
    }

    protected function ensureAdmin(): void
    {
        if (! $this->sourceAccessService->isAdmin()) {
            abort(403);
        }
    }
}
