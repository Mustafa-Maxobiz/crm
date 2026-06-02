<?php

namespace Webkul\Lead\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Lead\Mail\FollowupReminderMail;
use Webkul\Lead\Repositories\LeadRepository;

class SendFollowupReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:send-followup-reminders {--email : Send email notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send follow-up reminders for leads that are due today or overdue';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected ActivityRepository $activityRepository
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for leads with due follow-ups...');

        $sendEmail = $this->option('email');

        // Get leads with follow-ups due today or overdue
        $leads = $this->leadRepository
            ->whereNotNull('next_followup_date')
            ->where('next_followup_date', '<=', Carbon::now()->endOfDay())
            ->whereNotIn('lead_pipeline_stage_id', function ($query) {
                $query->select('id')
                    ->from('lead_pipeline_stages')
                    ->whereIn('code', ['won', 'lost']);
            })
            ->with(['user', 'person', 'stage'])
            ->get();

        if ($leads->isEmpty()) {
            $this->info('No leads with due follow-ups found.');
            return 0;
        }

        $this->info("Found {$leads->count()} lead(s) with due follow-ups.");

        $remindersSent = 0;
        $emailsSent = 0;

        foreach ($leads as $lead) {
            if (!$lead->user) {
                continue;
            }

            try {
                // Create a reminder activity for the lead
                $this->createFollowupActivity($lead);
                $remindersSent++;
                
                // Send email if option is enabled and user has email
                if ($sendEmail && $lead->user->email) {
                    $this->sendEmailReminder($lead);
                    $emailsSent++;
                    $this->line("✓ Reminder created and email sent for lead: {$lead->title} (to: {$lead->user->email})");
                } else {
                    $this->line("✓ Reminder created for lead: {$lead->title} (assigned to: {$lead->user->name})");
                }
            } catch (\Exception $e) {
                $this->error("✗ Failed to create reminder for lead: {$lead->title}");
                $this->error("  Error: {$e->getMessage()}");
            }
        }

        $this->info("Successfully created {$remindersSent} follow-up reminder(s).");
        
        if ($sendEmail) {
            $this->info("Successfully sent {$emailsSent} email notification(s).");
        }

        return 0;
    }

    /**
     * Create a follow-up activity for the lead
     */
    protected function createFollowupActivity($lead)
    {
        $followupDate = Carbon::parse($lead->next_followup_date);
        $isOverdue = $followupDate->isPast();
        
        $status = $isOverdue ? 'OVERDUE' : 'DUE TODAY';
        
        $title = "Follow-up Reminder: {$lead->title} ({$status})";
        
        $comment = sprintf(
            "This is an automated reminder for the follow-up scheduled on %s.\n\n" .
            "Lead: %s\n" .
            "Contact: %s\n" .
            "Previous Attempts: %d\n" .
            "Status: %s\n\n" .
            "%s",
            $followupDate->format('M d, Y H:i A'),
            $lead->title,
            $lead->person ? $lead->person->name : 'No contact assigned',
            $lead->followup_count,
            $status,
            $lead->followup_notes ? "Notes: {$lead->followup_notes}" : ''
        );

        // Create a task activity as a reminder
        $this->activityRepository->create([
            'type'       => 'call', // Using 'call' type for follow-up reminders
            'title'      => $title,
            'comment'    => $comment,
            'is_done'    => 0,
            'schedule_from' => Carbon::now(),
            'schedule_to'   => Carbon::now()->addHour(),
            'user_id'    => $lead->user_id,
            'participants' => [
                'users' => [$lead->user_id],
            ],
        ]);

        // Attach the activity to the lead
        $activity = $this->activityRepository->orderBy('id', 'desc')->first();
        if ($activity) {
            $activity->leads()->attach($lead->id);
        }

        // Log the reminder
        \Log::channel('daily')->info("Follow-up Reminder created for lead #{$lead->id}: {$lead->title}");
    }

    /**
     * Send email reminder to the user
     */
    protected function sendEmailReminder($lead)
    {
        $followupDate = Carbon::parse($lead->next_followup_date);
        $isOverdue = $followupDate->isPast();

        Mail::to($lead->user->email)->send(new FollowupReminderMail($lead, $isOverdue));

        \Log::channel('daily')->info("Follow-up email sent to {$lead->user->email} for lead #{$lead->id}");
    }
}
