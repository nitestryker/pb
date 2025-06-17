
#!/bin/bash

# Setup script for expiration reminders cron job
# This script sets up a daily cron job to run expiration reminders

echo "Setting up expiration reminder cron job..."

# Add cron job to run daily at 9 AM
(crontab -l 2>/dev/null; echo "0 9 * * * cd $(pwd) && php expiration_reminder.php >> logs/expiration_reminders.log 2>&1") | crontab -

# Create logs directory if it doesn't exist
mkdir -p logs

echo "Cron job setup complete!"
echo "Expiration reminders will run daily at 9:00 AM"
echo "Logs will be saved to logs/expiration_reminders.log"
echo ""
echo "To test the reminder system manually, run:"
echo "php expiration_reminder.php"
