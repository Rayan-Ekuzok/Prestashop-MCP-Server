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
 * SomcpPermission - what the employee behind a token may do in PrestaShop.
 *
 * A tool names the back office page it works on and the action it performs,
 * exactly as PrestaShop states them in Team, Permissions: AdminProducts with
 * delete, AdminOrders with edit. The answer comes from the shop's own
 * permission table, never from a list kept here.
 *
 * Profile::getProfileAccesses() is that answer on every version the module
 * supports - checked on 8.0, 8.1, 8.2 and 9.1, same signature, and it accepts
 * a lookup by controller name. On PrestaShop 9 it reads the newer slug-based
 * permissions underneath, so it is the façade over that system rather than a
 * leftover. It caches per profile, so filtering thirty tools costs one query.
 *
 * An employee whose profile is the super administrator is granted everything
 * by the core itself, before any query runs, so there is no special case here.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SomcpPermission
{
    public const VIEW = 'view';
    public const ADD = 'add';
    public const EDIT = 'edit';
    public const DELETE = 'delete';

    /** @var array<int, string> */
    private const ACTIONS = [self::VIEW, self::ADD, self::EDIT, self::DELETE];

    /**
     * Whether the account behind a token still exists, may still sign in, and
     * is allowed on the shop being acted upon.
     *
     * Read on every call rather than once when the token was issued: an
     * account disabled this morning must not keep its assistant working until
     * the token expires weeks from now.
     *
     * The shop matters on a multistore installation and is the reason this
     * takes a parameter. Back office permissions belong to the profile and are
     * the same on every shop, so the profile alone would let a bookkeeper of
     * one store read the orders of the next one - they only have to point an
     * assistant at that store's address. PrestaShop answers this itself, with
     * the employee's shop associations, and a super administrator passes
     * everywhere.
     *
     * @param int $idEmployee
     * @param int|null $idShop the shop being acted upon. Null means the shop
     *                         answering this request, which is right on the MCP
     *                         endpoint and wrong in the back office, where the
     *                         shop in context is whichever one the
     *                         administrator happens to be working on.
     *
     * @return bool
     */
    public static function employeeIsUsable(int $idEmployee, ?int $idShop = null): bool
    {
        if ($idEmployee <= 0) {
            return false;
        }

        $employee = new Employee($idEmployee);
        if (!Validate::isLoadedObject($employee) || !$employee->active) {
            return false;
        }

        // Nothing to separate on a single shop, and an installation whose
        // associations were never filled in would lock every account out.
        if (!Shop::isFeatureActive()) {
            return true;
        }

        return (bool) $employee->hasAuthOnShop($idShop ?? Somcp::currentShopId());
    }

    /**
     * Whether an employee may perform one action on one back office page.
     *
     * Unknown page, unknown action, missing employee: refused. A tool that
     * names a controller which does not exist - a typo, a module uninstalled
     * since - must disappear, never open.
     *
     * @param int $idEmployee
     * @param string $controller back office controller, e.g. AdminProducts
     * @param string $action one of the ACTIONS constants
     * @param int|null $idShop see employeeIsUsable
     *
     * @return bool
     */
    public static function employeeCan(int $idEmployee, string $controller, string $action, ?int $idShop = null): bool
    {
        if ($idEmployee <= 0 || $controller === '' || !in_array($action, self::ACTIONS, true)) {
            return false;
        }

        if (!self::employeeIsUsable($idEmployee, $idShop)) {
            return false;
        }

        $employee = new Employee($idEmployee);
        $accesses = Profile::getProfileAccesses((int) $employee->id_profile, 'class_name');
        if (!is_array($accesses) || !isset($accesses[$controller][$action])) {
            return false;
        }

        return (bool) $accesses[$controller][$action];
    }

    /**
     * Whether an employee holds every permission a tool requires.
     *
     * @param int $idEmployee
     * @param array<int, array{controller:string, action:string}> $required
     * @param int|null $idShop see employeeIsUsable
     *
     * @return bool
     */
    public static function employeeHolds(int $idEmployee, array $required, ?int $idShop = null): bool
    {
        // Checked before the list, and whatever is in it: a tool that asks for
        // no permission used to be open to any account, including one deleted
        // since the token was issued, or one belonging to another shop, because
        // an empty list has nothing to refuse. The account itself has to be
        // usable on this shop first.
        if (!self::employeeIsUsable($idEmployee, $idShop)) {
            return false;
        }

        foreach ($required as $permission) {
            $controller = (string) $permission['controller'];
            $action = (string) $permission['action'];

            if (!self::employeeCan($idEmployee, $controller, $action, $idShop)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A permission as a tool declares it.
     *
     * @param string $controller
     * @param string $action
     *
     * @return array{controller:string, action:string}
     */
    public static function on(string $controller, string $action): array
    {
        return ['controller' => $controller, 'action' => $action];
    }

    /**
     * Technical wording, for the log and for the answer to a call.
     *
     * Deliberately the controller name rather than the page title: a log line
     * is read while diagnosing, where AdminOrders is exact and a translated
     * title is one more thing to map back.
     *
     * @param array<int, array{controller:string, action:string}> $required
     *
     * @return string
     */
    public static function describe(array $required): string
    {
        $parts = [];
        foreach ($required as $permission) {
            $parts[] = (string) $permission['controller']
                . ' (' . (string) $permission['action'] . ')';
        }

        return implode(', ', $parts);
    }

    /**
     * The same thing in the words the merchant sees on their own screens.
     *
     * The page title comes from PrestaShop's own tab, in the employee's
     * language, so the module never has to keep a list of page names in step
     * with the shop - a renamed or translated menu entry follows by itself. A
     * page the shop does not have falls back to its controller name rather
     * than to an empty line.
     *
     * @param array<int, array{controller:string, action:string}> $required
     * @param int|null $idLang the employee's language, the context one otherwise
     *
     * @return string
     */
    public static function label(array $required, ?int $idLang = null): string
    {
        if ($idLang === null) {
            $idLang = (int) Somcp::legacyContext()->language->id;
        }

        $parts = [];
        foreach ($required as $permission) {
            $controller = (string) $permission['controller'];
            $action = (string) $permission['action'];

            $tab = Tab::getInstanceFromClassName($controller, $idLang);
            $page = Validate::isLoadedObject($tab) && (string) $tab->name !== ''
                ? (string) $tab->name
                : $controller;

            $parts[] = $page . ' (' . self::actionLabel($action) . ')';
        }

        return implode(', ', $parts);
    }

    /**
     * The four PrestaShop permissions, in the merchant's language.
     *
     * @param string $action
     *
     * @return string
     */
    private static function actionLabel(string $action): string
    {
        switch ($action) {
            case self::VIEW:
                return Somcp::t('view');
            case self::ADD:
                return Somcp::t('add');
            case self::EDIT:
                return Somcp::t('edit');
            case self::DELETE:
                return Somcp::t('delete');
            default:
                return $action;
        }
    }
}
