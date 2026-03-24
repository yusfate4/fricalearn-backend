<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RewardRedeemed extends Mailable
{
    use Queueable, SerializesModels;

    public $student;
    public $reward;

    public function __construct($student, $reward)
    {
        $this->student = $student;
        $this->reward = $reward;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎉 New Reward Redemption: ' . $this->student->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reward_redeemed',
        );
    }
}