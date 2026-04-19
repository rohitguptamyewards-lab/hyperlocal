# Module: Member

## What this module does
Owns customer identity for the entire platform. Phone number is the primary identifier.
`MemberService::findOrCreateByPhone()` is the single entry point — call this whenever a customer phone is submitted.

## What it does NOT do
- Does not manage loyalty balances (→ LoyaltyBridge module)
- Does not send WhatsApp messages (→ CustomerActivation/WhatsAppNotifier)
- Does not handle campaign sends (→ Campaign module)

## Tables it owns
- `members` — primary identity record
- `member_integrations` — links to external provider IDs (eWards, Capillary, POS, etc.)

## Tables it reads but does not write
None.

## Events it fires / listens to
None.

## How to run locally
No special setup needed. Members are created automatically on first claim submission.
To test opt-out: `POST /api/members/opt-out` with `{ "phone": "9191234567890" }`
