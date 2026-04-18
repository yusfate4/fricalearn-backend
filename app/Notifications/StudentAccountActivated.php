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

public function toMail($notifiable)
{
    return (new \Illuminate\Notifications\Messages\MailMessage)
        ->subject('Great News! Your Child’s Learning Track is Active 🚀')
        ->greeting('Ẹ n lẹ́, ' . $notifiable->name . '!')
        // 🚀 CHANGED $this->trackName TO $this->courseName
        ->line('Great news! We have verified your payment for the **' . $this->courseName . '** track.')
        ->line('Your child\'s learning account has been officially activated. They can now begin their journey into African culture and language through the parent portal.')
        
        ->line('**How to Start Learning:**')
        ->line('1. **Login:** Use your registered parent email and password to access your dashboard.')
        ->line('2. **Select Child:** You will now see **' . $this->studentData['name'] . '** listed under your active students.')
        ->line('3. **Launch Portal:** Click on your child’s name to enter their personalized student portal where they can start their lessons immediately.')
        
        ->action('Login to Parent Dashboard', url('https://fricalearn.com/login'))
        
        ->line('By managing access through your portal, you can easily track their progress and stay involved in their learning journey.')
        ->line('We are excited to have you and your family in the FricaLearn community!')
        ->salutation('Warm regards, The FricaLearn Team');
}
}