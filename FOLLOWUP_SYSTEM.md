# Follow-up Reminders & Automation System

## Overview
The follow-up system allows you to track and automate reminders for leads that require follow-up contact. The system includes manual tracking, visual indicators, and automated daily reminders.

---

## Features Implemented

### 1. **Follow-up Fields**
Each lead now has the following follow-up tracking fields:
- **Next Follow-up Date**: When the next follow-up is scheduled
- **Follow-up Count**: Number of follow-up attempts made
- **Last Follow-up Date**: When the last follow-up was completed
- **Follow-up Notes**: Optional notes about the follow-up

### 2. **Lead Creation/Editing**
- Added "Next Follow-up Date" field in create and edit forms
- Users can set a reminder date when creating or editing a lead
- Field supports date and time selection

### 3. **Follow-up Tracking View**
Located on the lead detail page (between attributes and contact person sections):

**Visual Indicators:**
- 🔴 **Red "Overdue" badge**: Follow-up date has passed
- 🟡 **Yellow "Due Today" badge**: Follow-up is scheduled for today
- 🔵 **Blue counter badge**: Shows total follow-up attempts

**Statistics Display:**
- Total follow-up attempts counter
- Next scheduled follow-up date
- Last follow-up date (shown as "X days ago")
- Follow-up notes (if any)

**Actions:**
- "Mark Follow-up Complete" button (visible when follow-up is scheduled)
- Clicking this button:
  - Increments the follow-up counter
  - Records the completion timestamp
  - Clears the next follow-up date
  - Shows success message

### 4. **Automated Cron Job System**

#### Command Details
- **Command Name**: `leads:send-followup-reminders`
- **Description**: Checks for leads with due or overdue follow-ups and creates reminder activities

#### Schedule
- **Frequency**: Daily
- **Time**: 9:00 AM UTC
- **Timezone**: UTC (can be changed in `app/Console/Kernel.php`)

#### What It Does
1. Scans all leads with `next_followup_date` set
2. Finds leads where follow-up is due today or overdue
3. Excludes leads in "Won" or "Lost" stages
4. Creates a "Call" activity as a reminder for each due follow-up
5. Assigns the activity to the lead's sales owner
6. Logs all actions to the daily log file

#### Activity Created
Each reminder creates an activity with:
- **Type**: Call
- **Title**: "Follow-up Reminder: [Lead Title] (OVERDUE/DUE TODAY)"
- **Comment**: Detailed information including:
  - Scheduled date
  - Lead title
  - Contact person
  - Previous attempt count
  - Status (Overdue/Due Today)
  - Follow-up notes
- **Assigned to**: Lead's sales owner
- **Status**: Not done (requires action)

---

## How Users Receive Notifications

### 1. **In-App Activity Notifications** (Always Enabled)
When the cron job runs daily at 9:00 AM UTC, it creates an **Activity** for each due follow-up:

**Where to Find:**
- Navigate to **Activities** menu in the CRM
- URL: `http://your-domain/admin/activities`
- Filter by "Not Done" to see pending reminders
- The activity will show:
  - Title: "Follow-up Reminder: [Lead Title] (OVERDUE/DUE TODAY)"
  - Type: Call
  - Status: Not Done
  - Full details in the comment section

**Activity Details Include:**
- Lead title and contact information
- Scheduled follow-up date
- Number of previous attempts
- Status (Overdue or Due Today)
- Follow-up notes (if any)
- Direct link to the lead

### 2. **Email Notifications** (Optional)
If enabled, users receive a professional email with:

**Email Contains:**
- Subject: "Follow-up Reminder: [Lead Title] (OVERDUE/DUE TODAY)"
- Color-coded header (Red for overdue, Yellow for due today)
- Complete lead details in a formatted table
- "View Lead Details" button linking directly to the lead
- Tips for completing the follow-up

**Email Recipients:**
- Sent to the lead's assigned sales owner
- Uses the email address from the user's profile

**To Enable Email Notifications:**
```bash
# Edit app/Console/Kernel.php and add --email flag
$schedule->command('leads:send-followup-reminders --email')
    ->dailyAt('09:00')
    ->timezone('UTC');
```

