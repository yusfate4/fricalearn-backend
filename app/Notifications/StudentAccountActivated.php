<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudentAccountActivated extends Notification
{
    use Queueable;

    protected $studentData;
    protected $courseName;

    public function __construct($studentData, $courseName)
    {
        $this->studentData = $studentData;
        $this->courseName = $courseName;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🚀 Welcome to FricaLearn! ' . $this->studentData['name'] . '\'s Account is Ready')
            ->greeting('Ẹ n lẹ́, ' . $notifiable->name . '!')
            ->line('Great news! We have verified your payment for the **' . $this->courseName . '** track.')
            ->line('Your child\'s learning account has been officially activated. They can now begin their journey into African culture and language.')
            ->line('**Child\'s Login Credentials:**')
            ->line('📧 **Email:** ' . $this->studentData['email'])
            ->line('🔑 **Temporary Password:** student123')
            ->action('Start Learning Now', url('https://fricalearn.com/login'))
            ->line('Note: For security, please have your child update their password once they log in.')
            ->line('We are excited to have you in the FricaLearn family!')
            ->salutation('Warm regards, Dahud Yusuf & The FricaLearn Team');
    }
}