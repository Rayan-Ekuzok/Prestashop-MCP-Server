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
 * SomcpMetadata - the two documents a client reads before it can connect.
 *
 * The protected resource document says which authorization server guards this
 * MCP endpoint; the authorization server document says where its endpoints are
 * and what it supports. Both are built from SomcpConfig so they cannot drift
 * from the URLs that actually answer.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SomcpMetadata
{
    /**
     * Protected resource metadata, RFC 9728.
     *
     * @return array<string, mixed>
     */
    public static function protectedResource(): array
    {
        return [
            'resource' => SomcpConfig::resourceUri(),
            'authorization_servers' => [SomcpConfig::issuer()],
            'scopes_supported' => SomcpScope::supported(),
            'bearer_methods_supported' => ['header'],
            'resource_name' => (string) Configuration::get('PS_SHOP_NAME'),
            'resource_documentation' => 'https://novalo.fr',
        ];
    }

    /**
     * Authorization server metadata, RFC 8414.
     *
     * `client_id_metadata_document_supported` is what tells a modern client it
     * can identify itself with the document it publishes instead of registering.
     * The registration endpoint is only advertised while dynamic registration is
     * enabled: announcing an endpoint that answers 403 sends clients down a dead
     * end rather than to the mechanism that works.
     *
     * @return array<string, mixed>
     */
    public static function authorizationServer(): array
    {
        $metadata = [
            'issuer' => SomcpConfig::issuer(),
            'authorization_endpoint' => SomcpConfig::authorizationEndpoint(),
            'token_endpoint' => SomcpConfig::oauthEndpoint('token'),
            'revocation_endpoint' => SomcpConfig::oauthEndpoint('revoke'),
            'scopes_supported' => SomcpScope::supported(),
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
            'revocation_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
            'code_challenge_methods_supported' => ['S256'],
            'authorization_response_iss_parameter_supported' => true,
            'client_id_metadata_document_supported' => SomcpConfig::isOn(SomcpConfig::CIMD_ENABLED),
            'service_documentation' => 'https://novalo.fr',
        ];

        if (SomcpConfig::isOn(SomcpConfig::DCR_ENABLED)) {
            $metadata['registration_endpoint'] = SomcpConfig::oauthEndpoint('register');
        }

        return $metadata;
    }
}
