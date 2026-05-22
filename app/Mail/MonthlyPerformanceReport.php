<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MonthlyPerformanceReport extends Mailable
{
    use Queueable, SerializesModels;

    public $student;
    public $report;

    public function __construct($student, $report)
    {
        $this->student = $student;
        $this->report = $report;
    }

    public function build()
    {
        return $this->subject('Monthly Progress Report: ' . $this->student->name)
                    ->view('emails.monthly_report');
    }
}