### 3. **Lead Detail Page Indicators**
When viewing a lead with a due follow-up:
- **Red "Overdue" badge**: Follow-up date has passed
- **Yellow "Due Today" badge**: Follow-up is scheduled for today
- **Follow-up counter**: Shows total attempts made
- Statistics showing next and last follow-up dates

---

## How to Use

### Setting Up a Follow-up
1. Create or edit a lead
2. Fill in the "Next Follow-up Date" field with your desired reminder date/time
3. Optionally add notes in the "Follow-up Notes" field
4. Save the lead

### Viewing Follow-up Status
1. Open the lead detail page
2. Scroll to the "Follow-up Tracking" section
3. View the statistics and status badges
4. Check if the follow-up is overdue, due today, or upcoming

### Completing a Follow-up
1. After contacting the lead, open the lead detail page
2. Click "Mark Follow-up Complete" button
3. The system will:
   - Increment the follow-up counter
   - Record the completion time
   - Clear the next follow-up date
4. Edit the lead again to schedule the next follow-up if needed

### Receiving Automated Reminders
1. Ensure the Laravel scheduler is running (see Setup section below)
2. Every day at 9:00 AM UTC, the system checks for due follow-ups
3. If you have leads with due follow-ups, an activity will be created
4. Check your activities list to see the reminders
5. The activity will show all relevant information about the follow-up

---

## Setup Instructions

### 1. Enable Laravel Scheduler
The cron job requires Laravel's task scheduler to be running.

#### On Linux/Mac (Production Server):
Add this to your crontab:
```bash
# Edit crontab
crontab -e

# Add this line (replace /path/to/crm with your actual path)
* * * * * cd /path/to/crm && php artisan schedule:run >> /dev/null 2>&1
```

#### On Local Development:
Run the scheduler manually:
```bash
# Keep this running in a terminal
php artisan schedule:work
```

### 2. Verify Command Registration
Check if the command is available:
```bash
php artisan list | grep followup
```

You should see:
```
leads:send-followup-reminders  Send follow-up reminders for leads that are due today or overdue
```

### 3. Test the Command Manually
Run the command to test:
```bash
php artisan leads:send-followup-reminders
```

### 4. Change Schedule Time (Optional)
To change when reminders are sent, edit `app/Console/Kernel.php`:

```php
// Current: Daily at 9:00 AM UTC with email notifications
$schedule->command('leads:send-followup-reminders --email')
    ->dailyAt('09:00')
    ->timezone('UTC');

// Without email notifications (activities only)
$schedule->command('leads:send-followup-reminders')
    ->dailyAt('09:00')
    ->timezone('UTC');

// Examples:
// Every day at 8:00 AM in your timezone
->dailyAt('08:00')->timezone('Asia/Karachi');

// Twice daily (9 AM and 5 PM)
->twiceDaily(9, 17);

// Every weekday at 9 AM
->weekdays()->dailyAt('09:00');

// Multiple times per day
->cron('0 9,14,17 * * *'); // 9 AM, 2 PM, 5 PM
```

