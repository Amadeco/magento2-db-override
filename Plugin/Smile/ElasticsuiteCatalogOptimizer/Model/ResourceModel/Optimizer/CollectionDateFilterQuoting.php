<?php
/**
 * Amadeco DbOverride module
 *
 * @category  Amadeco
 * @package   Amadeco_DbOverride
 * @copyright Ilan Parmentier
 */
declare(strict_types=1);

namespace Amadeco\DbOverride\Plugin\Smile\ElasticsuiteCatalogOptimizer\Model\ResourceModel\Optimizer;

use Amadeco\DbOverride\Model\ReservedWordEngine;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Smile\ElasticsuiteCatalogOptimizer\Model\ResourceModel\Optimizer\Collection;

/**
 * Quote the from_date / to_date identifiers in the optimizer "is active" date filter.
 *
 * Why
 * ---
 * {@see Collection::addIsActiveFilter()} appends its validity-window predicate with
 * UNQUOTED column names:
 *
 *     ->where('from_date is null or from_date <= ?', $date)
 *     ->where('to_date is null or to_date >= ?', $date);
 *
 * On MariaDB 12.x `TO_DATE` is a reserved function name, so the bare identifier `to_date`
 * is a syntax error (SQLSTATE 42000 / 1064) and the whole optimizer collection load fails —
 * taking down every catalog / search request that builds an ElasticSuite query on a
 * full-page-cache miss (developer mode, cache warmer, Varnish miss). `from_date` is not
 * reserved but is quoted here too for symmetry.
 *
 * Strategy
 * --------
 * On an affected engine the plugin replaces `addIsActiveFilter()` wholesale via `around`,
 * re-adding the same two predicates back-tick quoted plus the original `is_active` filter and
 * default-UTC-date logic. `$proceed` is deliberately NOT invoked there: it would append the
 * unquoted (broken) predicates to the same Select, which cannot then be cleanly removed. On
 * any other engine (MySQL, MariaDB < 12) the bare identifiers are already valid, so the plugin
 * defers to `$proceed` — scoping the override to where it is needed and leaving upstream
 * untouched everywhere else. Safe as long as no other plugin decorates the method (verified)
 * and the upstream body stays equivalent. Remove this plugin once the identifiers are quoted
 * upstream.
 *
 * @see \Smile\ElasticsuiteCatalogOptimizer\Model\ResourceModel\Optimizer\Collection::addIsActiveFilter()
 */
class CollectionDateFilterQuoting
{
    /**
     * @param DateTime           $date               Same date service the collection uses for the default window date.
     * @param ReservedWordEngine $reservedWordEngine Resolves whether the engine reserves `to_date`.
     */
    public function __construct(
        private readonly DateTime $date,
        private readonly ReservedWordEngine $reservedWordEngine
    ) {
    }

    /**
     * Replace the date filter with a back-tick-quoted equivalent, but only on MariaDB 12+.
     *
     * @param Collection  $subject
     * @param callable    $proceed Original addIsActiveFilter (called as-is on unaffected engines).
     * @param string|null $date    Date the filter must be active on (UTC, Y-m-d). Null = today.
     * @return Collection
     */
    public function aroundAddIsActiveFilter(Collection $subject, callable $proceed, $date = null): Collection
    {
        if (!$this->reservedWordEngine->isAffected()) {
            return $proceed($date);
        }

        $subject->addFieldToFilter('is_active', true);

        // Mirrors upstream Collection::addIsActiveFilter() verbatim (loose `== null` included)
        // so behaviour stays byte-for-byte equivalent — only the identifier quoting differs.
        if ($date == null) {
            $date = $this->date->date('Y-m-d');
        }

        $subject->getSelect()
            ->where('`from_date` IS NULL OR `from_date` <= ?', $date)
            ->where('`to_date` IS NULL OR `to_date` >= ?', $date);

        return $subject;
    }
}
