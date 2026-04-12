<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleUpdated extends Notification
{
    use Queueable;

    protected $scheduleDetails;

    public function __construct($scheduleDetails)
    {
        $this->scheduleDetails = $scheduleDetails;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🗓️ Save the Date: New Class Schedule for FricaLearn')
            ->greeting('Ẹ n lẹ́, Parent!')
            ->line('We are updating our global master schedule to better serve our students.')
            ->line('**The new weekly class time is now fixed for:**')
            ->line('🚀 ' . $this->scheduleDetails)
            ->line('Please update your calendars to ensure your child doesn\'t miss out on our live cultural sessions.')
            ->action('View Full Calendar', url('https://fricalearn.com/dashboard'))
            ->line('Thank you for being part of the FricaLearn family!')
            ->salutation('Warm regards, The FricaLearn Team');
    }
}