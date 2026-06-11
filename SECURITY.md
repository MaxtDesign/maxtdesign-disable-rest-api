# Security Policy

## Supported versions

MaxtDesign REST API Control follows a rolling-release model on WordPress.org.
Only the latest published version receives security fixes. Always run the most
recent release.

| Version | Supported |
| ------- | --------- |
| Latest stable (1.0.x) | ✅ |
| Older releases | ❌ |

## Reporting a vulnerability

If you believe you have found a security vulnerability in this plugin, please
report it privately. **Do not open a public GitHub issue, pull request, or
WordPress.org support thread for security matters.**

Email: **security@maxtdesign.com**

Please include:

- A description of the vulnerability and its potential impact.
- The plugin version affected.
- Step-by-step instructions to reproduce (proof-of-concept code, requests, or
  screenshots where relevant).
- Any suggested remediation, if you have one.

## What to expect

- **Acknowledgement** within 3 business days.
- An initial assessment and severity classification within 7 business days.
- Coordinated disclosure: we will agree on a disclosure timeline with you and
  credit you in the changelog unless you prefer to remain anonymous.
- A patched release pushed to WordPress.org as soon as a fix is validated, with
  an `== Upgrade Notice ==` entry advising users to update.

## Scope

This plugin filters REST API access using the core `rest_authentication_errors`
filter and stores a single settings option. It registers no custom REST
endpoints, makes no external HTTP requests, and processes no visitor data.
In-scope reports include (but are not limited to): authorization bypasses that
expose REST data the plugin is configured to block, stored or reflected XSS in
the settings screen, CSRF on the settings/import/export/reset actions, and
unsafe handling of imported settings files.

Out of scope: vulnerabilities in WordPress core, other plugins, the hosting
environment, or issues that require an already-compromised administrator
account.

## Coordinated disclosure programs

We welcome reports submitted through established WordPress security programs
(for example, Patchstack and Wordfence). Reports routed through those programs
reach us through the same triage process described above.
