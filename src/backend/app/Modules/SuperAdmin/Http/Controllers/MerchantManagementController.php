<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\User;
use App\Modules\SuperAdmin\Services\SuperAdminService;
use App\Modules\WhatsAppCredit\Services\WhatsAppCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Super admin merchant management — list, detail, credit ledger.
 *
 * Owner module: SuperAdmin
 * Tables read: merchants, merchant_whatsapp_balance, whatsapp_credit_ledger
 */
class MerchantManagementController extends Controller
{
    public function __construct(
        private readonly SuperAdminService    $service,
        private readonly WhatsAppCreditService $credits,
    ) {}

    /**
     * GET /api/super-admin/merchants
     * Paginated list with credit balances and eWards status.
     */
    public function index(Request $request): JsonResponse
    {
        $merchants = $this->service->listMerchants([
            'search' => $request->query('search'),
        ]);

        return response()->json($merchants);
    }

    /**
     * GET /api/super-admin/merchants/{id}
     */
    public function show(int $merchantId): JsonResponse
    {
        $merchant = $this->service->getMerchant($merchantId);

        return response()->json($merchant);
    }

    /**
     * GET /api/super-admin/merchants/{id}/credit-ledger
     */
    public function creditLedger(int $merchantId): JsonResponse
    {
        $ledger = $this->credits->getLedger($merchantId);

        return response()->json($ledger);
    }

    /**
     * GET /api/super-admin/dashboard
     */
    public function dashboard(): JsonResponse
    {
        return response()->json($this->service->platformStats());
    }

    /**
     * PUT /api/super-admin/merchants/{id}
     * Update merchant details.
     */
    public function update(Request $request, int $merchantId): JsonResponse
    {
        $merchant = Merchant::findOrFail($merchantId);

        $data = $request->validate([
            'name'                 => ['sometimes', 'string', 'max:255'],
            'category'             => ['sometimes', 'string', 'max:100'],
            'city'                 => ['sometimes', 'string', 'max:100'],
            'state'                => ['sometimes', 'nullable', 'string', 'max:100'],
            'email'                => ['sometimes', 'email', 'max:255'],
            'phone'                => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_active'            => ['sometimes', 'boolean'],
            'open_to_partnerships' => ['sometimes', 'boolean'],
            'ecosystem_active'     => ['sometimes', 'boolean'],
        ]);

        $merchant->update($data);

        return response()->json(['message' => 'Merchant updated.', 'data' => $merchant->fresh()]);
    }

    /**
     * POST /api/super-admin/merchants
     * Create a new merchant (brand) with a default outlet and admin user.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'category'       => ['required', 'string', 'max:100'],
            'city'           => ['required', 'string', 'max:100'],
            'outlet_name'    => ['required', 'string', 'max:255'],
            'admin_name'     => ['required', 'string', 'max:255'],
            'admin_email'    => ['required', 'email', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:6'],
        ]);

        return DB::transaction(function () use ($data) {
            $merchant = Merchant::create([
                'uuid'                 => (string) Str::uuid(),
                'name'                 => $data['name'],
                'category'             => $data['category'],
                'city'                 => $data['city'],
                'is_active'            => true,
                'open_to_partnerships' => true,
                'ecosystem_active'     => true,
                'registration_status'  => 'approved',
            ]);

            $outlet = $merchant->outlets()->create([
                'uuid'    => (string) Str::uuid(),
                'name'    => $data['outlet_name'],
                'city'    => $data['city'],
            ]);

            $user = User::create([
                'name'        => $data['admin_name'],
                'email'       => $data['admin_email'],
                'password'    => $data['admin_password'],
                'merchant_id' => $merchant->id,
                'outlet_id'   => null,
                'role'        => 1, // admin
            ]);

            return response()->json([
                'id'    => $merchant->id,
                'name'  => $merchant->name,
                'email' => $user->email,
            ], 201);
        });
    }
}