### 5. Configure Email Settings (For Email Notifications)
Ensure your `.env` file has proper mail configuration:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourcrm.com
MAIL_FROM_NAME="${APP_NAME}"
```

**Popular Mail Services:**
- **Gmail**: Use `smtp.gmail.com` (port 587)
- **SendGrid**: Use `smtp.sendgrid.net` (port 587)
- **Mailgun**: Use `smtp.mailgun.org` (port 587)
- **Mailtrap** (for testing): Use `smtp.mailtrap.io` (port 2525)

**Test Email Configuration:**
```bash
php artisan tinker
Mail::raw('Test email', function($msg) { $msg->to('your@email.com')->subject('Test'); });
```

---

## Technical Details

### Files Modified/Created

#### New Files:
- `packages/Webkul/Lead/src/Console/Commands/SendFollowupReminders.php` - Cron job command
- `packages/Webkul/Lead/src/Mail/FollowupReminderMail.php` - Email notification class
- `packages/Webkul/Lead/src/Resources/views/emails/followup-reminder.blade.php` - Email template
- `packages/Webkul/Admin/src/Resources/views/leads/view/followup.blade.php` - Follow-up tracking view
- `packages/Webkul/Lead/src/Database/Migrations/2026_05_15_162619_add_followup_fields_to_leads_table.php` - Database migration
- `packages/Webkul/Attribute/src/Database/Migrations/2026_05_15_162827_add_followup_attributes.php` - Attribute migration

#### Modified Files:
- `packages/Webkul/Lead/src/Models/Lead.php` - Added fillable fields, casts, and helper methods
- `packages/Webkul/Lead/src/Providers/LeadServiceProvider.php` - Registered command
- `packages/Webkul/Admin/src/Http/Controllers/Lead/LeadController.php` - Added followupComplete() method
- `packages/Webkul/Admin/src/Routes/Admin/leads-routes.php` - Added follow-up complete route
- `packages/Webkul/Admin/src/Resources/views/leads/view.blade.php` - Included follow-up view
- `packages/Webkul/Admin/src/Resources/views/leads/create.blade.php` - Added follow-up date field
- `packages/Webkul/Admin/src/Resources/views/leads/edit.blade.php` - Added follow-up date field
- `packages/Webkul/Admin/src/Resources/lang/en/app.php` - Added translations
- `packages/Webkul/Installer/src/Database/Seeders/Attribute/AttributeSeeder.php` - Added attribute
- `packages/Webkul/Installer/src/Resources/lang/en/app.php` - Added translations
- `app/Console/Kernel.php` - Scheduled the command

### Database Schema
```sql
-- Added to leads table
next_followup_date   DATETIME NULL
followup_count       INT DEFAULT 0
last_followup_date   DATETIME NULL
followup_notes       TEXT NULL
```

### Helper Methods (Lead Model)
```php
// Check if follow-up is overdue
$lead->isFollowupDue()

// Check if follow-up is today
$lead->isFollowupToday()

// Increment follow-up counter and update last follow-up date
$lead->incrementFollowupCount()
```

---

## Troubleshooting

### Reminders Not Being Sent
1. **Check if scheduler is running**:
   ```bash
   # Check crontab
   crontab -l
   
   # Or run scheduler manually
   php artisan schedule:work
   ```

2. **Verify command works**:
   ```bash
   # Without email
   php artisan leads:send-followup-reminders
   
   # With email
   php artisan leads:send-followup-reminders --email
   ```

3. **Check logs**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Activities Not Appearing
1. Check if ActivityRepository is working
2. Verify the lead has a user assigned
3. Check database for created activities:
   ```bash
   mysql -u root krayin_crm -e "SELECT * FROM activities ORDER BY id DESC LIMIT 5;"
   ```
4. Navigate to Activities page in CRM: `http://your-domain/admin/activities`

### Emails Not Being Sent
1. **Check mail configuration**:
   ```bash
   php artisan config:cache
   php artisan tinker
   # Test email
   Mail::raw('Test', function($msg) { $msg->to('test@example.com')->subject('Test'); });
   ```

2. **Check if --email flag is used**:
   ```bash
   # View scheduled commands
   php artisan schedule:list
   ```

3. **Check mail logs**:
   ```bash
   tail -f storage/logs/laravel.log | grep -i mail
   ```

4. **Common issues**:
   - SMTP credentials incorrect
   - Firewall blocking SMTP port
   - Mail server requires authentication
   - FROM email not verified (for services like SendGrid)

### Email Shows Broken Layout
1. Clear view cache:
   ```bash
   php artisan view:clear
   ```

2. Check if email layout exists:
   ```bash
   ls -la packages/Webkul/Admin/src/Resources/views/emails/layout.blade.php
   ```

### Change Not Reflecting
Clear all caches:
```bash
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

---

## Future Enhancements (Optional)

### Email Notifications
Add email sending to the command:
```php
Mail::to($lead->user->email)->send(new FollowupReminderMail($lead));
```

### SMS Notifications
Integrate with SMS service (Twilio, etc.)

### Browser Push Notifications
Use Laravel Echo and Pusher for real-time notifications

### Follow-up Templates
Create predefined follow-up schedules (e.g., "3 days, 7 days, 14 days")

### Follow-up Reports
Dashboard widget showing:
- Total overdue follow-ups
- Follow-ups due today
- Follow-up completion rate
- Average response time

---

## Support

For issues or questions, check:
1. Laravel logs: `storage/logs/laravel.log`
2. Cron logs: Check system cron logs
3. Command output: Run manually to see errors

---

**Last Updated**: May 15, 2026
**Version**: 1.0
