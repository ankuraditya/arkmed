<?php

namespace App\Console\Commands;

use App\Models\TemporaryPrescriptionUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeTemporaryPrescriptions extends Command
{
    protected $signature = 'prescriptions:purge-temporary {--dry-run}';

    protected $description = 'Remove expired, unclaimed temporary prescription uploads';

    public function handle(): int
    {
        $query = TemporaryPrescriptionUpload::whereNull('claimed_at')->where('expires_at', '<=', now());
        $count = $query->count();
        if ($this->option('dry-run')) {
            $this->info("{$count} expired upload(s) would be removed.");

            return self::SUCCESS;
        }$query->each(function ($upload) {
            Storage::disk($upload->disk)->delete($upload->path);
            $upload->delete();
        });
        $this->info("Removed {$count} expired upload(s).");

        return self::SUCCESS;
    }
}
