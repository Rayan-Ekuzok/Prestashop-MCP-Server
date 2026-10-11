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

namespace Novalo\Somcp\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;

// The validation checklist asks every PHP file of a module to refuse to run
// outside PrestaShop. It sits after the imports rather than at the very top
// because a namespace and its use statements have to come first, and this
// class is reached through Symfony's autoloader, not by being included.
if (!defined('_PS_VERSION_')) {
    exit;
}

/*
 * The door into the back office, on PrestaShop 9.
 *
 * An assistant sends the merchant to the shop front, which parks the
 * authorization request and forwards here. This class does one thing: it is a
 * route PrestaShop authenticates natively, and from inside that authenticated
 * request it builds the address of the consent screen with the two tokens that
 * address needs, then redirects.
 *
 * Why it exists at all. Every legacy back office page of PrestaShop 9 is served
 * by one route, admin_legacy_controller_route, and TokenizedUrlsListener
 * demands a Symfony CSRF token on any admin route whose name does not begin
 * with "_" or "api_". That token belongs to the back office session, so a front
 * controller cannot produce one - and an employee who was already signed in was
 * met with the "token compromised" warning in the middle of an authorization
 * flow. An employee who was not signed in never saw it, because PrestaShop
 * regenerates a tokenized address for them on the way back from the login form.
 *
 * So the entry point moves to a route of our own, named with the api_ prefix
 * the listener leaves alone, and carrying no condition - a route condition is
 * evaluated against a request Symfony builds from the path alone when the shop
 * context is worked out, which on 9.1.0 turns a missed match into a server
 * error. The firewall still applies: an employee who is not signed in is sent
 * to the usual login form and comes back here afterwards. Nothing about the
 * back office security is switched off; the module simply stops entering
 * through a door that was never meant for it.
 *
 * PrestaShop 8 keeps the legacy address. Its own listener lets those through,
 * its legacy pages never reach the Symfony router, and its firewall leaves
 * authentication to each controller - so a route here would have to
 * reimplement what the legacy controller already does correctly.
 */
class ConsentEntryController
{
    /**
     * Sends the signed-in employee to the consent screen.
     *
     * @return RedirectResponse
     */
    public function __invoke(): RedirectResponse
    {
        // The module class is not covered by the autoloader: PrestaShop loads a
        // module's main file itself, and this controller runs before anything
        // has asked it to.
        $module = \Module::getInstanceByName('somcp');
        if (!$module instanceof \Somcp) {
            return new RedirectResponse(\Tools::getShopDomainSsl(true));
        }

        \Somcp::bootClasses();

        $employee = \Somcp::legacyContext()->employee;
        $idEmployee = ($employee && $employee->id) ? (int) $employee->id : 0;

        // The firewall guarantees an employee here. Should that ever stop being
        // true, the settings page is a safe place to land - never the consent
        // screen, which would then be reached without anyone being identified.
        if ($idEmployee <= 0) {
            return new RedirectResponse(\SomcpBackOffice::adminBaseUrl());
        }

        return new RedirectResponse(\SomcpBackOffice::legacyConsentUrl(
            $idEmployee,
            \SomcpBackOffice::symfonyToken()
        ));
    }
}
