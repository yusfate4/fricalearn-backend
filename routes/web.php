<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

// 🚀 THE YUSUF MIGRATION TOOL
Route::get('/run-migration-yusuf', function () {
    try {
        // 1. Force Laravel to refresh its view of the database/migrations folder
        Artisan::call('config:clear');
        Artisan::call('cache:clear');

        // 2. Run the migration with --force (required for Namecheap/Production)
        $status = Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();

        // 3. Return a clear report of what happened
        if (str_contains($output, 'Nothing to migrate')) {
            return "
                <div style='font-family:sans-serif; padding:40px;'>
                    <h1 style='color:#2D5A27;'>System Synced</h1>
                    <p>Laravel says there are no new migration files to run.</p>
                    <p><strong>Note:</strong> If the table is still missing in phpMyAdmin, delete the rows for 'lesson_completions' from the <b>migrations</b> table in phpMyAdmin and run this again.</p>
                    <pre style='background:#f4f4f4; pading:20px;'>$output</pre>
                    <a href='/'>Go Home</a>
                </div>
            ";
        }

        return "
            <div style='font-family:sans-serif; padding:40px;'>
                <h1 style='color:#2D5A27;'>Migration Success!</h1>
                <p>The following tables have been created/updated:</p>
                <pre style='background:#f4f4f4; padding:20px;'>$output</pre>
                <a href='/'>Go Home</a>
            </div>
        ";

    } catch (\Exception $e) {
        return "
            <div style='font-family:sans-serif; padding:40px; color:red;'>
                <h1>Migration Failed</h1>
                <pre>{$e->getMessage()}</pre>
            </div>
        ";
    }
});