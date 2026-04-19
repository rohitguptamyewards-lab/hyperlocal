# Hyperlocal Partnership Network — Source Root

## Stack
| Layer | Technology |
|-------|-----------|
| Backend API | Laravel 10 (PHP 8.2) |
| Frontend | Vue 3 + TypeScript + Vite |
| Database | MySQL 8 |
| Queue | Laravel Queue (database driver locally; Redis on eWards) |
| Storage | Local filesystem locally; S3 on eWards |

## Folder Structure
```
src/
├── backend/                  Laravel 10 API
│   ├── app/
│   │   └── Modules/          Domain-grouped modules (NOT by file type)
│   │       ├── Partnership/
│   │       ├── Discovery/
│   │       ├── RulesEngine/
│   │       ├── CustomerActivation/
│   │       ├── Execution/
│   │       ├── Ledger/
│   │       ├── Analytics/
│   │       ├── Enablement/
│   │       └── Migration/
│   └── database/
│       ├── migrations/
│       └── seeders/
└── frontend/                 Vue 3 + TypeScript
    └── src/
        └── modules/
            ├── partnership/
            ├── discovery/
            ├── cashier/
            ├── analytics/
            └── admin/
```

## Build Order
Follow `docs/DATA_MODEL.md` migration order and `FLOWCHART.md` module dependency map.
Do NOT start a module until its blocking OPEN_DECISIONS are LOCKED.

## Rules
1. Every module has a README — read it before touching that module
2. Every query includes `merchant_id` — no exceptions
3. Debugging always follows FLOWCHART.md — never patch a single layer
4. SESSION_NOTES.md updated at end of every session
5. FLOWCHART.md updated whenever a new connection is added
