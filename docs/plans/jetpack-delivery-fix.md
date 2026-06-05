# Jetpack Delivery Fix — Well Web Notify

**Trigger:** On one production site, Jetpack form submissions stopped reaching Google Chat after the WP 7.0 / 1.0.4 update window. The "Test" button still works on every channel. The Notify Log shows only `Test` success rows for `google-chat`; **no log row at all is produced when a visitor submits a real Jetpack form** on that site. Other channels (Slack/Discord/Telegram) and other form integrations (CF7/WPForms/Gravity/Ninja/Woo) are unaffected.

**Current state:** v1.0.4. `includes/forms/class-jetpack.php` listens only on `grunion_pre_message_sent`. That action is the legacy `Grunion_Contact_Form::process_submission()` hook. The modern Jetpack Forms (block / namespaced `Automattic\Jetpack\Forms\ContactForm\…`) does not reliably fire it on every site — and "no log row at all" is what that gap looks like in our log.

**Target release:** v1.0.5.

## Root cause (final, confirmed via captured hook trace)

Diagnostic output from the failing site (Exelor, Jetpack `15.9-a7`), trace of a real Jetpack Forms-block submission:

- Local `feedback` posts ARE being created (the captured trace shows `wp_insert_post → publish_feedback → save_post_feedback` at ~43,000 ms with post #2723).
- **`_feedback_extra_fields` meta IS written, but as an empty array (`array(0)`).** Jetpack 15.x no longer stores the field payload in meta. So the meta-listener strategy from the second sideload build was directionally correct (catch the meta write) but useless on this Jetpack version (the meta key is empty).
- **`grunion_pre_message_sent` does not fire** on this Jetpack version — it has been removed from the modern Forms-block path. That's why every previous build failed: we were waiting for an action that no longer runs.
- **`grunion_after_message_sent` does fire**, with the canonical signature `($post_id, $to, $subject, $message, $headers, $all_values, $extra_values)`. The 6th arg (`$all_values`) carries the form field values keyed by label (`array(7)` in the captured submission). This is the only signal in Jetpack 15.x that arrives at a hook with the field data populated.
- The channel pipeline is healthy end-to-end (the diagnostic self-test reached both Telegram and Google Chat with synthetic data).

The Exelor site also has a custom `exelor_grunion_n8n_*` chain firing right after `grunion_after_message_sent`, which independently proves that this hook is the live entry point on this Jetpack version for site-level integrations.

## Phase 1 — Catch every Jetpack submission path (the fix)

- **Goal:** Whichever Jetpack submission flow runs — classic shortcode, new Forms block, REST, AJAX — our handler is called exactly once.
- **Allowed files:** `includes/forms/class-jetpack.php` only.
- **Forbidden files (regression guardrail):** every other file under `includes/`, `well-web-notify.php`, all CSS/JS, `readme.txt`. No channel changes, no form-manager changes, no signature changes to `handle_submission()`.
- **Approach (final, after the hook trace pinpointed the surviving entry point):**
  - **Primary hook: `grunion_after_message_sent` (priority 10, 7 args).** This is the only hook that fires on Jetpack 15.x Forms-block submissions AND arrives with the field payload as a callback argument (`$all_values`). All previous builds missed this because they listened on the dropped `grunion_pre_message_sent` and on meta keys that 15.x now writes empty.
  - Keep `grunion_pre_message_sent` for older Jetpack (≤ 14.x).
  - Keep the `added_post_meta` / `updated_post_meta` listener on `_feedback_extra_fields` / `_feedback_all_fields` as a defensive fallback — on the Jetpack versions where the meta IS populated, that listener fires earlier than the after-message-sent action, and dedupe collapses the second arrival.
  - Keep `transition_post_status` as a last-resort fallback for importers / scripted creation.
  - Resolve the form name via `_feedback_source_post_id` first (modern), `_feedback_parent_url` next (legacy), feedback post title last.
  - Dedupe inside the request with a static set keyed on the feedback / form post ID so no listener can double-send.
  - Skip Akismet-marked spam and trashed feedback posts.
- **Behaviour change:** Modern Jetpack Forms submissions produce notifications again. Classic-shortcode submissions continue to work as before, with no duplicates.
- **Verification:**
  - On the failing site, submit the Jetpack form on the affected page. Expect: one Notify Log row per active channel; Google Chat receives the message.
  - Submit a known spam test (or mark as spam in the dashboard simulation) — expect: no Notify Log row.
  - On a site running classic-shortcode Jetpack forms, submit and confirm: still exactly one row per channel (no doubles).

## Phase 2 — (deferred) Diagnostic visibility

Not shipped in 1.0.5 unless Phase 1 doesn't resolve the report. Plan kept for reference:
- Opt-in "Debug submissions" setting that logs a `received` row at the top of `handle_submission()` so future "no row" reports can be triaged without code access.
- Site Health card listing which Jetpack actions currently have callbacks bound.

## Phase 3 — (deferred) Channel payload hardening

Not needed for this report (no `error` rows in the log). Kept for reference:
- Recursive flatten of nested arrays before stringifying in `class-form-manager.php`.
- Switch `class-google-chat.php` to `cardsV2` with `decoratedText` widgets per field; cap body length.

## Phase 4 — Release v1.0.5

- **Allowed files:** `readme.txt`, `well-web-notify.php` (header + constant), `CHANGELOG.md`.
- **Forbidden files:** everything else.
- Bump 1.0.4 → 1.0.5 per the global versioning rule. Real CHANGELOG entry naming the fix (Jetpack Forms block delivery), no boilerplate.
- Git push + GitHub release + wp.org SVN sync via the existing release workflow.

## Decisions / notes

- **Why `transition_post_status` over a REST endpoint sniff:** Jetpack's REST routes have churned across versions; the `feedback` post-type signal is stable from Jetpack 7.x onward and survives every refactor of the submission entry point.
- **Why not also drop the legacy hook:** keeping both is free (deduped), and the legacy path is still primary on a lot of installs — losing it would risk breakage on the inverse population.
- **Why scope to one file:** the failure mode is entirely localized to "Jetpack submissions don't reach our entry point". Anything we change outside `class-jetpack.php` would be unrelated cleanup masquerading as a fix.
