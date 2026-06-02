<x-admin::emails.layout>
    <div style="margin-bottom: 34px;">
        @if ($isOverdue)
            <p style="font-weight: bold; font-size: 20px; color: #DC2626; margin-bottom: 20px;">
                ⚠️ OVERDUE FOLLOW-UP REMINDER
            </p>
        @else
            <p style="font-weight: bold; font-size: 20px; color: #F59E0B; margin-bottom: 20px;">
                📅 FOLLOW-UP DUE TODAY
            </p>
        @endif

        <p style="font-size: 16px; color: #384252; line-height: 24px; margin-bottom: 20px;">
            Hello {{ $lead->user->name }},
        </p>

        <p style="font-size: 16px; color: #384252; line-height: 24px; margin-bottom: 20px;">
            This is a reminder that you have a follow-up scheduled for the following lead:
        </p>

        <!-- Lead Details Card -->
        <div style="background-color: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #6B7280; width: 40%;">Lead Title:</td>
                    <td style="padding: 8px 0; color: #111827;">{{ $lead->title }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #6B7280;">Contact Person:</td>
                    <td style="padding: 8px 0; color: #111827;">
                        {{ $lead->person ? $lead->person->name : 'No contact assigned' }}
                    </td>
                </tr>
                @if ($lead->person && $lead->person->emails)
                    <tr>
                        <td style="padding: 8px 0; font-weight: 600; color: #6B7280;">Email:</td>
                        <td style="padding: 8px 0; color: #111827;">
                            @php
                                $emails = is_string($lead->person->emails) ? json_decode($lead->person->emails, true) : $lead->person->emails;
                                $firstEmail = is_array($emails) && !empty($emails) ? ($emails[0]['value'] ?? '') : '';
                            @endphp
                            {{ $firstEmail }}
                        </td>
                    </tr>
                @endif
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #6B7280;">Lead Value:</td>
                    <td style="padding: 8px 0; color: #111827;">{{ core()->formatBasePrice($lead->lead_value) }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #6B7280;">Stage:</td>
                    <td style="padding: 8px 0; color: #111827;">{{ $lead->stage->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #6B7280;">Scheduled Follow-up:</td>
                    <td style="padding: 8px 0; color: #111827;">
                        {{ \Carbon\Carbon::parse($lead->next_followup_date)->format('M d, Y h:i A') }}
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #6B7280;">Previous Attempts:</td>
                    <td style="padding: 8px 0; color: #111827;">{{ $lead->followup_count }}</td>
                </tr>
                @if ($lead->followup_notes)
                    <tr>
                        <td style="padding: 8px 0; font-weight: 600; color: #6B7280; vertical-align: top;">Notes:</td>
                        <td style="padding: 8px 0; color: #111827;">{{ $lead->followup_notes }}</td>
                    </tr>
                @endif
            </table>
        </div>

        <!-- Action Button -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ route('admin.leads.view', $lead->id) }}" 
               style="display: inline-block; padding: 12px 24px; background-color: #2563EB; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px;">
                View Lead Details
            </a>
        </div>

        <p style="font-size: 14px; color: #6B7280; line-height: 20px; margin-top: 20px;">
            <strong>Tip:</strong> After completing the follow-up, remember to mark it as complete in the lead details page and schedule the next follow-up if needed.
        </p>
    </div>
</x-admin::emails.layout>
