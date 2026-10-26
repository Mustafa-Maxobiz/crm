<?php

namespace Webkul\Admin\Http\Middleware;

use Illuminate\Support\Facades\Route;
use Webkul\User\Services\ActiveRoleService;

class Bouncer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, \Closure $next, $guard = 'user')
    {
        if (! auth()->guard($guard)->check()) {
            return redirect()->route('admin.session.create');
        }

        /**
         * If user status is changed by admin. Then session should be
         * logged out.
         */
        if (! (bool) auth()->guard($guard)->user()->status) {
            auth()->guard($guard)->logout();

            session()->flash('error', __('admin::app.errors.401'));

            return redirect()->route('admin.session.create');
        }

        $activeRoleService = app(ActiveRoleService::class);
        $user = auth()->guard($guard)->user();

        if ($activeRoleService->requiresRoleSelection($user)) {
            if (! $request->routeIs('admin.session.role.*') && ! $request->routeIs('admin.session.destroy')) {
                return redirect()->route('admin.session.role.create');
            }
        } else {
            $activeRoleService->applyActiveRole($user);
        }

        /**
         * If somehow the user deleted all permissions, then it should be
         * auto logged out and need to contact the administrator again.
         */
        if (! $request->routeIs('admin.session.role.*') && $this->isPermissionsEmpty()) {
            auth()->guard($guard)->logout();

            session()->flash('error', __('admin::app.errors.401'));

            return redirect()->route('admin.session.create');
        }

        return $next($request);
    }

    /**
     * Check for user, if they have empty permissions or not except admin.
     *
     * @return bool
     */
    public function isPermissionsEmpty()
    {
        $user = auth()->guard('user')->user();
        $role = app(ActiveRoleService::class)->getActiveRole($user) ?? $user?->role;

        if (! $role) {
            abort(401, 'This action is unauthorized.');
        }

        if ($role->permission_type === 'all') {
            return false;
        }

        if ($role->permission_type !== 'all' && empty($role->permissions)) {
            return true;
        }

        $this->checkIfAuthorized();

        return false;
    }

    /**
     * Check authorization.
     *
     * @return null
     */
    public function checkIfAuthorized()
    {
        $roles = acl()->getRoles();

        if (
            Route::currentRouteName() === 'admin.activities.store'
            && $this->isLeadMeetingStageWorkflowRequest()
        ) {
            return;
        }

        if (isset($roles[Route::currentRouteName()])) {
            bouncer()->allow($roles[Route::currentRouteName()]);
        }
    }

    /**
     * Allow the lead Meeting-stage workflow to create its required meeting
     * activity without granting the role general activities.create access.
     */
    protected function isLeadMeetingStageWorkflowRequest(): bool
    {
        if (! request()->boolean('stage_meeting')) {
            return false;
        }

        $leadId = (int) request()->input('lead_id');
        $targetStageId = (int) request()->input('lead_pipeline_stage_id');

        if (! $leadId || ! $targetStageId) {
            return false;
        }

        $sourceAccessService = app(\Webkul\Lead\Services\SourceAccessService::class);

        if (! (
            $sourceAccessService->isBdeUser()
            || $sourceAccessService->isSdrUser()
            || $sourceAccessService->isLgeUser()
            || $sourceAccessService->isDeDataScraperUser()
        )) {
            return false;
        }

        $lead = app(\Webkul\Lead\Repositories\LeadRepository::class)
            ->getModel()
            ->newQuery()
            ->with('pipeline.stages')
            ->find($leadId);

        if (! $lead || ! $sourceAccessService->canEditLead($lead)) {
            return false;
        }

        $targetStage = $lead->pipeline?->stages
            ?->firstWhere('id', $targetStageId);

        return $targetStage?->code === 'meeting';
    }
}
