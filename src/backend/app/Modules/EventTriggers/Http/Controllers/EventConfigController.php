<?php

namespace App\Modules\EventTriggers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\EventTriggers\Constants\EventSourceType;
use App\Modules\EventTriggers\Constants\EventType;
use App\Modules\EventTriggers\Constants\TriggerActionType;
use App\Modules\EventTriggers\Models\EventLogEntry;
use App\Modules\EventTriggers\Models\EventSource;
use App\Modules\EventTriggers\Models\EventTrigger;
use App\Modules\EventTriggers\Services\EventIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authenticated CRUD for event sources, triggers, and event logs.
 */
class EventConfigController extends Controller
{
    public function __construct(private readonly EventIngestionService $ingestion) {}

    // ── Constants for frontend dropdowns ───────────────────

    public function constants(): JsonResponse
    {
        return response()->json([
            'event_types'   => EventType::LABELS,
            'source_types'  => EventSourceType::LABELS,
            'action_types'  => TriggerActionType::LABELS,
        ]);
    }

    // ── Event Sources ─────────────────────────────────────

    public function listSources(Request $request): JsonResponse
    {
        $sources = EventSource::where('merchant_id', $request->user()->merchant_id)
            ->withCount('triggers')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $sources]);
    }

    public function createSource(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'source_type' => ['required', 'string', 'in:' . implode(',', EventSourceType::VALID)],
            'config'      => ['nullable', 'array'],
        ]);

        $source = EventSource::create([
            'merchant_id' => $request->user()->merchant_id,
            'name'        => $data['name'],
            'source_type' => $data['source_type'],
            'config'      => $data['config'] ?? null,
            'created_by'  => $request->user()->id,
        ]);

        return response()->json([
            'data' => $source,
            'merchant_key' => $source->merchant_key,
            'trigger_url'  => url("/api/events/pixel/{$source->merchant_key}"),
            'api_url'      => url('/api/events/trigger'),
        ], 201);
    }

    public function updateSource(Request $request, string $uuid): JsonResponse
    {
        $source = EventSource::where('uuid', $uuid)
            ->where('merchant_id', $request->user()->merchant_id)
            ->firstOrFail();

        $data = $request->validate([
            'name'   => ['sometimes', 'string', 'max:100'],
            'config' => ['sometimes', 'nullable', 'array'],
        ]);

        $source->update($data);
        return response()->json(['data' => $source]);
    }

    public function toggleSource(Request $request, string $uuid): JsonResponse
    {
        $source = EventSource::where('uuid', $uuid)
            ->where('merchant_id', $request->user()->merchant_id)
            ->firstOrFail();

        $source->update(['status' => $source->status === 1 ? 2 : 1]);
        return response()->json(['data' => $source]);
    }

    public function deleteSource(Request $request, string $uuid): JsonResponse
    {
        $source = EventSource::where('uuid', $uuid)
            ->where('merchant_id', $request->user()->merchant_id)
            ->firstOrFail();

        $source->update(['status' => 3]); // disconnected
        return response()->json(['message' => 'Source disconnected.']);
    }

    // ── Event Triggers ────────────────────────────────────

    public function listTriggers(Request $request): JsonResponse
    {
        $triggers = EventTrigger::where('merchant_id', $request->user()->merchant_id)
            ->with('source:id,name,source_type')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $triggers]);
    }

    public function createTrigger(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:200'],
            'event_source_id'    => ['nullable', 'integer'],
            'event_type'         => ['required', 'string', 'in:' . implode(',', EventType::VALID)],
            'condition_json'     => ['nullable', 'array'],
            'action_type'        => ['required', 'string', 'in:' . implode(',', TriggerActionType::VALID)],
            'action_config_json' => ['nullable', 'array'],
            'partnership_id'     => ['nullable', 'integer'],
            'offer_id'           => ['nullable', 'integer'],
        ]);

        $trigger = EventTrigger::create(array_merge($data, [
            'merchant_id' => $request->user()->merchant_id,
            'created_by'  => $request->user()->id,
            'updated_by'  => $request->user()->id,
        ]));

        return response()->json(['data' => $trigger], 201);
    }

    public function updateTrigger(Request $request, string $uuid): JsonResponse
    {
        $trigger = EventTrigger::where('uuid', $uuid)
            ->where('merchant_id', $request->user()->merchant_id)
            ->firstOrFail();

        $data = $request->validate([
            'name'               => ['sometimes', 'string', 'max:200'],
            'event_type'         => ['sometimes', 'string', 'in:' . implode(',', EventType::VALID)],
            'condition_json'     => ['sometimes', 'nullable', 'array'],
            'action_type'        => ['sometimes', 'string', 'in:' . implode(',', TriggerActionType::VALID)],
            'action_config_json' => ['sometimes', 'nullable', 'array'],
            'partnership_id'     => ['sometimes', 'nullable', 'integer'],
            'offer_id'           => ['sometimes', 'nullable', 'integer'],
        ]);

        $trigger->update(array_merge($data, ['updated_by' => $request->user()->id]));
        return response()->json(['data' => $trigger]);
    }

    public function toggleTrigger(Request $request, string $uuid): JsonResponse
    {
        $trigger = EventTrigger::where('uuid', $uuid)
            ->where('merchant_id', $request->user()->merchant_id)
            ->firstOrFail();

        $trigger->update(['is_active' => !$trigger->is_active, 'updated_by' => $request->user()->id]);
        return response()->json(['data' => $trigger]);
    }

    public function deleteTrigger(Request $request, string $uuid): JsonResponse
    {
        EventTrigger::where('uuid', $uuid)
            ->where('merchant_id', $request->user()->merchant_id)
            ->delete();

        return response()->json(['message' => 'Trigger deleted.']);
    }

    // ── Test Event ────────────────────────────────────────

    public function testEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'merchant_key' => ['required', 'string'],
            'event_type'   => ['required', 'string'],
            'amount'       => ['nullable', 'numeric'],
            'phone'        => ['nullable', 'string'],
        ]);

        $payload = [
            'event_type' => $data['event_type'],
            'amount'     => $data['amount'] ?? 0,
            'phone'      => $data['phone'] ?? null,
            'order_id'   => 'TEST-' . uniqid(),
        ];

        $result = $this->ingestion->ingest($data['merchant_key'], $payload, 'website');
        return response()->json($result);
    }

    // ── Event Log ─────────────────────────────────────────

    public function eventLog(Request $request): JsonResponse
    {
        $logs = EventLogEntry::where('merchant_id', $request->user()->merchant_id)
            ->orderByDesc('received_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $logs]);
    }

    public function eventLogDetail(Request $request, int $id): JsonResponse
    {
        $entry = EventLogEntry::where('id', $id)
            ->where('merchant_id', $request->user()->merchant_id)
            ->firstOrFail();

        return response()->json(['data' => $entry]);
    }
}
