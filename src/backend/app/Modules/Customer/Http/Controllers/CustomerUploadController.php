<?php

namespace App\Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Models\CustomerUpload;
use App\Modules\Customer\Models\MerchantCustomer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer list + CSV upload management for merchants.
 *
 * Owner module: Customer
 * Integration points: merchant_customers, customer_uploads tables
 */
class CustomerUploadController extends Controller
{
    /**
     * List merchant's customers with pagination.
     *
     * GET /api/customers
     */
    public function index(Request $request): JsonResponse
    {
        $merchantId = $request->user()->merchant_id;

        $query = MerchantCustomer::where('merchant_id', $merchantId);

        // Optional search by name or phone
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($customers);
    }

    /**
     * Accept a CSV file, parse it, insert valid rows, skip duplicates, return summary.
     *
     * POST /api/customers/upload
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv', 'max:5120'],
        ]);

        $merchantId = $request->user()->merchant_id;
        $file = $request->file('file');

        // Create upload record
        $upload = CustomerUpload::create([
            'merchant_id' => $merchantId,
            'file_name'   => $file->getClientOriginalName(),
            'status'      => 'processing',
        ]);

        $imported = 0;
        $failed   = 0;
        $errors   = [];
        $totalRows = 0;

        try {
            $handle = fopen($file->getRealPath(), 'r');
            if ($handle === false) {
                throw new \RuntimeException('Could not read uploaded file.');
            }

            // Read header row
            $header = fgetcsv($handle);
            if (!$header) {
                throw new \RuntimeException('CSV file is empty or has no header row.');
            }

            // Normalize header names to lowercase/trimmed
            $header = array_map(fn ($h) => strtolower(trim($h)), $header);

            // Map expected columns
            $nameCol        = $this->findColumn($header, ['name', 'customer_name', 'customer name']);
            $phoneCol       = $this->findColumn($header, ['phone', 'mobile', 'phone_number', 'phone number', 'contact']);
            $countryCodeCol = $this->findColumn($header, ['country_code', 'country code', 'code', 'isd', 'isd_code']);

            if ($phoneCol === null) {
                fclose($handle);
                $upload->update([
                    'status'      => 'failed',
                    'errors_json' => json_encode(['Phone column not found in CSV header. Expected: phone or mobile.']),
                ]);
                return response()->json([
                    'message' => 'Phone column not found in CSV header.',
                    'upload_id' => $upload->id,
                ], 422);
            }

            // Process rows
            $rowNum = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                $totalRows++;

                $phone       = trim($row[$phoneCol] ?? '');
                $name        = $nameCol !== null ? trim($row[$nameCol] ?? '') : null;
                $countryCode = $countryCodeCol !== null ? trim($row[$countryCodeCol] ?? '') : '';

                // Normalize phone — strip spaces, dashes, brackets, leading zeros
                $phone       = preg_replace('/[\s\-\(\)]/', '', $phone);
                $countryCode = preg_replace('/[\s\+\-]/', '', $countryCode);

                // Combine country code + phone if country code provided and phone not already prefixed
                if ($countryCode !== '' && !str_starts_with($phone, '+')) {
                    $phone = '+' . $countryCode . ltrim($phone, '0');
                }

                // Basic validation: phone must be 10–15 digits (with optional + prefix)
                if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
                    $failed++;
                    $errors[] = "Row {$rowNum}: Invalid phone '{$phone}'.";
                    continue;
                }

                // Upsert — skip duplicates
                try {
                    MerchantCustomer::firstOrCreate(
                        ['merchant_id' => $merchantId, 'phone' => $phone],
                        [
                            'name'   => $name ?: null,
                            'source' => 'upload',
                        ]
                    );
                    $imported++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = "Row {$rowNum}: {$e->getMessage()}";
                }
            }

            fclose($handle);

            $upload->update([
                'total_rows'     => $totalRows,
                'imported_count' => $imported,
                'failed_count'   => $failed,
                'status'         => 'completed',
                'errors_json'    => count($errors) ? json_encode(array_slice($errors, 0, 50)) : null,
            ]);
        } catch (\Throwable $e) {
            $upload->update([
                'total_rows'     => $totalRows,
                'imported_count' => $imported,
                'failed_count'   => $failed,
                'status'         => 'failed',
                'errors_json'    => json_encode([$e->getMessage()]),
            ]);

            return response()->json([
                'message'   => 'Upload processing failed: ' . $e->getMessage(),
                'upload_id' => $upload->id,
            ], 500);
        }

        return response()->json([
            'message'        => 'Upload completed.',
            'upload_id'      => $upload->id,
            'total_rows'     => $totalRows,
            'imported_count' => $imported,
            'failed_count'   => $failed,
            'errors'         => array_slice($errors, 0, 10),
        ]);
    }

    /**
     * Return aggregate customer stats for the merchant.
     *
     * GET /api/customers/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $merchantId = $request->user()->merchant_id;

        $total       = MerchantCustomer::where('merchant_id', $merchantId)->count();
        $fromUploads = MerchantCustomer::where('merchant_id', $merchantId)->where('source', 'upload')->count();
        $fromTokens  = MerchantCustomer::where('merchant_id', $merchantId)->where('source', 'token_claim')->count();

        return response()->json([
            'total'        => $total,
            'from_uploads' => $fromUploads,
            'from_tokens'  => $fromTokens,
        ]);
    }

    /**
     * List upload history for the merchant.
     *
     * GET /api/customers/uploads
     */
    public function uploadHistory(Request $request): JsonResponse
    {
        $uploads = CustomerUpload::where('merchant_id', $request->user()->merchant_id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($uploads);
    }

    /**
     * Find a column index by trying several possible header names.
     */
    private function findColumn(array $header, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $idx = array_search($candidate, $header, true);
            if ($idx !== false) {
                return $idx;
            }
        }
        return null;
    }
}
