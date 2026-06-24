<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Meta Lead</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>New Meta Lead Received</h2>

    <p>A new lead has been submitted via Meta Lead Ads.</p>

    <table style="border-collapse: collapse; width: 100%; max-width: 500px;">
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Name</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $metaLead->full_name ?: 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Phone</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $metaLead->phone ?: 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Email</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $metaLead->email ?: 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Campaign</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $metaLead->campaign_name ?: 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Form</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $metaLead->form_name ?: 'N/A' }}</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">Received</td>
            <td style="padding: 8px; border: 1px solid #ddd;">{{ $metaLead->received_at?->format('M d, Y H:i') }}</td>
        </tr>
    </table>

    @if ($metaLead->lead_id)
        <p style="margin-top: 20px;">
            <a href="{{ url(config('app.admin_path').'/leads/view/'.$metaLead->lead_id) }}">
                View Lead in CRM
            </a>
        </p>
    @endif
</body>
</html>
