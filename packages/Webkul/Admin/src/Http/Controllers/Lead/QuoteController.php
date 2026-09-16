<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use Illuminate\Support\Facades\Event;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Services\SourceAccessService;
use Webkul\Quote\Repositories\QuoteRepository;

class QuoteController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected QuoteRepository $quoteRepository,
        protected SourceAccessService $sourceAccessService,
    ) {}

    /**
     * Store a newly created resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function store($id)
    {
        Event::dispatch('leads.quote.create.before');

        $lead = $this->leadRepository->findOrFail($id);

        if (! $this->sourceAccessService->canEditLead($lead)) {
            return response()->json([
                'message' => trans('admin::app.leads.source-access-denied'),
            ], 403);
        }

        if (! $lead->quotes->contains(request('id'))) {
            $lead->quotes()->attach(request('id'));
        }

        Event::dispatch('leads.quote.create.after', $lead);

        return response()->json([
            'message' => trans('admin::app.leads.quote-create-success'),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $leadId
     * @param  int  $tagId
     * @return \Illuminate\Http\Response
     */
    public function delete($leadId)
    {
        Event::dispatch('leads.quote.delete.before', $leadId);

        $lead = $this->leadRepository->findOrFail($leadId);

        if (! $this->sourceAccessService->canEditLead($lead)) {
            return response()->json([
                'message' => trans('admin::app.leads.source-access-denied'),
            ], 403);
        }

        $quoteId = request('quote_id') ?: request()->route('quote_id');

        $lead->quotes()->detach($quoteId);

        Event::dispatch('leads.quote.delete.after', $lead);

        return response()->json([
            'message' => trans('admin::app.leads.view.quotes.destroy-success'),
        ], 200);
    }
}
