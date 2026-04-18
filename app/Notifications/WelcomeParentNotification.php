<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeParentNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
   public function toMail($notifiable)
{
    return (new \Illuminate\Notifications\Messages\MailMessage)
        ->subject('Welcome to FricaLearn!')
        ->greeting('Ẹ káàbọ̀, ' . $notifiable->name . '!')
        ->line('We are thrilled to have you join the FricaLearn Diaspora Academy.')
        ->line('Your account has been set up successfully. You can now log in to add, monitor your child’s progress, manage their lessons, and join our vibrant community.')
        ->action('Go to Parent Portal', url('/parent/dashboard'))
        ->line('Thank you for choosing to invest in your child’s cultural heritage!');
}

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
