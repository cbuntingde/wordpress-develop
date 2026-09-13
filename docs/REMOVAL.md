# Why the WordPress AI Infrastructure Was Removed

This fork strips out the entire AI client, Connectors API, and related
infrastructure that was introduced in upstream WordPress 7.0 ("Armstrong",
released May 2026).  This document explains the background, the specific
problems that surfaced, and why removal was the right call for this project.

## What was removed

The upstream 7.0 release bundled roughly 42,000 lines of new code across
206 files:

- `wp-includes/ai-client.php` and the `WP_AI_Client_Prompt_Builder` wrapper
- `wp-includes/php-ai-client/` — a full third-party SDK (provider registry,
  HTTP transport, model metadata, DTOs, event dispatching, caching, etc.)
- `wp-includes/connectors.php` + `class-wp-connector-registry.php` — the
  Connectors API for registering AI providers and storing their credentials
- `src/wp-admin/options-connectors.php` — the Settings > Connectors admin
  page where site owners entered AI API keys
- REST API routes and React-based admin UI for the connectors dashboard
- Associated PHPUnit test suites, mock objects, build tooling, and config

All of it has been excised from this fork.

## Why it was controversial

### 1. Confirmed security bug: AI API keys exposed via browser autocomplete

The AI integration setup form used a standard text input for API keys,
which triggered the browser's autocomplete/autofill dropdown and displayed
the key in plain text.  This was reported as [core.trac.wordpress.org
ticket #65303](https://core.trac.wordpress.org/ticket/65303).

Why this matters specifically for AI keys:

- AI API keys (OpenAI, Anthropic, Google, etc.) are monetizable credentials
  — attackers who steal them can run up charges on the victim's account.
- WordPress's plugin trust model was never designed to protect this class of
  secret at the platform level.
- Patchstack founder Oliver Sild warned the bug would trigger "an absolute
  rush by hackers to steal AI keys" from WordPress sites.

A single input-field fix would have resolved the immediate bug, but the
broader architectural concern remained: core now centrally stores valuable
third-party credentials that every plugin on the site can attempt to read.

### 2. Developer consensus: this belongs in a plugin, not core

A proposal to merge the "Knowledge Custom Post Type" into core — a post
type designed explicitly as "instructional content for AI agents to interact
with the site" — drew near-unanimous developer pushback on Make WordPress
Core and in the broader community:

> "This looks like an unnecessary overreach. Better to let developers
> decide it for individual sites rather than force a post type onto
> everyone." — commenter on the merge proposal

> "I know it says this feature is provided for both 'author-facing and
> agent-facing' applications, but it feels like AI/LLMs are driving the
> conception of the feature." — mrwweb, Make WordPress Core

> "Most sites already have content standards" does not strike me as
> accurate. That is a very real need and use-case, but I'm not sure it's
> one that would justify a new core feature on its own. — mrwweb

Critics noted the public framing emphasized human editors while the actual
GitHub issue described the feature as being "for AI-powered tools
integrating with WordPress."  Several developers also suggested the feature
benefited Automattic's WordPress.com enterprise customers more than the
average self-hosted user.

### 3. Bundled SDK version conflict caused fatal errors in plugins

The AI Experiments plugin (`WordPress/ai` on GitHub) bundled an older copy
of `php-ai-client` (v0.3.1) and loaded it via a Jetpack autoloader with
`prepend: true`, which overrode the newer v0.4.x SDK shipped in
`wp-includes/`.  Third-party provider plugins that implemented the core
SDK's interfaces hit PHP fatal errors at runtime:

> "Any third-party model class that implements
> TextGenerationModelInterface and is developed against the core SDK will
> trigger a PHP Fatal error at runtime: Class ... contains 1 abstract
> method and must therefore be declared abstract or implement the
> remaining method."

This was a direct consequence of bundling a fast-moving library in core
before the plugin ecosystem had converged on a stable interface.

### 4. The pace of the initiative outpaced community confidence

The WordPress AI team was formed in mid-2025 with a stated mission to build
"foundational building blocks" and "no immediate plans to integrate LLMs
into Core."  Within months, however, a full AI client SDK with provider
registry, credential management, REST endpoints, admin UI, and an
Abilities API integration was merged into core.  Many contributors felt the
initiative was moving faster than the surrounding ecosystem — provider
plugins, security patterns, testing, and documentation — could keep up with.

### 5. The pattern repeated: ambitious features pulled for stability

WordPress 7.0 also removed Real-Time Collaboration (RTC) twelve days
before launch.  Matt Mullenweg cited "surface area, race conditions, server
load, memory efficiency, and recurring bugs found through fuzz testing" and
said he was "not confident the current approach is robust enough to include
in Core at this time."  The AI client suffered from the same syndrome: it
worked in controlled demos but had not proven itself across the vast range
of hosting environments, PHP versions, and plugin combinations that
WordPress must support.

## Conclusion

Removing the AI infrastructure from this fork was a deliberate decision to
keep the codebase focused on core publishing functionality and to avoid the
security, compatibility, and maintenance burdens that the upstream merge
introduced.  The WordPress plugin ecosystem remains the right place for
site owners who want AI features to opt in, rather than having that
functionality forced onto every installation at the platform level.
