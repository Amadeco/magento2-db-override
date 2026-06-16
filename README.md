# Amadeco DbOverride Module for Magento 2

[![Latest Stable Version](https://img.shields.io/github/v/release/Amadeco/magento2-db-override)](https://github.com/Amadeco/magento2-db-override/releases)
[![Magento 2](https://img.shields.io/badge/Magento-2.4.x-brightgreen.svg)](https://magento.com)
[![PHP](https://img.shields.io/badge/PHP-8+-brightblue.svg)](https://www.php.net)
[![License](https://img.shields.io/github/license/Amadeco/magento2-db-override)](https://github.com/Amadeco/magento2-db-override/blob/main/LICENSE)

[SPONSOR: Amadeco](https://www.amadeco.fr)

Extend database version compatibility for Magento 2 with experimental support for MariaDB 11.X/12.X and enhanced version detection.

## What it does

Three fixes for running Magento 2.4.x on a MariaDB version ahead of its certified matrix
(all rooted in the same uncertified MariaDB 12.x engine):

1. **Version detection** — adds MariaDB 11.4 / 12.0–12.4 patterns to
   `SqlVersionProvider::$supportedVersionPatterns`, so setup/version gating stops rejecting
   the engine with *"Current version of RDBMS is not supported"*.
2. **Default charset** — an after-plugin on the declarative-schema `Table` factory returns
   `utf8mb4` / `utf8mb4_general_ci` for MariaDB >= 10.4 (the hardcoded static map only knows
   10.4/10.6/11.4/mysql8.29). ⚠ Activate **only** once live tables are already utf8mb4 — see
   [`NOTES.md`](NOTES.md).
3. **Reserved word `to_date`** — `TO_DATE` is a reserved function name on MariaDB 12.x, so a
   bare `to_date` column identifier is a 1064 syntax error. Two `around` plugins re-add the
   `from_date`/`to_date` validity-date predicates back-tick quoted in
   `SalesRule\...\DateApplier::applyDate()` (cart/checkout, never FPC-cached → hard 500) and
   `Smile\ElasticsuiteCatalogOptimizer\...\Collection::addIsActiveFilter()` (FPC-masked, 500
   on cache miss). Scoped to MariaDB 12+ via `Model\ReservedWordEngine`; a true pass-through
   on MySQL / MariaDB < 12. *(Merged in from the former `Amadeco_MariaDbReservedWordFix`.)*

### Dependencies

`magento/framework` is the only hard requirement. The SalesRule and ElasticSuite optimizer
integrations are **soft**: declared via `<sequence>` in `module.xml` (load-order only — tolerates
absence) and listed under `suggest` in `composer.json`. Each date-filter plugin activates only
when its target module is installed; on a build without it, the plugin is skipped at
`setup:di:compile` and inert at runtime. No hard composer dependency is created on either module.

## Installation

### Composer Installation

Execute the following commands in your Magento root directory:

```bash
composer require amadeco/module-db-override
bin/magento module:enable Amadeco_DbOverride
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
```

### Manual Installation

1. Create directory `app/code/Amadeco/DbOverride` in your Magento installation
2. Clone or download this repository into that directory
3. Enable the module and update the database:

```bash
bin/magento module:enable Amadeco_DbOverride
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
```

## Compatibility

- Magento 2.4.x
- PHP 8.3

### 🛠 Supported Database Versions

- MySQL 5.7
- MySQL 8.0
- MariaDB 10.2 - 10.6 - 10.11
- MariaDB 11.4
- MariaDB 12.0 - 12.4

## Contributing

Contributions are welcome! Please read our [Contributing Guidelines](CONTRIBUTING.md).

## Support

For issues or feature requests, please create an issue on our GitHub repository.

## License

This module is licensed under the Open Software License ("OSL") v3.0. See the [LICENSE.txt](LICENSE.txt) file for details.
