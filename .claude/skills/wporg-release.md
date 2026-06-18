# MaxtDesign REST API Control — wp.org Release Skill

> **wp.org slug is `maxtdesign-rest-api-control`** — derived from the Plugin Name header at submission, NOT from the zip folder. (We learned this the hard way: the v1.0.4 review bounced because the text domain still said `maxtdesign-disable-rest-api`. Fixed in 1.0.5 — text domain + .pot now match the slug.)
>
> Identity is intentionally split: the **slug, text domain, distributed folder, .pot, and GitHub repo** are all `maxtdesign-rest-api-control` (GitHub renamed 2026-06-18). Still on the old form: the **local working folder (`C:\maxt\projects\plugin\maxtdesign-disable-rest-api`), main plugin file (`maxtdesign-disable-rest-api.php`), and PHP namespace (`MaxtDesign\DisableRestApi`)** — internal, deferred (renaming the local folder would orphan the Claude memory dir; main-file != slug is valid + common). Do not assume these match the slug. The `MDRA_` constant prefix and `mdra_` hook/option prefix are short-code based and locked (the `mdra_settings` option is live in users' DBs) — do NOT change them.

Project-specific wp.org release context for the procedure defined in `hq/.claude/agents/development/wporg-release.md`. Copy this file into a new plugin at `<plugin>/.claude/skills/wporg-release.md` and fill in the placeholders.

## Plugin Identity

| Field | Value |
|---|---|
| `wporg_slug` | `maxtdesign-rest-api-control` |
| wp.org page | https://wordpress.org/plugins/maxtdesign-rest-api-control/ |
| SVN repo | https://plugins.svn.wordpress.org/maxtdesign-rest-api-control/ |
| Git repo | https://github.com/MaxtDesign/maxtdesign-rest-api-control (renamed 2026-06-18; old URL auto-redirects) |
| Local working folder | `C:\maxt\projects\plugin\maxtdesign-disable-rest-api` (NOT renamed — would orphan the Claude memory dir) |
| Main plugin file | `maxtdesign-disable-rest-api.php` (inside the `maxtdesign-rest-api-control/` distributed folder) |
| Text domain | `maxtdesign-rest-api-control` |

**Note on `git_dir`:** Most plugins live at `~/maxtventures/plugins/<slug>/`. If this plugin lives elsewhere (e.g. inside another project's tree), set the path above and pass `--git-dir` to the release script. Example for mantlewp-connector: `git_dir = ~/maxtventures/web/mantlewp/mantlewp-connector/`.

## wp.org Distribution Flag
- [x] **Eligible for wp.org SVN procedure** — this plugin is distributed through wordpress.org and follows the standard release flow.
- [ ] Private / client-only — do NOT run the wporg-release procedure against this plugin.

## Version Source(s) of Truth
Every release requires these to match exactly:

| Location | File | Line/Pattern |
|---|---|---|
| Plugin header | `maxtdesign-disable-rest-api.php` | line 6: `Version: 1.0.5` |
| Readme stable tag | `readme.txt` | line 7: `Stable tag: 1.0.5` |
| Constant (if used) | `maxtdesign-disable-rest-api.php` | line 28: `define( 'MDRA_VERSION', '1.0.5' );` |
| Git tag | repo | `vX.Y.Z` on main |
| SVN tag | `/tags/X.Y.Z/` | created by procedure |

## Readme.txt Requirements
- `Tested up to:` current WP major (update every release)
- `Requires at least:` minimum supported WP — document here: `6.4`
- `Requires PHP:` minimum PHP — document here: `8.2`
- `== Changelog ==` has an entry for the new version before release
- Screenshots numbered to match files in `.wporg-assets/`

## Release Assets (wp.org `/assets/` directory)

Source files live in `.wporg-assets/` in the Git repo. Published separately from code via the asset-only procedure.

| Asset | File | Status |
|---|---|---|
| Banner hi-DPI | `banner-1544x500.png` | [current/needs update] |
| Banner standard | `banner-772x250.png` | [current/needs update] |
| Icon hi-DPI | `icon-256x256.png` | [current/needs update] |
| Icon standard | `icon-128x128.png` | [current/needs update] |
| Screenshots | `screenshot-N.png` | [count and status] |

## Distignore Specifics
Baseline comes from `hq/.claude/standards/wporg-svn-setup.md`. Additional per-plugin excludes:

```
# (none — org template at repo root)
```

## Release History

| Version | Date | Git SHA | Notes |
|---|---|---|---|
| 1.0.5 | 2026-06-18 | 8906082 / SVN r3577509 | **PUBLISHED.** Text domain changed to `maxtdesign-rest-api-control` to match the assigned slug (fixed v1.0.4 review rejection). Live at wordpress.org/plugins/maxtdesign-rest-api-control. |
| 1.0.4 | 2026-06-11 | (set by release) | Renamed display name "Disable REST API" → "REST API Control". Submitted to wp.org; review bounced: text domain didn't match the auto-derived slug. |
| 1.0.3 | 2026-06-11 | (set by release) | Pre-submission hardening: fixed parameterized-route whitelist, most-permissive multi-role, removed Logger placeholder, multisite defaults, i18n default message. GitHub only — wp.org slug still pending. |
| 1.0.2 | 2026-05-28 | (set by release) | Fix: root-index `/wp-json/` was fail-open. GitHub release only — wp.org SVN slug still pending approval. |
| 1.0.1 | 2026-05-28 | 2c17786 | Audit + WP 7.0 compat + hardening. GitHub release only — wp.org SVN slug pending approval, hold SVN push until approved. |

## Plugin-Specific Notes / Gotchas

- Seeded 2026-04-16 via kickoff-prompts/wporg-release-rollout.md
- Local clone was on a feature branch at seed time (`claude/wordpress-disable-rest-api-8j46p`); merge tooling branch to `main` when ready so release runs track production.

## Local Testing (LocalWP)

This plugin should be junction-linked to the LocalWP `plugin-test` site so edits are live-testable in WordPress without copy/deploy.

**Junction status:** `linked to default path`

Create/refresh junction:
```
# Standard (plugin at ~/maxtventures/plugins/<slug>/)
powershell -File hq/.claude/scripts/symlink-plugin-to-localwp.ps1 -Slug maxtdesign-disable-rest-api

# Custom git_dir (override source)
powershell -File hq/.claude/scripts/symlink-plugin-to-localwp.ps1 `
  -Slug maxtdesign-disable-rest-api `
  -SourceDir "[full-path-to-git-repo]"
```

Protocol: agents working on this plugin run `-ListAll` first to confirm the junction exists. If not, create it. When a phase of work completes, activate the plugin in WP admin and smoke test before marking done.

## Quick Reference

Run release (standard plugin):
```
bash hq/.claude/scripts/wporg-release.sh maxtdesign-disable-rest-api X.Y.Z
```

Run release (custom git_dir):
```
bash hq/.claude/scripts/wporg-release.sh maxtdesign-disable-rest-api X.Y.Z --git-dir [path]
```

Asset-only update:
```
bash hq/.claude/scripts/wporg-release.sh maxtdesign-disable-rest-api --assets-only
```

Dry run (no commit):
```
bash hq/.claude/scripts/wporg-release.sh maxtdesign-disable-rest-api X.Y.Z --dry-run
```

Full procedure details: `hq/.claude/agents/development/wporg-release.md`
