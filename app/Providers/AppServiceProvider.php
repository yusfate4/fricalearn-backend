<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail; // 🚀 Added
use Illuminate\Notifications\Messages\MailMessage; // 🚀 Added

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
        // 1. Password Reset URL Customization
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return 'https://fricalearn.netlify.app/reset-password?token='.$token.'&email='.$user->email;
        });

        // 2. Email Verification Template Customization
        VerifyEmail::toMailUsing(function ($notifiable, $url) {
            return (new MailMessage)
                ->subject('Ẹ kú àbọ̀! Verify Your FricaLearn Account')
                ->greeting('Hello ' . $notifiable->name . '!')
                ->line('Welcome to FricaLearn Diaspora Academy. We are excited to have you preserve our heritage.')
                ->action('Verify Email Address', $url)
                ->line('If you did not create an account, no further action is required.')
                ->salutation('Olukọ from FricaLearn');
        });
    }
}