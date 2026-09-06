# WordPress

A maintained distribution of WordPress that targets a contemporary PHP and database floor and provides a typed, vendor-aware SQL abstraction.

The runtime requires PHP 8.5, MySQL 8.4 or MariaDB 10.11 or newer, and core tables on utf8mb4 and InnoDB. Connections that fall short are refused at bootstrap with a clear, intentional message. Vendor-specific SQL is routed through a typed dialect so application code does not branch on engine.

[![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](license.txt)
[![PHP: 8.5+](https://img.shields.io/badge/PHP-8.5%2B-777bb4.svg)](#requirements)
[![Database: MySQL 8.4 / MariaDB 10.11](https://img.shields.io/badge/DB-MySQL%208.4%20%2F%20MariaDB%2010.11%2B-4479A1.svg)](#requirements)

---

## Overview

This distribution runs the same content, user, taxonomy, media, comment, multisite, REST, and scheduling surface that upstream WordPress provides, on a contemporary PHP and database stack.

The runtime enforces the supported platform floor at bootstrap:

* PHP 8.5 or newer. Requests on older versions are rejected before `wp-config.php` is read.
* MySQL 8.4 LTS or newer, or MariaDB 10.11 LTS or newer. The connection failure names the detected version and the two accepted floors.
* Every core table on utf8mb4 and InnoDB. Tables on a legacy character set or storage engine cause the connection to fail rather than silently coerce.

After a successful connection, the runtime picks a vendor-aware SQL dialect and exposes it as a typed abstraction. Application code reads the dialect and dispatches through a single contract; vendor branches do not appear in call sites.

---

## Requirements

| Component | Required | Notes |
| :--- | :--- | :--- |
| PHP | 8.5 or newer | Requests on older versions are rejected at bootstrap. |
| MySQL | 8.4 LTS or newer | MySQL 8.0 reached end of life in April 2026 and is not supported. |
| MariaDB | 10.11 LTS or newer | Older versions are not supported. |
| Table character set | utf8mb4 | Tables on older charsets are not accepted. |
| Table engine | InnoDB | Non-InnoDB tables are not accepted. |
| Browser | Modern evergreen | Older browsers are not tested. |
| Node | 20.x or newer | Required for the JavaScript build only. |
| npm | 10.x or newer | Required for the JavaScript build only. |

The supported version matrices are defined in `.version-support-php.json` and `.version-support-mysql.json` at the top of the repository.

---

## Database dialect

A typed `DatabaseDialect` abstraction lives under `src/wp-includes/database/`. The runtime picks a vendor implementation during connection setup:

* `MySqlDialect` for MySQL 8.4 and the maintained newer LTS.
* `MariaDbDialect` for MariaDB 10.11 and the maintained newer LTS.

The interface exposes the structured SQL primitives the platform needs today: identifier quoting, vendor-specific feature support, structured upserts, and JSON path expressions. Vendor detection happens once at the connection boundary; nothing downstream branches on engine.

The dialect is the foundation the typed query builder, transactional write paths, and migration tooling build on.

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

## Security

Security reports follow the upstream process documented in [SECURITY.md](SECURITY.md). The platform is built and tested on every change; the PHP floor, the database floor, and the lint configuration are part of CI, so changes that would reintroduce a known weakness fail before they are accepted.

---

## License

GPL-2.0-or-later. See [license.txt](license.txt).
