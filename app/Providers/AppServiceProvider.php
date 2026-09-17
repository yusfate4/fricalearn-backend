<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema; // 🚀 Added for the Namecheap fix
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
   public function boot(): void
    {
        // 0. Namecheap/MySQL Compatibility Fix
        Schema::defaultStringLength(191);

        // 1. Password Reset URL Customization
        ResetPassword::createUrlUsing(function ($user, string $token) {
            // 🚀 THE FIX: Use your official domain instead of Netlify
            // We use rawurlencode to ensure the @ symbol doesn't break the link
            $frontendUrl = 'https://fricalearn.com'; 
            return $frontendUrl . '/reset-password?token=' . $token . '&email=' . rawurlencode($user->email);
        });

        // 2. Email Verification Template Customization
        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            return (new MailMessage)
                ->subject('Please verify your FricaLearn email address')
            ->greeting('Hello ' . ($this->notifiable->name ?? 'there') . '!')
            ->line('Thank you for registering with FricaLearn Diaspora Academy.')
            ->line('Please click the button below to verify your email address and activate your account.')
            ->action('Verify Email Address', $url)
            ->line('This link will expire in 60 minutes.')
            ->line('If you did not create a FricaLearn account, no further action is required.')
            ->salutation('The FricaLearn Team');
        });
    }
}