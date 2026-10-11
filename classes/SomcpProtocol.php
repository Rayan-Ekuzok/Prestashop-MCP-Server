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
 * SomcpProtocol - which revision of MCP the caller speaks.
 *
 * Two eras have to live behind one endpoint. The 2026-07-28 revision dropped
 * the initialize handshake and the session header: a request carries its own
 * version and the client's identity in `_meta`, and the server keeps no state
 * between calls. Everything released before that still opens with `initialize`.
 * Most assistants shipping today are still on the older shape, so refusing it
 * would mean a module nobody can connect to.
 *
 * The eras are kept apart on purpose: a request is handled under one set of
 * rules or the other, never a mixture.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SomcpProtocol
{
    public const ERA_MODERN = 'modern';
    public const ERA_LEGACY = 'legacy';

    /** @var string Revision the module prefers. */
    public const LATEST = '2026-07-28';

    /** @var string Assumed when a client sends no version at all, as the transport specification instructs. */
    public const ASSUMED = '2025-03-26';

    /** @var string Key under which a modern client puts its version inside params._meta. */
    public const META_VERSION = 'io.modelcontextprotocol/protocolVersion';
    public const META_CLIENT_INFO = 'io.modelcontextprotocol/clientInfo';
    public const META_SERVER_INFO = 'io.modelcontextprotocol/serverInfo';

    /**
     * @var string Key under which a modern client states what it can do.
     *
     * There is no handshake left to remember it from, so a client repeats this
     * on every request that might need it. The server may not ask for anything
     * the client has not declared here - the specification says MUST - which is
     * why nothing is assumed when the key is missing.
     */
    public const META_CLIENT_CAPABILITIES = 'io.modelcontextprotocol/clientCapabilities';

    /**
     * @return array<int, string>
     */
    public static function modernVersions(): array
    {
        return [self::LATEST];
    }

    /**
     * @return array<int, string>
     */
    public static function legacyVersions(): array
    {
        return ['2025-11-25', '2025-06-18', '2025-03-26'];
    }

    /**
     * @return array<int, string>
     */
    public static function supportedVersions(): array
    {
        return array_merge(self::modernVersions(), self::legacyVersions());
    }

    /**
     * Works out the version and era of one request.
     *
     * @param array<string, mixed> $message the decoded JSON-RPC message
     *
     * @return array{era:string, version:string, error:string, requested:string}
     */
    public static function negotiate(array $message): array
    {
        $headerVersion = SomcpHttp::header('MCP-Protocol-Version');
        $metaVersion = '';

        if (isset($message['params']['_meta'][self::META_VERSION])) {
            $metaVersion = (string) $message['params']['_meta'][self::META_VERSION];
        }

        if ($headerVersion !== '' && $metaVersion !== '' && $headerVersion !== $metaVersion) {
            // Header and body disagree about which rules apply; guessing which
            // one is right is how a request ends up handled under the wrong era.
            return ['era' => '', 'version' => '', 'error' => 'mismatch', 'requested' => $headerVersion];
        }

        $version = $metaVersion !== '' ? $metaVersion : $headerVersion;
        if ($version === '') {
            $version = self::ASSUMED;
        }

        if (in_array($version, self::modernVersions(), true)) {
            return ['era' => self::ERA_MODERN, 'version' => $version, 'error' => '', 'requested' => $version];
        }
        if (in_array($version, self::legacyVersions(), true)) {
            return ['era' => self::ERA_LEGACY, 'version' => $version, 'error' => '', 'requested' => $version];
        }

        return ['era' => '', 'version' => '', 'error' => 'unsupported', 'requested' => $version];
    }

    /**
     * Checks the routing headers the modern revision requires.
     *
     * `Mcp-Method` lets a proxy or a gateway route and authorize a call without
     * parsing the body, which only holds if the two agree. `Mcp-Name` carries
     * the tool being called; it is compared when both sides state it, and not
     * demanded on methods that target nothing.
     *
     * @param array<string, mixed> $message
     *
     * @return bool true when the headers are consistent with the body
     */
    public static function headersMatchBody(array $message): bool
    {
        $method = (string) ($message['method'] ?? '');
        $headerMethod = SomcpHttp::header('Mcp-Method');
        if ($headerMethod !== '' && $headerMethod !== $method) {
            return false;
        }

        $headerName = SomcpHttp::header('Mcp-Name');
        $bodyName = isset($message['params']['name']) ? (string) $message['params']['name'] : '';
        if ($headerName !== '' && $bodyName !== '' && $headerName !== $bodyName) {
            return false;
        }

        return true;
    }

    /**
     * Name and version of the client, as it declared them.
     *
     * @param array<string, mixed> $message
     *
     * @return string a short label for the log, empty when the client said nothing
     */
    public static function clientLabel(array $message): string
    {
        $info = $message['params']['_meta'][self::META_CLIENT_INFO]
            ?? $message['params']['clientInfo']
            ?? null;

        if (!is_array($info)) {
            return '';
        }

        $name = (string) ($info['name'] ?? '');
        $version = (string) ($info['version'] ?? '');

        return trim($name . ' ' . $version);
    }

    /**
     * What the client said it can do, on this request.
     *
     * Read from the request rather than remembered from a handshake, because in
     * this revision there is none. A client that declares nothing gets asked
     * nothing: an empty array here makes every capability check fail, which is
     * the safe direction - the alternative is sending a question the client
     * cannot answer and leaving the call hanging.
     *
     * @param array<string, mixed> $params the params of the request being handled
     *
     * @return array<string, mixed>
     */
    public static function clientCapabilities(array $params): array
    {
        $declared = $params['_meta'][self::META_CLIENT_CAPABILITIES] ?? null;

        return is_array($declared) ? $declared : [];
    }

    /**
     * Whether the client can answer one question a tool wants to ask.
     *
     * The specification forbids sending a request the client has not declared
     * support for - MUST NOT - so this is checked at the edge, once, rather
     * than trusted to each tool. An unknown method is refused for the same
     * reason: the capability that would cover it is not known either.
     *
     * @param array<string, mixed> $capabilities what the client declared on this request
     * @param array<string, mixed> $request one entry of inputRequests
     *
     * @return bool
     */
    public static function clientCanAnswer(array $capabilities, array $request): bool
    {
        switch ((string) ($request['method'] ?? '')) {
            case 'elicitation/create':
                return SomcpElicitation::supported(
                    $capabilities,
                    (string) ($request['params']['mode'] ?? SomcpElicitation::MODE_FORM)
                );
            case 'sampling/createMessage':
                return isset($capabilities['sampling']);
            case 'roots/list':
                return isset($capabilities['roots']);
            default:
                return false;
        }
    }

    /**
     * Picks the revision to answer an initialize request with.
     *
     * When the client asks for something we know, we answer with that. When it
     * asks for something we do not, the specification says to answer with our
     * own latest legacy revision and let the client decide whether to continue.
     *
     * @param string $requested
     *
     * @return string
     */
    public static function negotiateLegacyVersion(string $requested): string
    {
        if (in_array($requested, self::legacyVersions(), true)) {
            return $requested;
        }

        return self::legacyVersions()[0];
    }
}
