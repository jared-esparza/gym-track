# Demo and flexible training implementation

Approved specification: user plan in this chat, 2026-10-19.

- [x] Flexible schema, migration, API, backup v3 and compatibility.
- [x] Optional filters, quick creation, explicit workout editor and onboarding.
- [x] Session-isolated editable demo, reset, expiry and account transition.
- [x] Integration, UI and regression verification; deployment documentation.

Baseline: 13 PHPUnit tests, 41 assertions passed (PHP execution requires sandbox escalation).
Ruling: work in the existing checkout on codex/demo-flexible-training; branch created with approved escalation because .git is read-only in the sandbox.
Ruling: use session snapshots of an in-memory SQLite database for demo storage. The normal API and import service execute unchanged business rules against PDO; no demo account is created in MySQL. Requires pdo_sqlite on deployment.

Verification: 17 PHPUnit tests / 54 assertions, 10 Node UI suites, API integration (two isolated sessions, empty catalogue, explicit membership, nullable history, JSON v1/v3, CSV, gyms, CSRF, reset/logout), PHP lint, JS syntax, Composer validation and git diff --check passed.
Migration and fresh schema tested on a separately initialized MariaDB 10.4 instance: ownership, selections, history, nullable fields and duplicate protection passed. The real application database was not modified.
Browser checks: desktop and 390x844 mobile, external exercise during Piernas, mark without workout, history, list editing, reload, header logout, expired-cookie form submission and clean registration transition. Corrected the discovered mobile overflow (document width 1512 to 390 at viewport 390).
Independent read-only whole-change reviewer: no actionable findings. Follow-up cleanup clears all transient editors by reloading when exiting demo.
Deployment: apply the incremental SQL once before the new API and enable pdo_sqlite; see DEMO_AND_FLEXIBLE_TRAINING.md.
