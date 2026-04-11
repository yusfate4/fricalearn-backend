<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\EnrollmentPayment;

class NewPaymentSubmitted extends Notification
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
            ->subject('🔔 New Payment Receipt Uploaded: ' . $this->payment->child_name)
            ->greeting('Hello, Admin!')
            ->line('A parent has just uploaded a new payment receipt for review.')
            ->line('**Payment Details:**')
            ->line('👤 **Parent:** ' . $this->payment->parent->name)
            ->line('🎓 **Student:** ' . $this->payment->child_name)
            ->line('💰 **Amount:** ' . $this->payment->currency . ' ' . number_format($this->payment->amount, 2))
            ->line('📚 **Course:** ' . $this->payment->course->title)
            ->action('Review & Approve Payment', url('https://fricalearn.com/admin/payments'))
            ->line('Please verify the receipt image in the dashboard to activate the student account.')
            ->salutation('FricaLearn Automation System');
    }
}