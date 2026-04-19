<?php

namespace App\Modules\EventTriggers\Services;

use App\Modules\EventTriggers\Models\EventTrigger;
use Illuminate\Support\Collection;

/**
 * Matches incoming events to configured triggers and evaluates conditions.
 * Returns the list of actions to execute.
 */
class TriggerEngineService
{
    /**
     * Find all active triggers for this merchant + event type, then evaluate conditions.
     *
     * @return Collection<EventTrigger> Matching triggers whose conditions pass
     */
    public function match(int $merchantId, string $eventType, array $normalizedPayload): Collection
    {
        $triggers = EventTrigger::where('merchant_id', $merchantId)
            ->where('event_type', $eventType)
            ->where('is_active', true)
            ->get();

        return $triggers->filter(fn ($t) => $this->evaluateConditions($t, $normalizedPayload));
    }

    private function evaluateConditions(EventTrigger $trigger, array $payload): bool
    {
        $conditions = $trigger->condition_json ?? [];

        if (empty($conditions)) {
            return true; // No conditions = always match
        }

        // Min amount check
        if (isset($conditions['min_amount'])) {
            $amount = $payload['amount'] ?? 0;
            if ($amount < $conditions['min_amount']) return false;
        }

        // Category check
        if (isset($conditions['category'])) {
            $category = $payload['category'] ?? null;
            if ($category !== $conditions['category']) return false;
        }

        // First purchase only
        if (!empty($conditions['first_purchase'])) {
            $isFirst = $payload['metadata']['first_purchase'] ?? false;
            if (!$isFirst) return false;
        }

        return true;
    }
}
