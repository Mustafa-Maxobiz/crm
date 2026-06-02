# Follow-up Notification Guide

## 📬 How Users Receive Follow-up Reminders

When a follow-up is due, users are notified through **TWO channels**:

---

## 1. 📋 In-App Activity Notifications (Always Active)

### Where to Find:
1. Click on **"Activities"** in the main navigation menu
2. Or go to: `http://your-crm-domain/admin/activities`

### What You'll See:
- **Activity Type**: Call
- **Title**: "Follow-up Reminder: [Lead Title] (OVERDUE/DUE TODAY)"
- **Status**: Not Done (requires action)
- **Comment**: Full details including:
  - Scheduled follow-up date
  - Lead title and contact person
  - Previous attempt count
  - Status (Overdue or Due Today)
  - Follow-up notes

### Visual Indicators:
```
┌─────────────────────────────────────────────────────────┐
│ 📞 Call Activity                                        │
│ Follow-up Reminder: Website Redesign Project (OVERDUE)  │
│ Status: Not Done                                        │
│                                                         │
│ This is an automated reminder for the follow-up         │
│ scheduled on May 14, 2026 09:00 AM                      │
│                                                         │
│ Lead: Website Redesign Project                          │
│ Contact: John Smith                                     │
│ Previous Attempts: 2                                    │
│ Status: OVERDUE                                         │
│                                                         │
│ Notes: Client requested callback after budget approval  │
└─────────────────────────────────────────────────────────┘
```

### How to Use:
1. **View**: Click on the activity to see full details
2. **Take Action**: Contact the lead
3. **Mark Complete**: 
   - Click the activity's action menu
   - Select "Mark as Done"
4. **Update Lead**: Go to the lead and click "Mark Follow-up Complete"

---

## 2. 📧 Email Notifications (Optional)

### When Enabled:
Users receive a professional email at **9:00 AM UTC daily** (or your configured time)

### Email Details:

**Subject Line:**
```
Follow-up Reminder: [Lead Title] (OVERDUE/DUE TODAY)
```

**Email Content:**
```
┌──────────────────────────────────────────────────────┐
│ [Your CRM Logo]                                      │
│                                                      │
│ ⚠️ OVERDUE FOLLOW-UP REMINDER                        │
│                                                      │
│ Hello John Doe,                                      │
│                                                      │
│ This is a reminder that you have a follow-up         │
│ scheduled for the following lead:                    │
│                                                      │
│ ┌────────────────────────────────────────────────┐   │
│ │ Lead Details                                   │   │
│ ├────────────────────────────────────────────────┤   │
│ │ Lead Title:           Website Redesign Project │   │
│ │ Contact Person:       John Smith               │   │
│ │ Email:                john@example.com         │   │
│ │ Lead Value:           $5,000.00                │   │
│ │ Stage:                Negotiation              │   │
│ │ Scheduled Follow-up:  May 14, 2026 09:00 AM    │   │
│ │ Previous Attempts:    2                        │   │
│ │ Notes:                Client requested callback│   │
│ └────────────────────────────────────────────────┘   │
│                                                      │
│              [View Lead Details Button]              │
│                                                      │
│ Tip: After completing the follow-up, remember to     │
│ mark it as complete and schedule the next one.       │
│                                                      │
│ Cheers,                                              │
│ Your CRM Team                                        │
└──────────────────────────────────────────────────────┘
```

### Color Coding:
- **🔴 Red Header**: OVERDUE follow-ups
- **🟡 Yellow Header**: DUE TODAY follow-ups

### Email Features:
- ✅ Direct link to lead details
- ✅ Complete lead information
- ✅ Mobile-responsive design
- ✅ Professional formatting
- ✅ Actionable button

---

## 3. 🏷️ Lead Detail Page Badges

When viewing a lead with a due follow-up:

```
┌─────────────────────────────────────────────────────┐
│ Lead: Website Redesign Project                      │
│                                                     │
│ Follow-up Tracking                                  │
│ ┌─────────────────────────────────────────────────┐│
│ │ [2 attempts] [OVERDUE]                          ││
│ │                                                 ││
│ │ Total Attempts:        2                        ││
│ │ Next Follow-up:        May 14, 2026             ││
│ │ Last Follow-up:        3 days ago               ││
│ │                                                 ││
│ │ [✓ Mark Follow-up Complete]                     ││
│ └─────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────┘
```

