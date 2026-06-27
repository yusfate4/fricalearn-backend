<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class OakPrefetchNext extends Command
{
    protected $signature   = 'oak:prefetch-next';
    protected $description = 'Auto-fetch next batch of Oak lessons — runs via scheduler';

    public function handle(): int
    {
        // Find the UK subject with the most unfetched lessons
        $subject = DB::table('external_subjects as s')
            ->join('external_topics as t', 't.subject_id', '=', 's.id')
            ->join('external_lessons as l', 'l.topic_id', '=', 't.id')
            ->where('s.source', 'Oak National Academy')
            ->where('s.curriculum_region', 'uk')
            ->whereNull('l.description')
            ->whereNotNull('l.external_id')
            ->select('s.id', 's.name', DB::raw('COUNT(l.id) as remaining'))
            ->groupBy('s.id', 's.name')
            ->orderByDesc('remaining')
            ->first();

        if (!$subject) {
            $this->info('✅ All Oak lessons have been fetched!');

            // Remove self from schedule by logging completion
            \Log::info('oak:prefetch-next: All lessons fetched. No more work to do.');
            return self::SUCCESS;
        }

        $this->info("Fetching 25 lessons from: {$subject->name} ({$subject->remaining} remaining)");

        Artisan::call('oak:prefetch', [
            '--limit'   => 25,
            '--subject' => $subject->id,
        ]);

        $this->info(Artisan::output());

        return self::SUCCESS;
    }
}
