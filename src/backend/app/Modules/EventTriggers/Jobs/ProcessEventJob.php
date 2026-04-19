<?php

namespace App\Modules\EventTriggers\Jobs;

use App\Modules\EventTriggers\Models\EventLogEntry;
use App\Modules\EventTriggers\Services\ActionExecutorService;
use App\Modules\EventTriggers\Services\IdentityResolverService;
use App\Modules\EventTriggers\Services\TriggerEngineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processes a logged event: resolve identity → match triggers → execute actions.
 * Runs on the 'events' queue.
 */
class ProcessEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(private readonly int $eventLogId)
    {
        $this->onQueue('events');
    }

    public function handle(
        IdentityResolverService $identity,
        TriggerEngineService    $triggerEngine,
        ActionExecutorService   $actions,
    ): void {
        $entry = EventLogEntry::find($this->eventLogId);
        if (!$entry || $entry->processing_status === 'completed') return;

        $entry->update(['processing_status' => 'processing']);

        try {
            $normalized = $entry->normalized_payload ?? [];

            // 1. Resolve customer identity
            $member = $identity->resolve($normalized['customer_ref'] ?? []);
            $entry->update(['member_id' => $member?->id]);

            // 2. Match triggers
            $matchingTriggers = $triggerEngine->match(
                $entry->merchant_id,
                $entry->event_type,
                $normalized,
            );

            if ($matchingTriggers->isEmpty()) {
                $entry->update([
                    'processing_status' => 'completed',
                    'action_outcome'    => ['triggers_matched' => 0, 'message' => 'No matching triggers.'],
                    'processed_at'      => now(),
                ]);
                return;
            }

            // 3. Execute actions for each matching trigger
            $outcomes = [];
            foreach ($matchingTriggers as $trigger) {
                $outcomes[] = $actions->execute($trigger, $normalized, $member);
            }

            $entry->update([
                'processing_status' => 'completed',
                'action_outcome'    => [
                    'triggers_matched' => $matchingTriggers->count(),
                    'actions'          => $outcomes,
                ],
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ProcessEventJob failed', [
                'event_log_id' => $this->eventLogId,
                'error'        => $e->getMessage(),
            ]);

            $entry->update([
                'processing_status' => 'failed',
                'error_reason'      => $e->getMessage(),
                'processed_at'      => now(),
            ]);

            throw $e; // Let the queue retry
        }
    }
}
