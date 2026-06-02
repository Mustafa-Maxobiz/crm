<?php

namespace Webkul\Lead\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Webkul\Lead\Contracts\Lead;

class FollowupReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        public $lead,
        public $isOverdue = false
    ) {}

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $status = $this->isOverdue ? 'OVERDUE' : 'DUE TODAY';
        
        return $this->subject("Follow-up Reminder: {$this->lead->title} ({$status})")
            ->view('lead::emails.followup-reminder')
            ->with([
                'lead'      => $this->lead,
                'isOverdue' => $this->isOverdue,
                'status'    => $status,
            ]);
    }
}
