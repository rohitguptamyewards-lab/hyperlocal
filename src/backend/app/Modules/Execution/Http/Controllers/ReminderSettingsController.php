<?php

namespace App\Modules\Execution\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ReminderSettingsController — Token expiry reminder configuration (GAP 4).
 *
 * Purpose: Allow merchants to enable/disable expiry reminders and customise
 *          the message template and lead-time window.
 * Owner module: Execution
 * Tables: token_reminder_settings (read + upsert)
 * Auth: auth:sanctum — merchant-scoped
 */
class ReminderSettingsController extends Controller
{
    /**
     * GET /api/reminders/settings
     *
     * Returns the current merchant's reminder settings.
     * If no row exists yet, returns the system defaults so the frontend
     * can render the form in a consistent state.
     */
    public function show(Request $request): JsonResponse
    {
        $merchantId = $request->user()->merchant_id;

        $settings = DB::table('token_reminder_settings')
            ->where('merchant_id', $merchantId)
            ->first();

        if ($settings) {
            return response()->json([
                'reminder_enabled'    => (bool) $settings->reminder_enabled,
                'remind_hours_before' => (int)  $settings->remind_hours_before,
                'message_template'    => $settings->message_template,
            ]);
        }

        // Return defaults — no row in DB yet
        return response()->json([
            'reminder_enabled'    => false,
            'remind_hours_before' => 12,
            'message_template'    => null,
        ]);
    }

    /**
     * PUT /api/reminders/settings
     *
     * Create or update the merchant's reminder settings.
     * Uses an upsert so the first PUT call initialises the row.
     *
     * Accepted body fields:
     *   reminder_enabled    boolean
     *   remind_hours_before integer  (must be one of 3, 6, 12, 24)
     *   message_template    string|null
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reminder_enabled'    => ['required', 'boolean'],
            'remind_hours_before' => ['required', 'integer', 'in:3,6,12,24'],
            'message_template'    => ['nullable', 'string', 'max:1000'],
        ]);

        $merchantId = $request->user()->merchant_id;
        $now        = now();

        DB::table('token_reminder_settings')->upsert(
            [
                'merchant_id'         => $merchantId,
                'reminder_enabled'    => $data['reminder_enabled'],
                'remind_hours_before' => $data['remind_hours_before'],
                'message_template'    => $data['message_template'] ?? null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ],
            ['merchant_id'],                              // unique key for conflict detection
            ['reminder_enabled', 'remind_hours_before', 'message_template', 'updated_at'],
        );

        return response()->json([
            'reminder_enabled'    => (bool)  $data['reminder_enabled'],
            'remind_hours_before' => (int)   $data['remind_hours_before'],
            'message_template'    => $data['message_template'] ?? null,
        ]);
    }
}
