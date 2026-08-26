<?php

namespace Webkul\Admin\Http\Controllers\User;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Core\Menu\MenuItem;
use Webkul\User\Services\ActiveRoleService;

class SessionController extends Controller
{
    public function __construct(
        protected ActiveRoleService $activeRoleService,
    ) {}

    /**
     * Show the form for creating a new resource.
     */
    public function create(): RedirectResponse|View
    {
        if (auth()->guard('user')->check()) {
            if ($this->activeRoleService->requiresRoleSelection()) {
                return redirect()->route('admin.session.role.create');
            }

            return redirect()->route($this->activeRoleService->dashboardRouteForRole(
                $this->activeRoleService->getActiveRole()
            ));
        }

        $previousUrl = url()->previous();

        $intendedUrl = str_contains($previousUrl, 'admin')
            ? $previousUrl
            : route('admin.dashboard.index');

        session()->put('url.intended', $intendedUrl);

        return view('admin::sessions.login');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(): RedirectResponse
    {
        $this->validate(request(), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (! auth()->guard('user')->attempt(request(['email', 'password']), request('remember'))) {
            session()->flash('error', trans('admin::app.users.login-error'));

            return redirect()->back();
        }

        request()->session()->regenerate();

        if (auth()->guard('user')->user()->status == 0) {
            session()->flash('warning', trans('admin::app.users.activate-warning'));

            auth()->guard('user')->logout();

            return redirect()->route('admin.session.create');
        }

        $this->activeRoleService->clearActiveRole();

        $assignedRoles = $this->activeRoleService->assignedRoles();

        if ($assignedRoles->isEmpty()) {
            session()->flash('error', trans('admin::app.users.not-permission'));

            auth()->guard('user')->logout();

            return redirect()->route('admin.session.create');
        }

        if ($assignedRoles->count() > 1) {
            return $this->clearLoginCookies(redirect()->route('admin.session.role.create'));
        }

        $role = $this->activeRoleService->activateRole(
            (int) $assignedRoles->first()->id,
            null,
            false
        );

        return $this->redirectAfterRoleActivation($role);
    }

    /**
     * Show role selection for multi-role users.
     */
    public function createRole(): RedirectResponse|View
    {
        if (! auth()->guard('user')->check()) {
            return redirect()->route('admin.session.create');
        }

        $roles = $this->activeRoleService->assignedRoles();

        if ($roles->isEmpty()) {
            auth()->guard('user')->logout();

            session()->flash('error', trans('admin::app.users.not-permission'));

            return redirect()->route('admin.session.create');
        }

        if ($roles->count() === 1) {
            $role = $this->activeRoleService->activateRole((int) $roles->first()->id, null, false);

            return redirect()->route($this->activeRoleService->dashboardRouteForRole($role));
        }

        if (! $this->activeRoleService->requiresRoleSelection()) {
            return redirect()->route($this->activeRoleService->dashboardRouteForRole(
                $this->activeRoleService->getActiveRole()
            ));
        }

        $user = auth()->guard('user')->user();

        return view('admin::sessions.select-role', [
            'user'  => $user,
            'roles' => $roles,
        ]);
    }

    /**
     * Persist the selected active role.
     */
    public function storeRole(): RedirectResponse
    {
        if (! auth()->guard('user')->check()) {
            return redirect()->route('admin.session.create');
        }

        $this->validate(request(), [
            'role_id' => 'required|integer',
        ]);

        try {
            $role = $this->activeRoleService->activateRole((int) request('role_id'));
        } catch (ValidationException $exception) {
            return redirect()
                ->route('admin.session.role.create')
                ->withErrors($exception->errors());
        }

        return $this->redirectAfterRoleActivation($role);
    }

    /**
     * Switch active role for an already authenticated multi-role user.
     */
    public function switchRole(): RedirectResponse
    {
        if (! auth()->guard('user')->check()) {
            return redirect()->route('admin.session.create');
        }

        $this->validate(request(), [
            'role_id' => 'required|integer',
        ]);

        try {
            $role = $this->activeRoleService->activateRole((int) request('role_id'));
        } catch (ValidationException $exception) {
            abort(403, $exception->errors()['role_id'][0] ?? 'Invalid role.');
        }

        return redirect()->route($this->activeRoleService->dashboardRouteForRole($role));
    }

    /**
     * Redirect after a successful login and clear stale browser cookies.
     */
    protected function redirectAfterLogin(string $url): RedirectResponse
    {
        return $this->clearLoginCookies(redirect()->to($url));
    }

    protected function redirectAfterRoleActivation($role): RedirectResponse
    {
        $menus = menu()->getItems('admin');
        $availableNextMenu = $menus?->first();
        $dashboardRoute = $this->activeRoleService->dashboardRouteForRole($role);

        if (! bouncer()->hasPermission('dashboard')
            && ! bouncer()->hasPermission('sdr_dashboard')
            && ! bouncer()->hasPermission('lge_dashboard')
            && ! bouncer()->hasPermission('lead_clouser_dashboard')
        ) {
            if (is_null($availableNextMenu)) {
                session()->flash('error', trans('admin::app.users.not-permission'));

                auth()->guard('user')->logout();

                return redirect()->route('admin.session.create');
            }

            return $this->redirectAfterLogin($availableNextMenu->getUrl());
        }

        $hasAccessToIntendedUrl = $this->canAccessIntendedUrl($menus, redirect()->getIntendedUrl());

        if ($hasAccessToIntendedUrl) {
            return $this->redirectAfterLogin(redirect()->getIntendedUrl() ?? route($dashboardRoute));
        }

        return $this->redirectAfterLogin(route($dashboardRoute));
    }

    /**
     * Forget cookies from previous sessions while keeping the new session cookie.
     */
    protected function clearLoginCookies(RedirectResponse $response): RedirectResponse
    {
        $sessionCookie = config('session.cookie');

        foreach (array_keys(request()->cookies->all()) as $name) {
            if ($name === $sessionCookie) {
                continue;
            }

            $response = $response->withCookie(Cookie::forget($name));
        }

        return $response;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(): RedirectResponse
    {
        $this->activeRoleService->clearActiveRole();

        auth()->guard('user')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('admin.session.create');
    }

    /**
     * Find menu item by URL.
     */
    protected function canAccessIntendedUrl(Collection $menus, ?string $url): ?MenuItem
    {
        if (is_null($url)) {
            return null;
        }

        foreach ($menus as $menu) {
            if ($menu->getUrl() === $url) {
                return $menu;
            }

            if ($menu->haveChildren()) {
                $found = $this->canAccessIntendedUrl($menu->getChildren(), $url);

                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }
}