**Badge Colors:**
- 🔴 **Red "Overdue"**: Follow-up date has passed
- 🟡 **Yellow "Due Today"**: Follow-up is today
- 🔵 **Blue Counter**: Shows total attempts

---

## 📅 Daily Workflow

### Morning (9:00 AM UTC):
1. **Automated System Runs**:
   - Scans all leads with scheduled follow-ups
   - Identifies due and overdue follow-ups
   - Creates activity reminders
   - Sends emails (if enabled)

### User Receives:
2. **Email Notification** (if enabled):
   - Check your inbox
   - Review lead details
   - Click "View Lead Details" button

3. **Activity Notification**:
   - Open Activities page
   - Filter by "Not Done"
   - See all pending follow-up reminders

### Taking Action:
4. **Contact the Lead**:
   - Call, email, or message the contact
   - Discuss the opportunity

5. **Mark Complete**:
   - Go to lead detail page
   - Click "Mark Follow-up Complete"
   - System increments counter
   - Records completion time

6. **Schedule Next Follow-up**:
   - Edit the lead
   - Set new "Next Follow-up Date"
   - Add notes if needed
   - Save

---

## ⚙️ Configuration

### Enable Email Notifications:

**Edit:** `app/Console/Kernel.php`

```php
// With email notifications
$schedule->command('leads:send-followup-reminders --email')
    ->dailyAt('09:00')
    ->timezone('UTC');

// Without email (activities only)
$schedule->command('leads:send-followup-reminders')
    ->dailyAt('09:00')
    ->timezone('UTC');
```

### Configure Email Settings:

**Edit:** `.env`

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourcrm.com
MAIL_FROM_NAME="Your CRM"
```

### Test Email:
```bash
php artisan leads:send-followup-reminders --email
```

---

## 🎯 Best Practices

### For Sales Teams:
1. **Check Activities Daily**: Make it part of your morning routine
2. **Respond Promptly**: Act on overdue follow-ups first
3. **Update Status**: Always mark follow-ups as complete
4. **Schedule Next**: Set the next follow-up immediately
5. **Add Notes**: Document what was discussed

### For Managers:
1. **Monitor Activities**: Check team's pending follow-ups
2. **Review Metrics**: Track follow-up completion rates
3. **Set Standards**: Define follow-up schedules for different lead types
4. **Provide Training**: Ensure team knows how to use the system

### For Administrators:
1. **Configure Email**: Set up reliable SMTP service
2. **Test Regularly**: Run manual tests monthly
3. **Monitor Logs**: Check for errors in `storage/logs/laravel.log`
4. **Adjust Timing**: Set reminder time based on team's work hours

---

## 📊 Notification Summary

| Notification Type | Always Active | Requires Setup | Location | Format |
|------------------|---------------|----------------|----------|--------|
| **Activity** | ✅ Yes | ❌ No | Activities Page | In-app |
| **Email** | ❌ No | ✅ Yes | User's Inbox | HTML Email |
| **Badge** | ✅ Yes | ❌ No | Lead Detail Page | Visual Badge |

---

## 🔔 Notification Frequency

- **Daily**: 9:00 AM UTC (configurable)
- **Scope**: Only due or overdue follow-ups
- **Exclusions**: Won and Lost leads are skipped
- **Recipients**: Lead's assigned sales owner

---

## 💡 Tips

### Reduce Notification Overload:
- Set realistic follow-up dates
- Complete follow-ups promptly
- Use follow-up notes effectively
- Archive or close inactive leads

### Improve Response Rate:
- Check activities first thing in the morning
- Enable email notifications for mobile access
- Set calendar reminders for important follow-ups
- Use the follow-up counter to prioritize

### Team Coordination:
- Reassign leads if sales owner is unavailable
- Use activity comments for team communication
- Share follow-up best practices
- Review follow-up metrics in team meetings

---

**Last Updated**: May 15, 2026
**Version**: 1.0
