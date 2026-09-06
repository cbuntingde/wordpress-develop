# WordPress

A maintained distribution of WordPress on a contemporary PHP and database floor, with a single editing experience and no third-party AI provider surface.

Posts, pages, users, taxonomies, media, comments, multisite, the block editor, REST, themes, plugins, the scheduler, feeds, embeds, search, and internationalization all continue to work. Sites that depended on a removed capability are not silently mapped to a replacement; the runtime names the missing capability instead.

[![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](license.txt)
[![PHP: 8.5+](https://img.shields.io/badge/PHP-8.5%2B-777bb4.svg)](#requirements)
[![Database: MySQL 8.4 / MariaDB 10.11](https://img.shields.io/badge/DB-MySQL%208.4%20%2F%20MariaDB%2010.11%2B-4479A1.svg)](#requirements)
[![Editing: Block editor only](https://img.shields.io/badge/editing-block%20editor%20only-1e8cbe.svg)](#what-is-not-shipped)
[![AI provider integrations: not included](https://img.shields.io/badge/AI%20provider%20integrations-not%20included-d83a3a.svg)](#what-is-not-shipped)

---

## Overview

This distribution is a re-cut of WordPress for a contemporary PHP and database stack. The platform runs the same content, user, taxonomy, media, comment, multisite, REST, and scheduling surface that upstream WordPress provides, but is implemented for the modern PHP and database floor.

The block editor is the only editing experience. Themes must be block themes. Plugins are loaded through a typed contract.

The runtime fails fast on unsupported input. There are no silent fallbacks, no version-conditional code paths, and no compatibility shims for removed behavior. A request against an old PHP or database version, a table on a legacy character set or storage engine, a classic-theme activation, or a plugin that calls a removed symbol all fail with a clear, intentional message.

---

## Requirements

| Component | Required | Notes |
| :--- | :--- | :--- |
| PHP | 8.5 or newer | Requests on older versions are rejected at bootstrap with the detected version and the floor. |
| MySQL | 8.4 LTS or newer | MySQL 8.0 reached end of life in April 2026 and is not supported. |
| MariaDB | 10.11 LTS or newer | Older versions are not supported. |
| Table character set | utf8mb4 | Tables on older charsets are not accepted. |
| Table engine | InnoDB | Non-InnoDB tables are not accepted. |
| Browser | Modern evergreen | Older browsers are not tested. |
| Node | 20.x or newer | Required for the JavaScript build only. |
| npm | 10.x or newer | Required for the JavaScript build only. |

The supported version matrices are defined in `.version-support-php.json` and `.version-support-mysql.json` at the top of the repository.

---

## What is shipped

The platform provides the same content, user, taxonomy, media, comment, multisite, REST, and scheduling surface that upstream WordPress provides, implemented for the modern PHP and database floor:

* Posts, pages, and custom content types.
* Categories, tags, and custom taxonomies.
* Users and roles.
* Media and attachments.
* Comments and comment moderation.
* Revisions and scheduled publishing.
* Multisite.
* The block editor and block themes.
* REST.
* Plugins loaded through a typed contract.
* A durable, observable scheduler.
* Feeds, lazy embeds, and search.
* Internationalization, RTL, and accessibility.

---

## What is not shipped

The following are removed from the runtime. There is no replacement API for any of them.

**AI provider integrations.** The AI client, abilities registry, connectors, MCP adapter, and the bundled hooks for AI-generated images, titles, excerpts, and alt text. The settings screen and API-key management for AI providers are gone.

**Classic editing and theming.** The classic editor, classic themes, the Customizer, the classic widgets system, and PHP-template resolution. A site with classic-theme content still opens and always renders and edits through the block editor. A classic-theme fixture fails theme activation with a clear message.

**Legacy transport endpoints.** The legacy AJAX and XML-RPC handlers that existed only to serve the removed editing surface. Block-era admin screens keep their AJAX endpoints.

**Legacy JavaScript.** The legacy migration, view, and upload JavaScript libraries. Frontend emoji and eager embed auto-injection.

**Legacy filesystem paths.** FTP, FTPS, and SSH filesystem fallbacks; the in-admin file editor; the version-check polling channel.

**Legacy database patterns.** Single-option storage for transients and the rewrite-rule cache; string-keyed metadata free-for-all; the legacy pagination patterns; the legacy local plus GMT dual-writes.

---

## Installation

A Docker-based local environment is provided. After cloning:

```sh
npm install
npm run build:dev
npm run env:start
npm run env:install
```

The site is then available at <http://localhost:8889>. Rebuild on changes with `npm run dev`.

---

## Migration from upstream WordPress

Migrating from an upstream WordPress installation is a one-shot CLI step that runs before the site comes up on the new runtime. The migration is idempotent and resumable.

A preflight command inspects the existing site and reports:

* The detected PHP version versus the 8.5 floor.
* The detected MySQL or MariaDB version versus the 8.4 or 10.11 floors.
* The character set and engine of every table.
* Classic-editor content present in the database.
* Classic themes present in `wp-content/themes/`.
* Any AI or connectors configuration present in the database.

The preflight does not mutate data. The migration transforms the data in place, in a deterministic order, and writes a resumable log so a partial run can be completed without redoing completed steps.

Sites that depend on a removed capability are not silently mapped to a replacement. The preflight lists the capabilities that will not exist on the new runtime and stops before mutating data, so the operator can decide what to do.

---

## Security

Security reports follow the upstream process documented in [SECURITY.md](SECURITY.md). The platform is built and tested on every change; the PHP floor, the database floor, and the lint configuration are part of CI, so changes that would reintroduce a known weakness fail before they are accepted.

---

## License

GPL-2.0-or-later. See [license.txt](license.txt).
