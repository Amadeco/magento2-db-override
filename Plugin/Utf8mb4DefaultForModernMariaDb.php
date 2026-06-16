<?php
/**
 * Amadeco DbOverride module
 *
 * @category  Amadeco
 * @package   Amadeco_DbOverride
 * @copyright Ilan Parmentier
 */
declare(strict_types=1);

namespace Amadeco\DbOverride\Plugin;

use Magento\Framework\DB\Adapter\SqlVersionProvider;
use Magento\Framework\Setup\Declaration\Schema\Dto\Factories\Table;

/**
 * Forces utf8mb4 / utf8mb4_general_ci as the declarative-schema default charset
 * and collation on modern MariaDB.
 *
 * Magento resolves the default table charset from a hardcoded `private static`
 * array in {@see Table} that only maps 10.4. / 10.6. / 11.4. / mysql_8_29 to
 * utf8mb4; every other engine (including MariaDB 12.x/13.x) falls through to
 * `'utf8'` (= utf8mb3). That array is not reachable by a DI `<argument>` override,
 * which is why the companion SqlVersionProvider patch (etc/di.xml) cannot fix it.
 *
 * The factory methods are public and non-final, so an after-plugin can. This
 * aligns the declared default with what Magento would natively pick on a
 * certified MariaDB LTS (10.6 / 11.4), without downgrading the engine.
 *
 * IMPORTANT (see NOTES.md): this makes the declarative schema DECLARE utf8mb4.
 * It must only be activated once the live tables are ALREADY utf8mb4, otherwise
 * `setup:db-schema:upgrade` will try to convert every legacy utf8mb3 table on the
 * next run. Convert the data first (controlled window), then deploy this.
 */
class Utf8mb4DefaultForModernMariaDb
{
    /**
     * Charset applied as the declarative-schema default on modern MariaDB.
     */
    private const CHARSET = 'utf8mb4';

    /**
     * Collation applied as the declarative-schema default on modern MariaDB.
     */
    private const COLLATION = 'utf8mb4_general_ci';

    /**
     * Lowest MariaDB version with full native utf8mb4 default support.
     *
     * MySQL version keys (5.7. / 8.0. / 8.4.) all compare below this, so MySQL
     * engines are never affected by this plugin.
     */
    private const MIN_MARIADB_VERSION = '10.4';

    /**
     * @param SqlVersionProvider $sqlVersionProvider
     */
    public function __construct(
        private readonly SqlVersionProvider $sqlVersionProvider
    ) {
    }

    /**
     * Override the default charset with utf8mb4 on modern MariaDB.
     *
     * @param Table $subject
     * @param string $result
     * @return string
     */
    public function afterGetDefaultCharset(Table $subject, string $result): string
    {
        return $this->isModernMariaDb() ? self::CHARSET : $result;
    }

    /**
     * Override the default collation with utf8mb4_general_ci on modern MariaDB.
     *
     * @param Table $subject
     * @param string $result
     * @return string
     */
    public function afterGetDefaultCollation(Table $subject, string $result): string
    {
        return $this->isModernMariaDb() ? self::COLLATION : $result;
    }

    /**
     * Whether the running engine is MariaDB >= 10.4.
     *
     * Uses version_compare against the resolved version prefix (e.g. "12.3"), so
     * it is forward-proof for future MariaDB majors (12.x, 13.x, ...).
     *
     * @return bool
     */
    private function isModernMariaDb(): bool
    {
        $version = rtrim($this->sqlVersionProvider->getSqlVersion(), '.');

        return $version !== ''
            && version_compare($version, self::MIN_MARIADB_VERSION, '>=');
    }
}
