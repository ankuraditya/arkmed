<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProductionCheck extends Command
{
    protected $signature = 'app:production-check {--allow-development : Do not require production environment and HTTPS}';

    protected $description = 'Validate ARK med production configuration and required services';

    public function handle(): int
    {
        $errors = [];
        $warnings = [];
        $allowDevelopment = (bool) $this->option('allow-development');
        $check = function (bool $condition, string $message) use (&$errors): void {
            if (! $condition) {
                $errors[] = $message;
            }
        };

        if (! $allowDevelopment) {
            $check(app()->environment('production'), 'APP_ENV must be production.');
            $check(config('app.debug') === false, 'APP_DEBUG must be false.');
            $check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL must use HTTPS.');
            $check(str_starts_with((string) config('cors.allowed_origins.0'), 'https://'), 'FRONTEND_URL must use HTTPS.');
        }
        $check(filled(config('app.key')), 'APP_KEY must be configured.');
        $check(filled(config('services.whatsapp.number')), 'WHATSAPP_NUMBER must be configured.');
        $check(config('session.driver') !== 'array', 'SESSION_DRIVER must use persistent storage.');
        $check(config('cache.default') !== 'array', 'CACHE_STORE must use persistent storage.');
        $check(config('queue.default') !== 'sync', 'QUEUE_CONNECTION must not be sync in production.');
        if (config('mail.default') === 'log') {
            $warnings[] = 'MAIL_MAILER is log; customer email delivery is disabled.';
        }

        try {
            DB::select('select 1');
            $this->components->info('Database connection is available.');
        } catch (\Throwable $exception) {
            $errors[] = 'Database connection failed: '.$exception->getMessage();
        }

        foreach (['prescriptions', 'imports'] as $directory) {
            $path = storage_path('app/'.$directory);
            $check(is_dir($path) && is_writable($path), "Private {$directory} storage must exist and be writable.");
        }
        try {
            if (! User::where('role', 'super_admin')->where('is_active', true)->exists()) {
                $warnings[] = 'No active super administrator exists.';
            }
        } catch (\Throwable) {
            $errors[] = 'Could not verify the administrator account.';
        }

        foreach ($warnings as $warning) {
            $this->components->warn($warning);
        }
        foreach ($errors as $error) {
            $this->components->error($error);
        }
        if ($errors !== []) {
            $this->newLine();
            $this->components->error(count($errors).' production check(s) failed.');

            return self::FAILURE;
        }

        $this->components->info('ARK med production checks passed'.($warnings ? ' with warnings.' : '.'));

        return self::SUCCESS;
    }
}
