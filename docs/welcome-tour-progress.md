# Welcome and demo tour implementation

Specification: approved user plan in this chat, 2026-10-22.

- [x] Public editorial welcome, local photographs and authentic demo screenshots.
- [x] Separate account view and preserved email/deep-link routes.
- [x] Six-step accessible tour, persistence, replay and lifecycle cleanup.
- [x] Unit/regression/API/browser verification and independent review.

Design: graphite #111827, paper #FFFFFF, chalk #F4F6F8, blue #2453E6, orange #F26A2E. Barlow Condensed for left-aligned editorial headlines, IBM Plex Sans for text and controls. One dominant split hero with oversized typography and gym photograph, followed by alternating product stories and a three-step explanation. Existing app screens retain their visual system.
Baseline: clean main at 832b84f; 17 PHP tests / 54 assertions pass. Work happens on codex/welcome-demo-tour.
Assets: original specified Unsplash photograph shows a person beside a machine, not actually holding a phone; use an accurate caption/alt and contextual gym placement rather than claiming phone use.

Verification: 17 PHP tests / 54 assertions, 12 Node UI suites, demo API integration, PHP lint, Composer validation, JS syntax and diff whitespace passed. Browser checks used desktop and 390x844 mobile: product assets/deep links, full tour, back/skip/Escape, reload persistence, manual replay and empty catalogue. Export comparison before/after the full tour proves no data changes. Simulated missing photos and demo-start failure retain readable content and show an actionable error.
Visual fixes: temporary page padding lets the last record form sit above the mobile tour panel; resize reveals the target again; highlighted regions remain undimmed. Auth specimen styles removed. Product screenshot capture uses actual page coordinates to avoid browser clipping artifacts.
Independent review found a delayed-response cancellation race and session-storage fallback under localStorage quota failure. Both were reproduced in failing tests and fixed. Async exercise, routine and history loaders accept explicit cancellation callbacks; normal callers retain their behavior. Real browser delayed-summary test confirms Skip leaves the panel hidden and the selection empty after the response arrives.
Deployment: frontend/static assets only, no production migration or new PHP dependencies. This work remains on the feature branch for review.
