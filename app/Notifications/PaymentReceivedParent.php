<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\EnrollmentPayment;

class PaymentReceivedParent extends Notification
{
    use Queueable;

    protected $payment;

    public function __construct(EnrollmentPayment $payment)
    {
        $this->payment = $payment;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('We’ve Received Your Payment Proof! 🚀')
            ->greeting('Ẹ n lẹ́, ' . $notifiable->name . '!')
            ->line('Thank you for submitting the payment proof for **' . $this->payment->child_name . '**.')
            ->line('Our team is currently verifying the receipt. This usually takes less than 24 hours.')
            ->line('**Summary of Submission:**')
            ->line('📚 **Course:** ' . ($this->payment->course->title ?? 'Selected Course'))
            ->line('💰 **Amount:** ' . $this->payment->currency . ' ' . number_format($this->payment->amount, 2))
            ->line('Once verified, you will receive another email with your child\'s login credentials and account activation details.')
            ->action('View My Dashboard', url('https://fricalearn.com/parent/dashboard'))
            ->line('Stay tuned, your child will be learning soon!')
            ->salutation('Warm regards, The FricaLearn Team');
    }
}