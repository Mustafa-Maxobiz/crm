<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Settings\DeletedLeadDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Lead\Models\Lead;

class DeletedLeadController extends Controller
{
    /**
     * Display a listing of the deleted leads.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(DeletedLeadDataGrid::class)->process();
        }

        return view('admin::settings.deleted-leads.index');
    }

    /**
     * Restore a soft deleted lead.
     */
    public function restore(int $id): JsonResponse
    {
        $lead = $this->findAuthorizedTrashedLead($id);

        Event::dispatch('lead.restore.before', $id);

        $lead->restore();

        Event::dispatch('lead.restore.after', $lead);

        return new JsonResponse([
            'message' => trans('admin::app.settings.deleted-leads.index.restore-success'),
        ]);
    }

    /**
     * Permanently delete a soft deleted lead.
     */
    public function permanentDelete(int $id): JsonResponse
    {
        $lead = $this->findAuthorizedTrashedLead($id);

        try {
            Event::dispatch('lead.permanent_delete.before', $id);

            $lead->forceDelete();

            Event::dispatch('lead.permanent_delete.after', $id);

            return new JsonResponse([
                'message' => trans('admin::app.settings.deleted-leads.index.permanent-delete-success'),
            ]);
        } catch (\Exception) {
            return new JsonResponse([
                'message' => trans('admin::app.settings.deleted-leads.index.permanent-delete-failed'),
            ], 400);
        }
    }

    /**
     * Find a deleted lead inside the current user's allowed lead scope.
     */
    protected function findAuthorizedTrashedLead(int $id): Lead
    {
        $query = Lead::onlyTrashed();

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $query->whereIn('user_id', $userIds);
        }

        return $query->findOrFail($id);
    }
}
