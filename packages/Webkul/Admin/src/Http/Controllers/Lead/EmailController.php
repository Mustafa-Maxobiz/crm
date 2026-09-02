<?php

namespace Webkul\Admin\Http\Controllers\Lead;

use Illuminate\Support\Facades\Event;
use Webkul\Admin\Http\Controllers\Mail\EmailController as BaseEmailController;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Lead\Services\SourceAccessService;

class EmailController extends BaseEmailController
{
    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        $leadId = request('lead_id') ?: request()->route('id');

        if (! $this->canEditLead($leadId)) {
            return response()->json([
                'message' => trans('admin::app.leads.source-access-denied'),
            ], 403);
        }

        request()->merge(['lead_id' => $leadId]);

        $response = json_decode(parent::store()->getContent(), true);

        return response()->json([
            'data'    => $this->transformToActivity($response['data']),
            'message' => $response['message'],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function detach($id)
    {
        if (! $this->canEditLead($id)) {
            return response()->json([
                'message' => trans('admin::app.leads.source-access-denied'),
            ], 403);
        }

        Event::dispatch('email.update.before', request()->input('email_id'));

        $email = $this->emailRepository->update([
            'lead_id' => null,
        ], request()->input('email_id'));

        Event::dispatch('email.update.after', $email);

        return response()->json([
            'message' => trans('admin::app.mail.update-success'),
        ]);
    }

    /**
     * Transform the email data to activity resource.
     *
     * @param  array  $data
     * @return \Webkul\Admin\Http\Resources\ActivityResource
     */
    public function transformToActivity($data)
    {
        return new ActivityResource((object) [
            'id'            => $data['id'],
            'parent_id'     => $data['parent_id'],
            'title'         => $data['subject'],
            'type'          => 'email',
            'is_done'       => 1,
            'comment'       => $data['reply'],
            'schedule_from' => null,
            'schedule_to'   => null,
            'user'          => auth()->guard('user')->user(),
            'participants'  => [],
            'location'      => null,
            'additional'    => json_encode([
                'folders' => $data['folders'],
                'from'    => $data['from'],
                'to'      => $data['reply_to'],
                'cc'      => $data['cc'],
                'bcc'     => $data['bcc'],
            ]),
            'files'         => array_map(function ($attachment) {
                return (object) $attachment;
            }, $data['attachments']),
            'created_at'    => $data['created_at'],
            'updated_at'    => $data['updated_at'],
        ]);
    }

    protected function canEditLead($leadId): bool
    {
        if (! filled($leadId)) {
            return false;
        }

        $lead = $this->leadRepository->find((int) $leadId);

        return $lead && app(SourceAccessService::class)->canEditLead($lead);
    }
}
