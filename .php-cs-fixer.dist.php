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

// The PrestaShop coding standard, from prestashop/php-dev-tools.
// Run: dev/tools/vendor/bin/php-cs-fixer fix --dry-run --diff
// The license header sits right under <?php, as in PrestaShop's own modules.
// php-dev-tools turned this rule off after its last release (v5), and its
// Config returns a fixed rule list, so the rule is turned off in a subclass
// until the next release ships it.
$config = new class extends PrestaShop\CodingStandards\CsFixer\Config {
    public function getRules(): array
    {
        return array_merge(parent::getRules(), ['blank_line_after_opening_tag' => false]);
    }
};

/** @var Symfony\Component\Finder\Finder $finder */
$finder = $config->setUsingCache(true)->getFinder();
$finder->in(__DIR__)->exclude(['vendor', 'dev']);

return $config;
