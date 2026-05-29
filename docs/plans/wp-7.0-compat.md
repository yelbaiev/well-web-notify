# WordPress 7.0 Compatibility — Well Web Notify

**Trigger:** WordPress.org "WP 7.0 is imminent" dev email (WP 7.0 releases 2026-05-20).
**Current state:** v1.0.3, `Tested up to: 6.9`, `Requires PHP: 8.2`, `Requires at least: 6.8`.
**Target release:** v1.0.4.

## Email items → relevance for this plugin

| WP 7.0 item | Relevant? | Notes |
|---|---|---|
| Update `Tested up to` → 7.0 | **Yes (mandatory)** | readme.txt + plugin header at 6.9; avoids the "untested" warning banner |
| New "Modern" default admin theme | **Yes** | Plugin ships custom `assets/css/admin.css` with hardcoded *classic* palette (`#2271b1`, `#1d2327`, `#c3c4c7`, `#f0f0f1`…). It does **not** use `@wordpress/components`, so it won't auto-restyle — classic-blue accents may clash with Modern chrome |
| Iframed editor changes | **No** | Plugin has zero block-editor surface (no `enqueue_block_editor_assets`, no blocks). Confirmed by grep |
| PHP 7.2/7.3 dropped (new min 7.4, rec 8.3) | **Info only** | Plugin already requires PHP 8.2 — above the new floor. No change needed |
| AI Client API | No | Not an AI plugin |
| Connections / Connectors API (central API-key mgmt) | No (future) | Plugin stores its own bot tokens/webhooks (encrypted). Adopting central key mgmt is out of scope for this release |
| Client-side Abilities API | No | No JS abilities |
| PHP-only block registration | No | No blocks |

**Net:** the only mandatory change is the `Tested up to` bump. The only real *testing* risk is the admin UI under the Modern theme.

---

## Phase 1 — Verify on WP 7.0 RC + Plugin Check (gate, no code change yet)

- **Goal:** Confirm the plugin actually loads and works on WP 7.0 before claiming compatibility.
- **Allowed files:** none (manual testing only).
- **Forbidden files:** all source.
- **Steps:**
  - Install **WordPress Beta Tester** on a staging site, switch to the WP 7.0 RC channel.
  - Activate Well Web Notify; exercise: settings page, save a channel, send a test notification, view the log, dashboard widget, Site Health panel, trigger the review notice.
  - Install **Plugin Check**, run it against the plugin, record any errors/warnings.
- **Verification (gate):** plugin activates without fatal/notice errors on WP 7.0; Plugin Check shows no new errors. Capture screenshots of every admin surface under the **Modern** theme — these feed Phase 2's decision.
- **Output:** a short findings note (clashes / Plugin Check items). If clean and visually fine → skip Phase 2.

## Phase 2 — Modern admin theme adaptation (CONDITIONAL on Phase 1 findings)

- **Goal:** Make the admin UI adapt to the active admin theme instead of hardcoding the classic palette, so it looks native under Modern (and other admin color schemes).
- **Allowed files:** `assets/css/admin.css` primarily; only if a specific surface needs it, the inline-styled spots in `includes/options.php`, `includes/dashboard-widget.php`, `includes/site-health.php`, `includes/class-review-notice.php`.
- **Forbidden files (regression guardrail):** `well-web-notify.php`, all channel logic (`includes/channels/**`), all form integrations (`includes/forms/**`), `includes/class-channel-manager.php`, `includes/class-form-manager.php`, `includes/class-log.php`, `includes/class-health-check.php`, `includes/class-site-verify.php`, `includes/functions.php`. No logic changes — CSS/markup only.
- **Approach:** Replace hardcoded accent/border/background hexes with WP admin CSS variables where they map cleanly (e.g. accents → `--wp-admin-theme-color` / `--wp-admin-theme-color-darker-10`, borders/surfaces → the `--wp-admin-*` neutrals). Keep semantic status colors (success `#00a32a`, error `#d63638`) as-is — they're intentional, not theme chrome.
- **Behaviour change:** Brand `#FF6600` accents stay; WP-chrome accents follow the active theme. No classic-blue bleed under Modern.
- **Verification:** Re-check every admin surface under Modern (default) + at least one alternate admin color scheme; compare against Phase 1 screenshots. Brand orange still correct.
- **Scope cap:** ~400-line diff max, CSS-only. If a surface needs deeper rework, split it out — do not let this balloon.

## Phase 3 — Bump compatibility + version (the mandatory release)

- **Goal:** Mark WP 7.0 compatibility and cut v1.0.4.
- **Allowed files:** `readme.txt`, `well-web-notify.php`, `CHANGELOG.md`.
- **Forbidden files:** everything else.
- **Changes:**
  - `readme.txt`: `Tested up to: 7.0`, `Stable tag: 1.0.4`, add `== Changelog ==` `= 1.0.4 =` entry.
  - `well-web-notify.php`: header `Tested up to: 7.0`, header `Version: 1.0.4`, `WELLWEB_NOTIFY_VERSION` → `1.0.4`.
  - `CHANGELOG.md`: `## Version 1.0.4` entry (real notes — e.g. "Tested and confirmed compatible with WordPress 7.0" + any Phase 2 CSS change).
  - Leave `Requires PHP: 8.2` and `Requires at least: 6.8` unchanged.
- **Behaviour change:** WordPress.org lists the plugin as compatible with 7.0; no untested-warning banner.
- **Verification:** versions consistent across header / constant / readme `Stable tag`; readme parses cleanly (WP readme validator).

## Phase 4 — Release to Git + WordPress.org SVN

- **Goal:** Ship 1.0.4 to both remotes.
- **Git:** commit Phases 2–3 in `well-web-dev-projects/well-web-notify`, push to `origin/main`, create the matching GitHub release (changelog notes surface in the admin "Notes" column).
- **SVN:** use the **current** checkout `~/wp-svn/well-web-notify-svn` (trunk already at 1.0.3, tags 1.0.2 + 1.0.3). Copy updated files into `trunk/`, `svn cp trunk tags/1.0.4`, update `assets/` if changed, `svn ci -m "Release 1.0.4 — WP 7.0 compatible"`.
- **Verification:** WordPress.org plugin page shows v1.0.4 and "Tested up to: 7.0" after the SVN sync.

---

## Decisions / notes
- **PHP floor stays at 8.2.** WP 7.0's new minimum is 7.4 (recommended 8.3); 8.2 is comfortably above the floor, and lowering it would mean testing older PHP for no benefit. No ADR needed.
- **Connectors/AI/Abilities APIs intentionally skipped** — no current feature needs them. Revisit only if we add a feature that benefits (e.g. central management of bot tokens via the Connections screen).
- If Phase 1 shows the UI already looks fine under Modern, **Phase 2 is skipped** and the release is just Phases 3–4 (a trivial patch).
