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
        // This prevents the "Specified key was too long" error
        Schema::defaultStringLength(191);

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