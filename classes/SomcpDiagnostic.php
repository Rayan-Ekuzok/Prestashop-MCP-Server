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
 * SomcpDiagnostic - calls the shop's own URLs and reports what a client finds.
 *
 * Every check here exists because something in a real hosting setup can break
 * it silently. Friendly URLs off, a shop in a subdirectory, an Authorization
 * header dropped by the FastCGI layer: none of these produce an error anywhere,
 * they just make the server invisible or unusable, and the merchant has no way
 * to tell which.
 *
 * The requests go out without the address guard of SomcpHttp, on purpose: a
 * shop behind a load balancer legitimately resolves to a private address, and
 * the URLs being called are its own.
 *
 * Everything below the first two checks is done for one named shop, not for
 * "the current context": on a multistore installation PrestaShop still reports
 * a current shop when the administrator is working on all of them, so testing
 * that one and showing a row of green ticks would claim things about shops that
 * were never called.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SomcpDiagnostic
{
    public const OK = 'ok';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    /**
     * Runs every check.
     *
     * @param Somcp $module used for the translated labels
     *
     * @return array<int, array{status:string, label:string, detail:string, url:string}>
     */
    public static function run(Somcp $module, ?int $idShop = null): array
    {
        $shop = new Shop($idShop > 0 ? $idShop : Somcp::currentShopId());
        if (!Validate::isLoadedObject($shop)) {
            $shop = Somcp::currentShop();
        }

        return [
            self::checkCurl($module),
            self::checkAdminDir($module),
            self::checkShopActive($module, $shop),
            self::checkMaintenance($module, $shop),
            self::checkFriendlyUrls($module, $shop),
            self::checkSubdirectory($module, $shop),
            self::checkAuthorizationServerMetadata($module, $shop),
            self::checkProtectedResourceMetadata($module, $shop),
            self::checkEndpointChallenge($module, $shop),
        ];
    }

    /**
     * A shop switched off in the multistore settings serves nothing at all.
     *
     * @param Somcp $module
     * @param Shop $shop
     *
     * @return array{status:string, label:string, detail:string, url:string}
     */
    private static function checkShopActive(Somcp $module, Shop $shop): array
    {
        $active = (bool) $shop->active;

        return self::line(
            $active ? self::OK : self::ERROR,
            $module->l10n('Shop enabled'),
            $active
                ? $module->l10n('This shop is enabled.')
                : $module->l10n('This shop is disabled in the multistore settings. It serves nothing at all, so no assistant can reach it. Enable it to use the MCP server on this shop.'),
            ''
        );
    }

    /**
     * Maintenance mode answers 503 to everything before the module is reached.
     *
     * @param Somcp $module
     * @param Shop $shop
     *
     * @return array{status:string, label:string, detail:string, url:string}
     */
    private static function checkMaintenance(Somcp $module, Shop $shop): array
    {
        $open = (bool) Configuration::get('PS_SHOP_ENABLE', null, null, (int) $shop->id);

        return self::line(
            $open ? self::OK : self::WARNING,
            $module->l10n('Shop open'),
            $open
                ? $module->l10n('This shop is open.')
                : $module->l10n('This shop is in maintenance. Assistants still connect and work: the MCP address belongs to you, not to your visitors. Turn off the MCP server above if you want it closed too.'),
            ''
        );
    }

    /**
     * @param Somcp $module
     *
     * @return array{status:string, label:string, detail:string, url:string}
     */
    private static function checkCurl(Somcp $module): array
    {
        $available = function_exists('curl_init');

        return self::line(
            $available ? self::OK : self::ERROR,
            $module->l10n('Outgoing HTTP requests'),
            $available
                ? $module->l10n('The cURL extension is available.')
                : $module->l10n('The cURL extension is missing. The module cannot read the metadata document published by a client, so no assistant can connect.'),
            ''
        );
    }

    /**
     * @param Somcp $module
     * @param Shop $shop
     *
     * @return array{status:string, label:string, detail:string, url:string}
     */
    private static function checkFriendlyUrls(Somcp $module, Shop $shop): array
    {
        // Read for this shop: the setting can be on for one and off for another,
        // which is exactly how a merchant ends up with one shop unreachable.
        $enabled = (bool) Configuration::get('PS_REWRITING_SETTINGS', null, null, (int) $shop->id);

        return self::line(
            $enabled ? self::OK : self::ERROR,
            $module->l10n('Friendly URLs'),
            $enabled
                ? $module->l10n('Friendly URLs are enabled.')
                : $module->l10n('Friendly URLs are disabled. PrestaShop then routes nothing outside index.php, so the discovery documents cannot be reached and no assistant can connect. Enable them in Shop parameters, Traffic and SEO.'),
            ''
        );
    }

    /**
     * @param Somcp $module
     * @param Shop $shop
     *
     * @return array{status:string, label:string, detail:string, url:string}
     */
    private static function checkSubdirectory(Somcp $module, Shop $shop): array
    {
        $inSubdirectory = SomcpConfig::isInSubdirectory($shop);

        return self::line(
            $inSubdirectory ? self::ERROR : self::OK,
            $module->l10n('Shop location'),
            $inSubdirectory
                ? $module->l10n('This shop is installed in a subdirectory. The authorization server document has to sit at the root of the domain, which is outside the shop, so PrestaShop cannot serve it. Moving the shop to the root of a domain or a subdomain is the only way around it.')
                : $module->l10n('The shop sits at the root of its domain.'),
            SomcpConfig::shopBaseUrl($shop)
        );
    }

    /**
     * @param Somcp $module
     *
     * @return array{status:string, label:string, detail:string, url:string}
     */
    private static function checkAdminDir(Somcp $module): array
    {
        $known = SomcpBackOffice::adminDir() !== '';

        return self::line(
            $known ? self::OK : self::WARNING,
            $module->l10n('Consent screen'),
            $known
                ? $module->l10n('The consent screen can be reached from the authorization flow.')
                : $module->l10n('The administration folder is not known yet. Save the settings once and this resolves itself.'),
            ''
        );
    }

    /**
     * @param Somcp $module
     * @param Shop $shop
     *
     * @return array{status:string, label:string, detail:string, url:string}
     */
    private static function checkAuthorizationServerMetadata(Somcp $module, Shop $shop): array
    {
        $url = SomcpConfig::asMetadataUrl($shop);
        $response = SomcpHttp::fetch($url, false);

        if ($response['status'] !== 200) {
            return self::line(
                self::ERROR,
                $module->l10n('Authorization server document'),
                $module->l10n(
                    'This address answered %status% instead of 200. Assistants read it to find out where to sign in, so none of them can connect until it works. Some hosting setups answer paths starting with .well-known themselves, before PrestaShop ever sees them; a rewrite rule sending that path to index.php fixes it.',
                    ['%status%' => $response['status'] === 0 ? $response['error'] : (string) $response['status']]
                ),
                $url
            );
        }

        $document = json_decode($response['body'], true);
        if (!is_array($document) || ($document['issuer'] ?? '') !== SomcpConfig::issuer($shop)) {
            return self::line(
                self::ERROR,
                $module->l10n('Authorization server document'),
                $module->l10n('This address answered, but not with the expected document. Something else on the hosting is serving that path.'),
                $url
            );
        }

        return self::line(
            self::OK,
            $module->l10n('Authorization server document'),
            $module->l10n('Served correctly.'),
            $url
        );
    }

    /**
     * @param Somcp $module
     * @param Shop $shop
     *
     * @return array{status:string, label:string, detail:string, url:string}
     */
    private static function checkProtectedResourceMetadata(Somcp $module, Shop $shop): array
    {
        $url = SomcpConfig::prmUrl($shop);
        $response = SomcpHttp::fetch($url, false);
        $document = json_decode($response['body'], true);

        if ($response['status'] !== 200 || !is_array($document)
            || ($document['resource'] ?? '') !== SomcpConfig::resourceUri($shop)) {
            return self::line(
                self::ERROR,
                $module->l10n('Protected resource document'),
                $module->l10n(
                    'This address answered %status% instead of the expected document.',
                    ['%status%' => $response['status'] === 0 ? $response['error'] : (string) $response['status']]
                ),
                $url
            );
        }

        return self::line(self::OK, $module->l10n('Protected resource document'), $module->l10n('Served correctly.'), $url);
    }

    /**
     * Calls the MCP endpoint the way an assistant does before it has a token,
     * and checks two things at once: that the challenge comes back, and that
     * the Authorization header survived the trip to PHP.
     *
     * @param Somcp $module
     * @param Shop $shop
     *
     * @return array{status:string, label:string, detail:string, url:string}
     */
    private static function checkEndpointChallenge(Somcp $module, Shop $shop): array
    {
        $url = SomcpConfig::resourceUri($shop);
        // POST, like a real client: the endpoint answers 405 to anything else,
        // which would make this check fail for the wrong reason.
        $response = SomcpHttp::fetch(
            $url,
            false,
            ['Authorization: Bearer somcp-diagnostic'],
            (string) json_encode(['jsonrpc' => '2.0', 'id' => 'somcp-diagnostic', 'method' => 'ping'])
        );

        if ($response['status'] !== 401) {
            return self::line(
                self::ERROR,
                $module->l10n('MCP endpoint'),
                $module->l10n(
                    'The endpoint answered %status% where an unauthenticated call must answer 401.',
                    ['%status%' => $response['status'] === 0 ? $response['error'] : (string) $response['status']]
                ),
                $url
            );
        }

        if (strpos(Tools::strtolower($response['headers']['www-authenticate'] ?? ''), 'resource_metadata') === false) {
            return self::line(
                self::ERROR,
                $module->l10n('MCP endpoint'),
                $module->l10n('The endpoint answered 401 but without the challenge that tells an assistant where to sign in.'),
                $url
            );
        }

        $body = json_decode($response['body'], true);
        if (is_array($body) && ($body['authorization_received'] ?? null) === false) {
            return self::line(
                self::ERROR,
                $module->l10n('MCP endpoint'),
                $module->l10n('The endpoint answers correctly, but the Authorization header never reached PHP: this hosting drops it. Assistants will always be treated as signed out. Adding a rewrite rule that copies the header into the environment fixes it.'),
                $url
            );
        }

        return self::line(
            self::OK,
            $module->l10n('MCP endpoint'),
            $module->l10n('Answers with the expected challenge, and credentials reach the module.'),
            $url
        );
    }

    /**
     * @param string $status
     * @param string $label
     * @param string $detail
     * @param string $url
     *
     * @return array{status:string, label:string, detail:string, url:string}
     */
    private static function line(string $status, string $label, string $detail, string $url): array
    {
        return ['status' => $status, 'label' => $label, 'detail' => $detail, 'url' => $url];
    }
}
