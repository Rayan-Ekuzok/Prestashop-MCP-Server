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
 * CI check: config/services.yml and config/routes.yml load, compile and
 * resolve the way PrestaShop's kernel does it.
 *
 * PrestaShop compiles every active module's services.yml into the one
 * container of the back office. A service that cannot be built there does not
 * break the module, it breaks the whole back office with a 500 - which is what
 * a constructor asking for a PrestaShop 9 class did on PrestaShop 8. So the
 * container is compiled twice: once without anything PrestaShop 9 adds (what
 * PrestaShop 8 gives us), once with it.
 *
 * Usage: php compile-container.php <composer vendor dir> <module root>
 * The vendor dir holds the Symfony line of the PrestaShop version under test:
 * 4.4 for PrestaShop 8, 6.4 for PrestaShop 9.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

[, $vendor, $module] = $argv + [null, null, null];
if ($vendor === null || $module === null) {
    fwrite(STDERR, "usage: php compile-container.php <vendor dir> <module root>\n");
    exit(2);
}

// The module's classes stop at their first line without it, as on any
// PrestaShop file reached directly.
define('_PS_VERSION_', '0.0.0');

require rtrim($vendor, '/') . '/autoload.php';
// The module's own autoloader, the one PrestaShop registers for it.
require rtrim($module, '/') . '/vendor/autoload.php';

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader as ServicesLoader;
use Symfony\Component\Routing\Loader\YamlFileLoader as RoutesLoader;

$symfony = class_exists(Composer\InstalledVersions::class)
    ? (string) Composer\InstalledVersions::getPrettyVersion('symfony/dependency-injection')
    : 'unknown';
$failed = false;

foreach (['PrestaShop 8 core' => false, 'PrestaShop 9 core' => true] as $label => $withPs9) {
    $container = new ContainerBuilder();
    if ($withPs9) {
        // What PrestaShop 9 registers and PrestaShop 8 does not: a controller
        // may look it up at run time, never require it to be built.
        if (!class_exists('PrestaShopBundle\\Security\\Admin\\UserTokenManager', false)) {
            eval('namespace PrestaShopBundle\\Security\\Admin; class UserTokenManager { public function getSymfonyToken(): string { return ""; } }');
        }
        $container->register('PrestaShopBundle\\Security\\Admin\\UserTokenManager', 'PrestaShopBundle\\Security\\Admin\\UserTokenManager')->setPublic(true);
    }

    try {
        (new ServicesLoader($container, new FileLocator($module . '/config')))->load('services.yml');
        $container->compile();
        $count = 0;
        foreach ($container->getServiceIds() as $id) {
            if (strncmp($id, 'Novalo\\', 7) !== 0) {
                continue;
            }
            $container->get($id);
            ++$count;
        }
        printf("ok   Symfony %s, %s: %d service(s) built\n", $symfony, $label, $count);
    } catch (Throwable $e) {
        printf("FAIL Symfony %s, %s: %s: %s\n", $symfony, $label, get_class($e), $e->getMessage());
        $failed = true;
    }
}

// Every route must point at a service that exists and a method it has.
try {
    $routes = (new RoutesLoader(new FileLocator($module . '/config')))->load('routes.yml');
    foreach ($routes->all() as $name => $route) {
        $controller = (string) $route->getDefault('_controller');
        [$class, $method] = explode('::', $controller, 2) + [1 => '__invoke'];
        if (!class_exists($class) || !method_exists($class, $method)) {
            printf("FAIL route %s: %s does not exist\n", $name, $controller);
            $failed = true;
            continue;
        }
        if (!$container->has($class)) {
            printf("FAIL route %s: %s is not a service\n", $name, $class);
            $failed = true;
            continue;
        }
        printf("ok   route %s -> %s\n", $name, $controller);
    }
} catch (Throwable $e) {
    printf("FAIL routes.yml: %s: %s\n", get_class($e), $e->getMessage());
    $failed = true;
}

exit($failed ? 1 : 0);
