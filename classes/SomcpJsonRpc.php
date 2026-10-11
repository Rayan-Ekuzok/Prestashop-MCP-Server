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
 * SomcpJsonRpc - the JSON-RPC 2.0 envelope used by the MCP endpoint.
 *
 * The -3202x codes are MCP's own additions from the 2026-07-28 revision; the
 * others are plain JSON-RPC. A client uses them to tell "this server does not
 * know that version" from "this server is broken", so returning the right one
 * decides whether it retries or gives up.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SomcpJsonRpc
{
    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;

    /** @var int Transport header and request body disagree. */
    public const HEADER_MISMATCH = -32020;

    /** @var int The client lacks a capability the operation needs. */
    public const MISSING_CAPABILITY = -32021;

    /** @var int The protocol version the request declares is not implemented. */
    public const UNSUPPORTED_VERSION = -32022;

    /**
     * @param mixed $id
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    public static function result($id, array $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * @param mixed $id
     * @param int $code
     * @param string $message
     * @param array<string, mixed>|null $data
     *
     * @return array<string, mixed>
     */
    public static function error($id, int $code, string $message, ?array $data = null): array
    {
        $error = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $error['data'] = $data;
        }

        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => $error];
    }

    /**
     * Reads the request body.
     *
     * The key type is deliberately open: a well-formed request decodes to a
     * string-keyed object, but a client sending a batch decodes to a list, and
     * the endpoint has to recognise that to refuse it.
     *
     * @param string $raw
     *
     * @return array<int|string, mixed>|null null when the body is not JSON at all
     */
    public static function decode(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * A notification carries no id and gets no response.
     *
     * @param array<string, mixed> $message
     *
     * @return bool
     */
    public static function isNotification(array $message): bool
    {
        return !array_key_exists('id', $message) || $message['id'] === null;
    }
}
