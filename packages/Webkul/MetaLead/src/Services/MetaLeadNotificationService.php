<?php

namespace Webkul\MetaLead\Services;

use Illuminate\Support\Facades\Mail;
use Webkul\MetaLead\Mail\NewMetaLeadMail;
use Webkul\MetaLead\Models\MetaLead;
use Webkul\User\Repositories\UserRepository;

class MetaLeadNotificationService
{
    public function __construct(protected UserRepository $userRepository) {}

    public function notifyTeam(MetaLead $metaLead): void
    {
        if ($metaLead->is_duplicate) {
            return;
        }

        $metaLead->loadMissing('users');

        if ($metaLead->users->isNotEmpty()) {
            $recipients = $metaLead->users->pluck('email')->filter()->unique()->values()->all();
        } else {
            $recipients = $this->resolveRecipients();
        }

        if (empty($recipients)) {
            return;
        }

        foreach ($recipients as $email) {
            Mail::queue(new NewMetaLeadMail($metaLead, $email));
        }
    }

    protected function resolveRecipients(): array
    {
        $configured = config('meta_lead.notification_emails');

        if ($configured) {
            return array_filter(array_map('trim', explode(',', $configured)));
        }

        return $this->userRepository
            ->getModel()
            ->whereHas('role', fn ($query) => $query->where('permission_type', 'all'))
            ->where('status', 1)
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
