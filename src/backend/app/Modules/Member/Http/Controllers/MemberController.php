<?php

namespace App\Modules\Member\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Member\Models\Member;
use App\Modules\Member\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Member lookup and management endpoints.
 * Used by cashiers to look up a customer by phone before issuing a claim.
 *
 * Owner module: Member
 * Integration points: MemberService
 */
class MemberController extends Controller
{
    public function __construct(
        private readonly MemberService $memberService,
    ) {}

    /**
     * Look up a member by phone number.
     * Returns member details including opt-in status and last seen.
     * Creates the member if not found (first-contact registration).
     *
     * POST /members/lookup
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'min:7', 'max:20'],
            'name'  => ['nullable', 'string', 'max:150'],
        ]);

        $phone  = $this->memberService->normalise($request->string('phone')->toString());
        $member = $this->memberService->findOrCreateByPhone($phone, $request->string('name')->toString() ?: null);

        return response()->json([
            'member_id'       => $member->id,
            'phone'           => $member->phone,
            'name'            => $member->name,
            'whatsapp_opt_in' => $member->whatsapp_opt_in,
            'last_seen_at'    => $member->last_seen_at?->toIso8601String(),
        ]);
    }

    /**
     * Opt a member out of WhatsApp messages.
     *
     * POST /members/opt-out
     */
    public function optOut(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $phone = $this->memberService->normalise($request->string('phone')->toString());
        $this->memberService->optOut($phone);

        return response()->json(['message' => 'Opted out successfully.']);
    }
}
