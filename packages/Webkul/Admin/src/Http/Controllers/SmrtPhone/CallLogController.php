<?php

namespace Webkul\Admin\Http\Controllers\SmrtPhone;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\SmrtPhone\SmrtPhoneCallLogDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\SmrtPhone\Repositories\SmrtPhoneCallLogRepository;

class CallLogController extends Controller
{
    public function __construct(
        protected SmrtPhoneCallLogRepository $callLogRepository,
    ) {}

    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(SmrtPhoneCallLogDataGrid::class)->process();
        }

        return view('admin::smrtphone.index');
    }

    public function view(int $id): View
    {
        $callLog = $this->callLogRepository
            ->with(['person', 'lead', 'activity'])
            ->findOrFail($id);

        return view('admin::smrtphone.view', compact('callLog'));
    }

    public function destroy(int $id): JsonResponse|RedirectResponse
    {
        try {
            $this->callLogRepository->delete($id);

            if (request()->ajax()) {
                return response()->json([
                    'message' => trans('admin::app.smrtphone.index.delete-success'),
                ]);
            }

            session()->flash('success', trans('admin::app.smrtphone.index.delete-success'));

            return redirect()->route('admin.smrtphone.index');
        } catch (\Exception) {
            if (request()->ajax()) {
                return response()->json([
                    'message' => trans('admin::app.smrtphone.index.delete-failed'),
                ], 400);
            }

            session()->flash('error', trans('admin::app.smrtphone.index.delete-failed'));

            return redirect()->back();
        }
    }

    public function massDestroy(MassDestroyRequest $request): JsonResponse
    {
        try {
            foreach ($request->input('indices') as $id) {
                $this->callLogRepository->delete($id);
            }

            return response()->json([
                'message' => trans('admin::app.smrtphone.index.delete-success'),
            ]);
        } catch (\Exception) {
            return response()->json([
                'message' => trans('admin::app.smrtphone.index.delete-failed'),
            ], 400);
        }
    }
}
