<?php

namespace App\Modules\Campaign\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Campaign\Constants\CampaignTemplate;
use App\Modules\Campaign\Models\Campaign;
use App\Modules\Campaign\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Campaign CRUD + lifecycle management.
 *
 * Owner module: Campaign
 * Integration points: CampaignService, DispatchCampaignSends job
 */
class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaignService,
    ) {}

    /**
     * List all templates available for use in V1.
     *
     * GET /campaigns/templates
     */
    public function templates(): JsonResponse
    {
        $templates = array_map(fn ($key) => [
            'key'              => $key,
            'label'            => CampaignTemplate::label($key),
            'required_vars'    => CampaignTemplate::requiredVars($key),
            // Whether this template is best suited to a partner segment
            'partner_segment'  => CampaignTemplate::suggestsPartnerSegment($key),
        ], CampaignTemplate::VALID_KEYS);

        return response()->json($templates);
    }

    /**
     * List campaigns for the authenticated merchant.
     *
     * GET /campaigns
     */
    public function index(Request $request): JsonResponse
    {
        $campaigns = Campaign::where('merchant_id', $request->user()->merchant_id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($campaigns);
    }

    /**
     * Create a new campaign in DRAFT status.
     *
     * POST /campaigns
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:200'],
            'template_key'   => ['required', 'string', 'in:' . implode(',', CampaignTemplate::VALID_KEYS)],
            'template_vars'  => ['required', 'array'],
            'target_segment' => ['nullable', 'array'],
        ]);

        $campaign = $this->campaignService->create(
            merchantId:    $request->user()->merchant_id,
            name:          $data['name'],
            templateKey:   $data['template_key'],
            templateVars:  $data['template_vars'],
            targetSegment: $data['target_segment'] ?? [],
            createdBy:     $request->user()->id,
        );

        return response()->json(['uuid' => $campaign->uuid, 'status' => $campaign->status], 201);
    }

    /**
     * Show a campaign and its send stats.
     *
     * GET /campaigns/{uuid}
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $campaign = Campaign::where('uuid', $uuid)
            ->where('merchant_id', $request->user()->merchant_id)
            ->firstOrFail();

        $sendStats = $campaign->sends()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'uuid'           => $campaign->uuid,
            'name'           => $campaign->name,
            'template_key'   => $campaign->template_key,
            'template_label' => $campaign->templateLabel(),
            'status'         => $campaign->status,
            'scheduled_at'   => $campaign->scheduled_at?->toIso8601String(),
            'started_at'     => $campaign->started_at?->toIso8601String(),
            'completed_at'   => $campaign->completed_at?->toIso8601String(),
            'sends'          => [
                'pending'   => $sendStats->get(1, 0),
                'sent'      => $sendStats->get(2, 0),
                'failed'    => $sendStats->get(3, 0),
                'delivered' => $sendStats->get(4, 0),
            ],
        ]);
    }

    /**
     * Schedule a draft campaign.
     *
     * POST /campaigns/{uuid}/schedule
     */
    public function schedule(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $campaign = Campaign::where('uuid', $uuid)
            ->where('merchant_id', $request->user()->merchant_id)
            ->firstOrFail();

        $this->campaignService->schedule($campaign, new \DateTime($data['scheduled_at']));

        return response()->json(['message' => 'Campaign scheduled.', 'scheduled_at' => $data['scheduled_at']]);
    }

    /**
     * Run a draft campaign immediately.
     *
     * POST /campaigns/{uuid}/run
     */
    public function run(Request $request, string $uuid): JsonResponse
    {
        $campaign = Campaign::where('uuid', $uuid)
            ->where('merchant_id', $request->user()->merchant_id)
            ->firstOrFail();

        $this->campaignService->runNow($campaign);

        return response()->json(['message' => 'Campaign queued for immediate dispatch.']);
    }

    /**
     * Cancel a draft or scheduled campaign.
     *
     * POST /campaigns/{uuid}/cancel
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $campaign = Campaign::where('uuid', $uuid)
            ->where('merchant_id', $request->user()->merchant_id)
            ->firstOrFail();

        $this->campaignService->cancel($campaign);

        return response()->json(['message' => 'Campaign cancelled.']);
    }

    /**
     * Preview the estimated audience count for a segment without creating a campaign.
     *
     * POST /campaigns/segment-preview
     *
     * Body: { target_segment: { source: 'own'|'partner', partnership_id?: uuid, last_seen_days?: int } }
     *
     * Returns: { count: int }
     */
    public function segmentPreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_segment'                  => ['required', 'array'],
            'target_segment.source'           => ['sometimes', 'string', 'in:own,partner'],
            'target_segment.partnership_id'   => ['sometimes', 'nullable', 'string', 'size:36'],
            'target_segment.last_seen_days'   => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'target_segment.partner_filter'   => ['sometimes', 'nullable', 'string', 'in:via_partnership,all_customers'],
        ]);

        $count = $this->campaignService->previewSegmentCount(
            merchantId:    $request->user()->merchant_id,
            targetSegment: $data['target_segment'],
        );

        return response()->json(['count' => $count]);
    }
}
