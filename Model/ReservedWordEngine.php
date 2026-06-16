<?php
/**
 * Amadeco DbOverride module
 *
 * @category  Amadeco
 * @package   Amadeco_DbOverride
 * @copyright Ilan Parmentier
 */
declare(strict_types=1);

namespace Amadeco\DbOverride\Model;

use Magento\Framework\DB\Adapter\SqlVersionProvider;

/**
 * Detects whether the running database engine reserves the {@see to_date} identifier.
 *
 * Why
 * ---
 * `TO_DATE` became a reserved function name in MariaDB 12.x, so a bare `to_date` column
 * identifier is a 1064 syntax error there — but valid on MySQL and on MariaDB < 12. The
 * quoting plugins in this module only need to override the upstream methods on an affected
 * engine; everywhere else the original (unquoted) SQL already works, so they should defer to
 * `$proceed`. This keeps the no-`$proceed` overrides scoped to MariaDB 12+, limiting the
 * blast radius if an upstream method body drifts.
 *
 * The result is resolved once and memoised — `SqlVersionProvider` itself issues a
 * `SHOW variables` query, so it must not be hit per filter-build in the hot paths
 * (cart validation, ElasticSuite query build).
 *
 * Fail-safe: if version detection throws (e.g. the engine is not in
 * `supportedVersionPatterns`), we assume the engine IS affected and let the plugins quote.
 * Back-tick quoting is valid SQL on every engine, so the safe fallback is "quote", never
 * "emit the bare identifier".
 */
class ReservedWordEngine
{
    /**
     * First MariaDB major version that reserves `TO_DATE`.
     */
    private const FIRST_RESERVED_MARIADB_MAJOR = 12;

    /**
     * @var bool|null Memoised detection result for the current request.
     */
    private ?bool $affected = null;

    /**
     * @param SqlVersionProvider $sqlVersionProvider
     */
    public function __construct(
        private readonly SqlVersionProvider $sqlVersionProvider
    ) {
    }

    /**
     * Whether the running engine reserves `to_date` and therefore needs the quoted predicates.
     *
     * @return bool
     */
    public function isAffected(): bool
    {
        if ($this->affected === null) {
            $this->affected = $this->detect();
        }

        return $this->affected;
    }

    /**
     * Resolve the engine once: MariaDB with a major version >= 12.
     *
     * @return bool
     */
    private function detect(): bool
    {
        try {
            if (!$this->sqlVersionProvider->isMariaDbEngine()) {
                return false;
            }

            // getSqlVersion() returns the matched template, e.g. "12.3." for MariaDB 12.3.2.
            $major = (int) strtok($this->sqlVersionProvider->getSqlVersion(), '.');

            return $major >= self::FIRST_RESERVED_MARIADB_MAJOR;
        } catch (\Throwable $e) {
            // Unknown / unsupported engine: fall back to the safe path (quoting is universal).
            return true;
        }
    }
}
