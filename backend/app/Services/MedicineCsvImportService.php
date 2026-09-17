<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ImportJob;
use App\Models\Medicine;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MedicineCsvImportService
{
    public const HEADERS = ['sku', 'name', 'category', 'brand', 'manufacturer', 'generic_name', 'composition', 'strength', 'dosage_form', 'pack_size', 'mrp', 'selling_price', 'stock_qty', 'stock_status', 'requires_prescription', 'is_active'];

    public function preview(ImportJob $job): void
    {
        [$headers,$rows] = $this->read($job->stored_path);
        $job->errors()->delete();
        if ($headers !== self::HEADERS) {
            $job->errors()->create(['row_number' => 1, 'raw_json' => $headers, 'error_message' => 'CSV headers do not match the required template.']);
            $job->update(['status' => 'invalid', 'total_rows' => count($rows), 'success_rows' => 0, 'failed_rows' => count($rows)]);

            return;
        }$valid = 0;
        foreach ($rows as $index => $row) {
            $data = array_combine($headers, $row);
            $errors = $this->validate($data);
            if ($errors) {
                $job->errors()->create(['row_number' => $index + 2, 'raw_json' => $data, 'error_message' => implode(' ', $errors)]);
            } else {
                $valid++;
            }
        }$job->update(['status' => 'previewed', 'total_rows' => count($rows), 'success_rows' => $valid, 'failed_rows' => count($rows) - $valid]);
    }

    public function commit(ImportJob $job): void
    {
        abort_unless($job->status === 'previewed', 422, 'Import must be previewed before commit.');
        [$headers,$rows] = $this->read($job->stored_path);
        $failed = $job->errors()->pluck('row_number')->all();
        foreach ($rows as $index => $row) {
            if (in_array($index + 2, $failed, true)) {
                continue;
            }$data = array_combine($headers, $row);
            $category = Category::whereRaw('lower(name) = ?', [mb_strtolower(trim($data['category']))])->first();
            $identity = ['slug' => Str::slug(trim($data['name']).'-'.trim($data['strength']).'-'.trim($data['dosage_form']))];
            $values = ['public_id' => (string) Str::ulid(), 'sku' => $data['sku'] ?: null, 'name' => trim($data['name']), 'category_id' => $category->id, 'brand' => $data['brand'] ?: null, 'manufacturer' => $data['manufacturer'] ?: null, 'generic_name' => $data['generic_name'] ?: null, 'composition' => $data['composition'] ?: null, 'strength' => $data['strength'] ?: null, 'dosage_form' => $data['dosage_form'] ?: null, 'pack_size' => $data['pack_size'] ?: null, 'mrp' => $data['mrp'] ?: null, 'selling_price' => $data['selling_price'] ?: null, 'stock_qty' => $data['stock_qty'] ?: null, 'stock_status' => $data['stock_status'], 'requires_prescription' => filter_var($data['requires_prescription'], FILTER_VALIDATE_BOOLEAN), 'is_active' => false];
            Medicine::updateOrCreate($data['sku'] ? ['sku' => $data['sku']] : $identity, $values + $identity);
        }$job->update(['status' => 'completed']);
    }

    private function validate(array $row): array
    {
        $errors = [];
        if (trim($row['name']) === '') {
            $errors[] = 'Name is required.';
        }if (! Category::whereRaw('lower(name) = ?', [mb_strtolower(trim($row['category']))])->exists()) {
            $errors[] = 'Category does not exist.';
        }if (! in_array($row['stock_status'], ['in_stock', 'low_stock', 'out_of_stock', 'on_request'], true)) {
            $errors[] = 'Invalid stock status.';
        }if (! in_array(strtolower($row['requires_prescription']), ['0', '1', 'true', 'false'], true)) {
            $errors[] = 'Invalid prescription flag.';
        }if ($row['sku'] && Medicine::where('sku', $row['sku'])->exists()) {
            $errors[] = 'SKU already exists.';
        }

return $errors;
    }

    private function read(string $path): array
    {
        $handle = fopen(Storage::disk('imports')->path($path), 'r');
        $headers = array_map('trim', fgetcsv($handle) ?: []);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== ''))) {
                $rows[] = $row;
            }
        }fclose($handle);

        return [$headers, $rows];
    }
}
