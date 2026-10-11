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
use Symfony\Component\HttpFoundation\Request;

// The validation checklist asks every PHP file of a module to refuse to run
// outside PrestaShop. It sits after the imports rather than at the very top
// because a namespace and its use statements have to come first, and this
// class is reached through Symfony's autoloader, not by being included.
if (!defined('_PS_VERSION_')) {
    exit;
}

/*
 * The door into the deposit screen, on PrestaShop 9.
 *
 * Identical in purpose to ConsentEntryController, and for the identical reason:
 * TokenizedUrlsListener demands a Symfony token on any admin route whose name
 * does not start with "_" or "api_", and only a request PrestaShop has already
 * authenticated can produce one. An assistant hands the merchant a plain
 * address; this route turns it into one the legacy page will accept.
 *
 * The reference travels through and is not read here. It names a deposit and
 * grants nothing: the page behind checks that whoever arrives is the employee
 * the invitation was opened for, which is the check the specification requires.
 */
class UploadEntryController
{
    /**
     * Sends the signed-in employee to the deposit screen.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function __invoke(Request $request): RedirectResponse
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
        // true, the back office root is a safe place to land - never the
        // deposit screen, which would then be reached without anyone being
        // identified.
        if ($idEmployee <= 0) {
            return new RedirectResponse(\SomcpBackOffice::adminBaseUrl());
        }

        return new RedirectResponse(\SomcpBackOffice::legacyUrl(
            \SomcpBackOffice::UPLOAD_CONTROLLER,
            $idEmployee,
            \SomcpBackOffice::symfonyToken(),
            ['ref' => (string) $request->query->get('ref', '')]
        ));
    }
}
