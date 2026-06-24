<?php

namespace Webkul\MetaLead\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Webkul\MetaLead\Models\MetaLead;

class NewMetaLeadMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MetaLead $metaLead,
        public string $recipientEmail,
    ) {}

    public function build()
    {
        return $this
            ->to($this->recipientEmail)
            ->subject('New Meta Lead: '.($this->metaLead->full_name ?: 'Unknown'))
            ->view('meta_lead::emails.new-meta-lead', [
                'metaLead' => $this->metaLead,
            ]);
    }
}
