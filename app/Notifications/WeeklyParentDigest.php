<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyParentDigest extends Notification
{
    use Queueable;

    protected $reportData;

    /**
     * Pass the report data from the Command to the Notification
     */
    public function __construct($reportData)
    {
        $this->reportData = $reportData;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('📊 Your Weekly FricaLearn Academy Digest')
            ->greeting('Ẹ n lẹ́, Parent!')
            ->line('It has been an exciting week of learning at the academy! Here is a summary of your children\'s progress and cultural milestones.');

        // Loop through each child's data to build the email body
        foreach ($this->reportData as $data) {
            $mail->line('---'); // Divider
            $mail->line('**Student:** ' . $data['student_name']);
            
            if ($data['has_activity']) {
                $mail->line('✅ Lessons Completed: ' . $data['lessons_completed'])
                     ->line('🏆 Points Earned: ' . $data['points_earned'])
                     ->line('Great job! ' . $data['student_name'] . ' is becoming a master of their heritage.');
            } else {
                $mail->line('⚠️ No lessons were completed this week.')
                     ->line('Encourage ' . $data['student_name'] . ' to log in this week to stay on track with their language goals!');
            }
        }

        return $mail
            ->action('View Parent Dashboard', url('https://fricalearn.com/parent/dashboard'))
            ->line('Consistency is the key to mastering a new language. Thank you for choosing FricaLearn for your family\'s journey!')
            ->salutation('Warm regards, The FricaLearn Team');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'report_summary' => $this->reportData
        ];
    }
}