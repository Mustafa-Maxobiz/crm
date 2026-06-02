#!/bin/bash

# Follow-up Reminder Scheduler Setup Script
# This script helps you set up the Laravel scheduler for automated follow-up reminders

echo "================================================"
echo "Follow-up Reminder Scheduler Setup"
echo "================================================"
echo ""

# Get the current directory
CURRENT_DIR=$(pwd)

echo "Current project directory: $CURRENT_DIR"
echo ""

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan file not found. Please run this script from the project root directory."
    exit 1
fi

echo "✓ Project directory verified"
echo ""

# Test the command
echo "Testing the follow-up reminder command..."
php artisan leads:send-followup-reminders
echo ""

# Check if command executed successfully
if [ $? -eq 0 ]; then
    echo "✓ Command executed successfully"
else
    echo "❌ Command failed. Please check the error above."
    exit 1
fi

echo ""
echo "================================================"
echo "Scheduler Setup Options"
echo "================================================"
echo ""
echo "Choose your setup method:"
echo ""
echo "1. Production Server (Add to crontab)"
echo "2. Development (Run scheduler manually)"
echo "3. Skip (I'll set it up later)"
echo ""
read -p "Enter your choice (1-3): " choice

case $choice in
    1)
        echo ""
        echo "================================================"
        echo "Production Server Setup"
        echo "================================================"
        echo ""
        echo "To enable the scheduler on a production server, add this line to your crontab:"
        echo ""
        echo "* * * * * cd $CURRENT_DIR && php artisan schedule:run >> /dev/null 2>&1"
        echo ""
        echo "To edit your crontab, run:"
        echo "  crontab -e"
        echo ""
        echo "Then paste the line above and save."
        echo ""
        read -p "Press Enter to continue..."
        ;;
    2)
        echo ""
        echo "================================================"
        echo "Development Setup"
        echo "================================================"
        echo ""
        echo "For local development, you can run the scheduler manually."
        echo ""
        read -p "Do you want to start the scheduler now? (y/n): " start_now
        if [ "$start_now" = "y" ] || [ "$start_now" = "Y" ]; then
            echo ""
            echo "Starting Laravel scheduler..."
            echo "Press Ctrl+C to stop"
            echo ""
            php artisan schedule:work
        else
            echo ""
            echo "To start the scheduler later, run:"
            echo "  php artisan schedule:work"
            echo ""
        fi
        ;;
    3)
        echo ""
        echo "Setup skipped. You can run this script again later."
        echo ""
        ;;
    *)
        echo ""
        echo "Invalid choice. Setup cancelled."
        echo ""
        exit 1
        ;;
esac

echo ""
echo "================================================"
echo "Setup Information"
echo "================================================"
echo ""
echo "✓ Follow-up reminder command: leads:send-followup-reminders"
echo "✓ Schedule: Daily at 9:00 AM UTC"
echo "✓ Documentation: FOLLOWUP_SYSTEM.md"
echo ""
echo "To test the command manually:"
echo "  php artisan leads:send-followup-reminders"
echo ""
echo "To change the schedule time, edit:"
echo "  app/Console/Kernel.php"
echo ""
echo "================================================"
echo "Setup Complete!"
echo "================================================"
