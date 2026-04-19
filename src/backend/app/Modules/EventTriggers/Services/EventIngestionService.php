<?php

namespace App\Modules\EventTriggers\Services;

use App\Modules\EventTriggers\Jobs\ProcessEventJob;
use App\Modules\EventTriggers\Models\EventLogEntry;
use App\Modules\EventTriggers\Models\EventSource;
use Illuminate\Support\Facades\Log;

/**
 * Receives raw events, normalizes, deduplicates, logs, and queues for processing.
 * This is the single entry point for ALL event sources.
 */
class EventIngestionService
{
    /**
     * Ingest a raw event from any source.
     *
     * @param  string     $merchantKey  The public merchant key from the URL/header
     * @param  array      $rawPayload   Raw event data
     * @param  string     $sourceType   'website' | 'api' | 'shopify' | etc.
     * @return array      {event_id, status, message}
     */
    public function ingest(string $merchantKey, array $rawPayload, string $sourceType = 'website'): array
    {
        // 1. Resolve event source
        $source = EventSource::where('merchant_key', $merchantKey)
            ->where('status', 1)
            ->first();

        if (!$source) {
            return ['status' => 'rejected', 'message' => 'Invalid or inactive merchant key.'];
        }

        // 2. Normalize payload
        $normalized = $this->normalize($rawPayload, $sourceType);
        $eventType  = $normalized['event_type'] ?? 'transaction_completed';

        // 3. Build idempotency key
        $idempKey = implode(':', [
            $source->merchant_id,
            $eventType,
            $normalized['order_id'] ?? $rawPayload['order_id'] ?? uniqid('evt_'),
        ]);

        // 4. Deduplicate
        $existing = EventLogEntry::where('idempotency_key', $idempKey)->first();
        if ($existing) {
            return ['status' => 'duplicate', 'message' => 'Event already processed.', 'event_id' => $existing->id];
        }

        // 5. Log the event
        $logEntry = EventLogEntry::create([
            'event_source_id'    => $source->id,
            'merchant_id'        => $source->merchant_id,
            'idempotency_key'    => $idempKey,
            'event_type'         => $eventType,
            'raw_payload'        => $rawPayload,
            'normalized_payload' => $normalized,
            'processing_status'  => $source->test_mode ? 'test' : 'received',
            'received_at'        => now(),
        ]);

        // 6. Queue for processing (unless test mode)
        if (!$source->test_mode) {
            ProcessEventJob::dispatch($logEntry->id);
        } else {
            Log::info('EventIngestion: test mode — event logged but not processed', [
                'event_id'    => $logEntry->id,
                'merchant_id' => $source->merchant_id,
            ]);
        }

        return [
            'status'   => $source->test_mode ? 'test_logged' : 'accepted',
            'event_id' => $logEntry->id,
            'message'  => $source->test_mode ? 'Test event logged (no actions executed).' : 'Event accepted for processing.',
        ];
    }

    /**
     * Normalize raw payload into the internal event format.
     */
    private function normalize(array $raw, string $sourceType): array
    {
        // Handle Shopify order/create format
        if ($sourceType === 'shopify') {
            return $this->normalizeShopify($raw);
        }

        // Handle WooCommerce order format
        if ($sourceType === 'woocommerce') {
            return $this->normalizeWooCommerce($raw);
        }

        // Generic / website / API format — already close to our model
        return [
            'event_type'   => $raw['event'] ?? $raw['event_type'] ?? 'transaction_completed',
            'amount'       => (float) ($raw['amount'] ?? $raw['total'] ?? 0),
            'currency'     => $raw['currency'] ?? 'INR',
            'order_id'     => $raw['order_id'] ?? $raw['reference'] ?? null,
            'category'     => $raw['category'] ?? null,
            'channel'      => $sourceType,
            'source'       => $sourceType,
            'customer_ref' => [
                'phone'       => $raw['phone'] ?? $raw['customer_phone'] ?? null,
                'email'       => $raw['email'] ?? $raw['customer_email'] ?? null,
                'external_id' => $raw['customer_id'] ?? $raw['external_id'] ?? null,
            ],
            'metadata'     => $raw['metadata'] ?? [],
        ];
    }

    private function normalizeShopify(array $raw): array
    {
        return [
            'event_type'   => 'transaction_completed',
            'amount'       => (float) ($raw['total_price'] ?? 0),
            'currency'     => $raw['currency'] ?? 'INR',
            'order_id'     => (string) ($raw['id'] ?? $raw['order_number'] ?? ''),
            'category'     => null,
            'channel'      => 'shopify',
            'source'       => 'shopify',
            'customer_ref' => [
                'phone'       => $raw['customer']['phone'] ?? $raw['billing_address']['phone'] ?? null,
                'email'       => $raw['customer']['email'] ?? $raw['email'] ?? null,
                'external_id' => (string) ($raw['customer']['id'] ?? ''),
            ],
            'metadata' => [
                'order_name'     => $raw['name'] ?? null,
                'line_item_count' => count($raw['line_items'] ?? []),
                'first_purchase' => ($raw['customer']['orders_count'] ?? 1) <= 1,
            ],
        ];
    }

    private function normalizeWooCommerce(array $raw): array
    {
        return [
            'event_type'   => 'transaction_completed',
            'amount'       => (float) ($raw['total'] ?? 0),
            'currency'     => $raw['currency'] ?? 'INR',
            'order_id'     => (string) ($raw['id'] ?? $raw['number'] ?? ''),
            'category'     => null,
            'channel'      => 'woocommerce',
            'source'       => 'woocommerce',
            'customer_ref' => [
                'phone'       => $raw['billing']['phone'] ?? null,
                'email'       => $raw['billing']['email'] ?? null,
                'external_id' => (string) ($raw['customer_id'] ?? ''),
            ],
            'metadata' => [
                'order_key'      => $raw['order_key'] ?? null,
                'line_item_count' => count($raw['line_items'] ?? []),
            ],
        ];
    }
}
