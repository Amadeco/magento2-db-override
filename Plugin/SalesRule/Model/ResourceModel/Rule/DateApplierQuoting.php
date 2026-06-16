<?php
/**
 * Amadeco DbOverride module
 *
 * @category  Amadeco
 * @package   Amadeco_DbOverride
 * @copyright Ilan Parmentier
 */
declare(strict_types=1);

namespace Amadeco\DbOverride\Plugin\SalesRule\Model\ResourceModel\Rule;

use Amadeco\DbOverride\Model\ReservedWordEngine;
use Magento\SalesRule\Model\ResourceModel\Rule\DateApplier;

/**
 * Quote the from_date / to_date identifiers in the SalesRule validity-date filter.
 *
 * Why
 * ---
 * {@see DateApplier::applyDate()} adds the cart-price-rule validity window with UNQUOTED
 * column names:
 *
 *     $select->where('from_date is null or from_date <= ?', $now)
 *            ->where('to_date is null or to_date >= ?', $now);
 *
 * On MariaDB 12.x `TO_DATE` is a reserved function name, so the bare identifier `to_date`
 * is a syntax error (SQLSTATE 42000 / 1064). `from_date` is not reserved but is quoted here
 * too for symmetry. This filter runs whenever active rules are loaded
 * ({@see \Magento\SalesRule\Model\ResourceModel\Rule\Collection::setValidationFilter()}) —
 * i.e. on quote totals / cart / checkout, which are NEVER full-page cached, so this breakage
 * is not masked by FPC.
 *
 * Strategy
 * --------
 * `applyDate()` is a 3-line, side-effect-only method (it mutates the passed Select and
 * returns void). On an affected engine the plugin replaces it wholesale via `around` and
 * re-adds the two identical predicates with the identifiers back-tick quoted; `$proceed` is
 * deliberately NOT invoked, because it would append the unquoted (broken) predicates to the
 * same Select and a Zend Select WHERE part cannot be cleanly removed afterwards. On any other
 * engine (MySQL, MariaDB < 12) the bare identifiers are already valid, so the plugin defers
 * to `$proceed` — scoping the override to where it is actually needed and leaving upstream
 * untouched everywhere else. This is safe as long as no other plugin decorates `applyDate()`
 * (verified: this is the only one) and the upstream method body stays equivalent. Remove this
 * plugin once the identifiers are quoted upstream.
 *
 * @see \Magento\SalesRule\Model\ResourceModel\Rule\DateApplier::applyDate()
 */
class DateApplierQuoting
{
    /**
     * @param ReservedWordEngine $reservedWordEngine
     */
    public function __construct(
        private readonly ReservedWordEngine $reservedWordEngine
    ) {
    }

    /**
     * Replace the date filter with a back-tick-quoted equivalent, but only on MariaDB 12+.
     *
     * Signature intentionally mirrors the untyped original to avoid a contravariance break.
     *
     * @param DateApplier $subject
     * @param callable    $proceed Original applyDate (called as-is on unaffected engines).
     * @param \Magento\Framework\DB\Select $select Select being decorated (mutated in place).
     * @param int|string  $now     Comparison date (Y-m-d) the rule must be active on.
     * @return void
     */
    public function aroundApplyDate(DateApplier $subject, callable $proceed, $select, $now): void
    {
        if (!$this->reservedWordEngine->isAffected()) {
            $proceed($select, $now);
            return;
        }

        $select->where('`from_date` IS NULL OR `from_date` <= ?', $now)
            ->where('`to_date` IS NULL OR `to_date` >= ?', $now);
    }
}
