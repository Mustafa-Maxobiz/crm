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
     * Current leads UI variant: main or sdr.
     */
    function lead_variant(?string $variant = null): string
    {
        if (in_array($variant, ['main', 'sdr'], true)) {
            return $variant;
        }

        if (app()->bound('view')) {
            $shared = view()->shared('leadVariant');

            if (in_array($shared, ['main', 'sdr'], true)) {
                return $shared;
            }
        }

        $routeName = request()->route()?->getName() ?? '';

        return str_starts_with($routeName, 'admin.leads.sdr') ? 'sdr' : 'main';
    }
}

if (! function_exists('lead_permission')) {
    /**
     * ACL key for the current lead variant (e.g. leads.view / sdr_leads.view).
     */
    function lead_permission(string $action = '', ?string $variant = null): string
    {
        $prefix = lead_variant($variant) === 'sdr' ? 'sdr_leads' : 'leads';

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
        return lead_variant($variant) === 'sdr'
            ? url('admin/leads/sdr')
            : url('admin/leads');
    }
}
