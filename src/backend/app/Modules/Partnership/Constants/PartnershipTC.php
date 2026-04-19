<?php

namespace App\Modules\Partnership\Constants;

/**
 * Standard T&C for all Hyperlocal Partnerships.
 *
 * Purpose: Fixed template that both merchants accept at agreement time.
 * Owner module: Partnership
 * Integration points:
 *   - PartnershipController::showTC()  — serves text to frontend before create/accept
 *   - PartnershipController::store()   — records proposer acceptance (VERSION stamp)
 *   - PartnershipController::accept()  — records acceptor acceptance (VERSION stamp)
 *   - PartnershipController::acceptAndGoLive() — records acceptor acceptance
 *   - PartnershipResource              — returns tc_version in every partnership response
 *
 * DO NOT MODIFY the text() body without bumping VERSION and creating a data migration
 * to re-request acceptance from all active partnerships on the old version.
 *
 * eWards is the technology facilitator only — NOT a party to the commercial agreement.
 */
class PartnershipTC
{
    public const VERSION = '1.0';

    public static function text(): string
    {
        return <<<TC
HYPERLOCAL PARTNERSHIP — STANDARD TERMS & CONDITIONS
Version 1.0

1. NATURE OF AGREEMENT
This Partnership Agreement is entered into directly between the two participating merchants ("Proposer" and "Acceptor"). eWards Technology Pvt. Ltd. ("eWards") provides the technology platform that facilitates this partnership and is NOT a party to the commercial agreement between the merchants.

2. TECHNOLOGY PROVIDER DISCLAIMER
eWards is a technology platform provider only. eWards bears no liability for:
  (a) The commercial terms agreed between merchants or any benefit offered, claimed, or redeemed.
  (b) Any disputes arising from the commercial agreement between merchants.
  (c) Customer satisfaction or dissatisfaction with the benefit offered.
  (d) Any financial loss arising from the partnership terms or their execution.

3. MERCHANT RESPONSIBILITIES
Each merchant is solely responsible for:
  (a) Honouring the benefit terms as agreed on the platform.
  (b) Briefing their staff on the partnership terms before going live.
  (c) Compliance with all applicable laws and regulations, including consumer protection and tax laws.
  (d) Any tax obligations arising from benefits offered or redeemed.

4. CUSTOMER DATA
Customer data accessed through this partnership may only be used to fulfil the specific benefit under this agreement. Cross-selling, retargeting, or sharing customer data with third parties for purposes outside this partnership is strictly prohibited.

5. BRAND USAGE
Each merchant may use the other's trading name and logo solely for promoting this specific partnership to their customers. No other commercial use is permitted without separate written consent.

6. MODIFICATION OF TERMS
Either party may propose changes to the partnership terms through the platform. Changes take effect only upon mutual acceptance. Unilateral modification of agreed terms is not permitted.

7. PAUSING AND TERMINATION
Either party may pause or terminate this partnership at any time through the platform with immediate effect. A minimum of 7 days' notice is recommended for planned terminations to allow customers holding active tokens to complete their redemptions.

8. DISPUTES
Any commercial disputes shall first be addressed through good-faith discussion between the merchants. eWards may facilitate communication but is not an arbitrator, mediator, or legal authority in any dispute.

9. GOVERNING LAW
This agreement is governed by the laws of India. Any legal proceedings shall be subject to the jurisdiction of the courts in the city of the Proposer's registered business address.

By creating or accepting this partnership on the eWards platform, both parties confirm they have read, understood, and agree to these Standard Terms & Conditions.
TC;
    }
}
