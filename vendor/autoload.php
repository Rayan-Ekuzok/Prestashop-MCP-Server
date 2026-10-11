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
 * PrestaShop registers this file for every installed module, from
 * AppKernel::enableComposerAutoloaderOnModules() - present on 8.0 as on 9.1.
 * It is what makes the module's one Symfony class reachable before the module
 * itself is loaded, which is when the router needs it.
 *
 * Written by hand rather than generated: the module has no dependency to
 * resolve, so all Composer would add here is a class map for a single
 * namespace and several hundred lines of loader nobody reads.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Novalo\\Somcp\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/../src/' . $relative . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});
