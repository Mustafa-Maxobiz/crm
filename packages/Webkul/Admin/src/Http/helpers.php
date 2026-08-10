<?php

if (! function_exists('bouncer')) {
    /**
     * @return \Webkul\Admin\Bouncer
     */
    function bouncer()
    {
        return app()->make('bouncer');
    }
}

if (! function_exists('lead_variant')) {
    /**
     * Current leads UI variant: main, sdr, or lge.
     */
    function lead_variant(?string $variant = null): string
    {
        if (in_array($variant, ['main', 'sdr', 'lge'], true)) {
            return $variant;
        }

        if (app()->bound('view')) {
            $shared = view()->shared('leadVariant');

            if (in_array($shared, ['main', 'sdr', 'lge'], true)) {
                return $shared;
            }
        }

        $routeName = request()->route()?->getName() ?? '';

        if (str_starts_with($routeName, 'admin.leads.sdr')) {
            return 'sdr';
        }

        if (str_starts_with($routeName, 'admin.leads.lge')) {
            return 'lge';
        }

        return 'main';
    }
}

if (! function_exists('lead_permission')) {
    /**
     * ACL key for the current lead variant (e.g. leads.view / sdr_leads.view).
     */
    function lead_permission(string $action = '', ?string $variant = null): string
    {
        $leadVariant = lead_variant($variant);
        $prefix = match ($leadVariant) {
            'sdr' => 'sdr_leads',
            'lge' => 'lge_leads',
            default => 'leads',
        };

        return $action === '' ? $prefix : $prefix.'.'.$action;
    }
}

if (! function_exists('lead_route_name')) {
    /**
     * Named route for the current lead variant.
     *
     * @param  string  $action  e.g. index, view, create, form_data
     */
    function lead_route_name(string $action = 'index', ?string $variant = null): string
    {
        if (lead_variant($variant) === 'sdr') {
            return $action === 'index' ? 'admin.leads.sdr' : 'admin.leads.sdr.'.$action;
        }

        if (lead_variant($variant) === 'lge') {
            return $action === 'index' ? 'admin.leads.lge' : 'admin.leads.lge.'.$action;
        }

        return 'admin.leads.'.$action;
    }
}

if (! function_exists('lead_route')) {
    /**
     * Generate a URL for a lead route in the current variant.
     *
     * @param  mixed  $parameters
     */
    function lead_route(string $action = 'index', $parameters = [], bool $absolute = true, ?string $variant = null): string
    {
        return route(lead_route_name($action, $variant), $parameters, $absolute);
    }
}

if (! function_exists('lead_url')) {
    /**
     * Base URL for lead AJAX paths (…/admin/leads or …/admin/leads/sdr).
     */
    function lead_url(?string $variant = null): string
    {
        return match (lead_variant($variant)) {
            'sdr' => url('admin/leads/sdr'),
            'lge' => url('admin/leads/lge'),
            default => url('admin/leads'),
        };
    }
}

if (! function_exists('admin_menu_items')) {
    /**
     * Sidebar menu for the current user.
     * Full admins get one Dashboard + one Leads page (all leads) + Meta Leads;
     * SDR/LGE dashboards and calling-role lead pages are hidden for them.
     */
    function admin_menu_items(): \Illuminate\Support\Collection
    {
        $items = menu()->getItems('admin');

        if (! app(\Webkul\Lead\Services\SourceAccessService::class)->isAdmin()) {
            return $items;
        }

        $hidden = [
            'sdr_dashboard',
            'lge_dashboard',
            'sdr_leads',
            'lge_leads',
        ];

        return $items->reject(
            fn ($item) => in_array($item->getKey(), $hidden, true)
        )->values();
    }
}
