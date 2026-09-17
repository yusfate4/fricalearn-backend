<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends VerifyEmail
{
    /**
     * Build the mail message with clean English — no Yoruba greeting.
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Please verify your FricaLearn email address')
            ->greeting('Hello ' . ($this->notifiable->name ?? 'there') . '!')
            ->line('Thank you for registering with FricaLearn Diaspora Academy.')
            ->line('Please click the button below to verify your email address and activate your account.')
            ->action('Verify Email Address', $url)
            ->line('This link will expire in 60 minutes.')
            ->line('If you did not create a FricaLearn account, no further action is required.')
            ->salutation('The FricaLearn Team');
    }

    /**
     * Generate the signed verification URL pointing to the API.
     * Uses APP_URL from .env — must be https://api.fricalearn.com
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(
                Config::get('auth.verification.expire', 60)
            ),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
