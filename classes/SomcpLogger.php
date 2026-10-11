<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to obtain
 * it through the world-wide-web, please send an email to
 * contact@novalo.fr so we can send you a copy immediately.
 *
 * @author    Novalo <contact@novalo.fr>
 * @copyright 2026 Novalo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License v3.0 (AFL-3.0)
 */
declare(strict_types=1);

/*
 * SomcpLogger - the security journal of the module.
 *
 * It records what happened, never what was handed out. Tokens, codes and client
 * secrets are scrubbed on the way in rather than at display time: a value that
 * never reaches the table cannot leak from a database export, a support dump or
 * a screenshot.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SomcpLogger
{
    public const OUTCOME_INFO = 'info';
    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_FAILURE = 'failure';

    /** @var int Days a log line is kept. */
    private const RETENTION_DAYS = 30;

    /** @var int One write in this many triggers the purge of old lines. */
    private const PURGE_CHANCE = 100;

    /**
     * Records an event.
     *
     * @param string $event short machine-readable name, e.g. token_issued
     * @param string $outcome one of the OUTCOME_* constants
     * @param string $message free text, already scrubbed of secrets by the caller
     * @param string $clientId OAuth client the event belongs to, if any
     * @param int $idEmployee employee the event belongs to, if any
     */
    public static function log(
        string $event,
        string $outcome = self::OUTCOME_INFO,
        string $message = '',
        string $clientId = '',
        int $idEmployee = 0,
    ): void {
        try {
            if (!SomcpConfig::isOn(SomcpConfig::LOG_ENABLED)) {
                return;
            }

            Db::getInstance()->execute(
                'INSERT INTO `' . _DB_PREFIX_ . 'somcp_log`
                 (`id_shop`, `event`, `outcome`, `client_id`, `id_employee`, `message`, `ip_address`)
                 VALUES (' . Somcp::currentShopId() . ", '"
                 . pSQL(Tools::substr($event, 0, 50)) . "', '"
                 . pSQL($outcome) . "', '"
                 . pSQL(Tools::substr(self::scrub($clientId), 0, 255)) . "', "
                 . (int) $idEmployee . ", '"
                 . pSQL(Tools::substr(self::scrub($message), 0, 2000)) . "', '"
                 . pSQL(Tools::substr((string) Tools::getRemoteAddr(), 0, 45)) . "')"
            );

            if (mt_rand(1, self::PURGE_CHANCE) === 1) {
                self::purgeOld();
            }
        } catch (Throwable $e) {
            // A journal that cannot be written must not break the flow it
            // was watching: an OAuth exchange is more useful than its trace.
        }
    }

    /**
     * Last log lines, most recent first.
     *
     * @param int $limit
     *
     * @return array<int, array<string, mixed>>
     */
    public static function recent(int $limit = 50): array
    {
        // The multistore selector decides the scope: all shops, the shops of a
        // group, or one shop. An event still belongs to the shop it happened
        // on - the context only filters what is shown.
        return Db::getInstance()->executeS(
            'SELECT l.*, s.`name` AS `shop_name`
             FROM `' . _DB_PREFIX_ . 'somcp_log` l
             LEFT JOIN `' . _DB_PREFIX_ . 'shop` s ON s.`id_shop` = l.`id_shop`
             WHERE l.`id_shop` ' . self::contextShopCondition() . '
             ORDER BY l.`id_log` DESC
             LIMIT ' . (int) $limit
        ) ?: [];
    }

    /**
     * Empties the journal for the current shop.
     *
     * DELETE rather than TRUNCATE: the latter needs a privilege that shared
     * hosting often withholds, and fails without a word.
     */
    public static function clear(): void
    {
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'somcp_log` WHERE `id_shop` ' . self::contextShopCondition()
        );
    }

    /**
     * SQL condition restricting rows to the shops of the current context.
     *
     * Shop::getContextListShopID() is the native answer to "which shops is the
     * administrator working on", and returns the one shop, the shops of the
     * group, or all of them.
     *
     * @return string an SQL fragment starting with IN
     */
    public static function contextShopCondition(): string
    {
        $ids = array_map('intval', Shop::getContextListShopID());
        if ($ids === []) {
            $ids = [Somcp::currentShopId()];
        }

        return 'IN (' . implode(', ', $ids) . ')';
    }

    /**
     * Masks anything that looks like a secret.
     *
     * The callers are not supposed to pass one, but a message built from an
     * exception can carry a query string or a header without anyone noticing.
     * This is the last net, not the first.
     *
     * @param string $text
     *
     * @return string
     */
    public static function scrub(string $text): string
    {
        $patterns = [
            '/(Bearer\s+)[A-Za-z0-9\-._~+\/]+=*/i',
            '/((?:access_token|refresh_token|client_secret|code|code_verifier|token)=)[^&\s"\']+/i',
        ];

        return (string) preg_replace($patterns, '$1[redacted]', $text);
    }

    private static function purgeOld(): void
    {
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'somcp_log`
             WHERE `date_add` < DATE_SUB(NOW(), INTERVAL ' . (int) self::RETENTION_DAYS . ' DAY)'
        );
    }
}
