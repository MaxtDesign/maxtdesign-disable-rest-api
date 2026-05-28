# MaxtDesign Disable REST API — wp.org Release Skill

Project-specific wp.org release context for the procedure defined in `hq/.claude/agents/development/wporg-release.md`. Copy this file into a new plugin at `<plugin>/.claude/skills/wporg-release.md` and fill in the placeholders.

## Plugin Identity

| Field | Value |
|---|---|
| `wporg_slug` | `maxtdesign-disable-rest-api` |
| wp.org page | https://wordpress.org/plugins/maxtdesign-disable-rest-api/ |
| SVN repo | https://plugins.svn.wordpress.org/maxtdesign-disable-rest-api/ |
| Git repo | https://github.com/MaxtDesign/maxtdesign-disable-rest-api |
| `git_dir` | `~/maxtventures/plugins/maxtdesign-disable-rest-api/` |
| Main plugin file | `maxtdesign-disable-rest-api.php` |
| Text domain | `maxtdesign-disable-rest-api` |

**Note on `git_dir`:** Most plugins live at `~/maxtventures/plugins/<slug>/`. If this plugin lives elsewhere (e.g. inside another project's tree), set the path above and pass `--git-dir` to the release script. Example for mantlewp-connector: `git_dir = ~/maxtventures/web/mantlewp/mantlewp-connector/`.

## wp.org Distribution Flag
- [x] **Eligible for wp.org SVN procedure** — this plugin is distributed through wordpress.org and follows the standard release flow.
- [ ] Private / client-only — do NOT run the wporg-release procedure against this plugin.

## Version Source(s) of Truth
Every release requires these to match exactly:

| Location | File | Line/Pattern |
|---|---|---|
| Plugin header | `maxtdesign-disable-rest-api.php` | line 6: `Version: 1.0.2` |
| Readme stable tag | `readme.txt` | line 7: `Stable tag: 1.0.2` |
| Constant (if used) | `maxtdesign-disable-rest-api.php` | line 28: `define( 'MDRA_VERSION', '1.0.2' );` |
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
