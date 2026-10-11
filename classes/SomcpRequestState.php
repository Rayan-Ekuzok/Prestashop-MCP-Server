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
 * SomcpRequestState - what the server remembers between two halves of a call,
 * carried by the client rather than stored here.
 *
 * A tool that needs something it was not given answers "input_required" and
 * hands back an opaque string. The client fetches what was asked, calls again,
 * and returns that string untouched. The server rebuilds its context from it
 * and finishes. Nothing is kept between the two: the protocol was designed
 * this way so a stateless server can ask questions, which is what this one is.
 *
 * The string goes through the client, so the specification is blunt about it:
 * servers MUST treat it as attacker-controlled input. Four things are sealed
 * inside and checked on the way back, each closing one way of reusing a state
 * that was not meant for this call:
 *
 * - the employee it was issued to, so another account cannot present it;
 * - the shop, so it cannot cross a multistore boundary;
 * - a short expiry, so a captured state stops working;
 * - a fingerprint of the original call - the tool name and a digest of its
 *   arguments - so a state issued for one question cannot answer another.
 *
 * Single use is not enforced here. The specification is explicit that these
 * measures bound replay without guaranteeing it, and that a state which must
 * be consumed once needs server-side bookkeeping. Where that matters - a file
 * deposit, which is redeemed - the bookkeeping lives with the thing being
 * redeemed, not here.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SomcpRequestState
{
    /** @var int How long a state stays valid, in seconds. */
    public const TTL = 900;

    /** @var int Longest state string accepted, so a huge one is dropped before it is parsed. */
    private const MAX_LENGTH = 8192;

    /**
     * Seals a state for the call being answered.
     *
     * @param array<string, mixed> $payload what the tool needs on the way back
     * @param string $tool name of the tool this state belongs to
     * @param array<string, mixed> $arguments the call's arguments
     * @param int $idEmployee the account behind the token
     *
     * @return string the opaque string to hand the client
     */
    public static function seal(array $payload, string $tool, array $arguments, int $idEmployee): string
    {
        $body = [
            'v' => 1,
            'e' => $idEmployee,
            's' => Somcp::currentShopId(),
            'x' => time() + self::TTL,
            'f' => self::fingerprint($tool, $arguments),
            'd' => $payload,
        ];

        $encoded = json_encode($body);
        if ($encoded === false) {
            return '';
        }

        $packed = SomcpRandom::base64UrlEncode($encoded);

        return $packed . '.' . self::sign($packed);
    }

    /**
     * Opens a state, or refuses it.
     *
     * Every refusal returns null and says nothing about which check failed:
     * telling a caller that the signature was fine but the expiry had passed
     * maps the server for them.
     *
     * @param string $state what the client sent back
     * @param string $tool name of the tool being called now
     * @param array<string, mixed> $arguments the arguments of the call being made now
     * @param int $idEmployee the account behind the token now
     *
     * @return array<string, mixed>|null the payload, or null when the state is not usable here
     */
    public static function open(string $state, string $tool, array $arguments, int $idEmployee): ?array
    {
        if ($state === '' || Tools::strlen($state) > self::MAX_LENGTH) {
            return null;
        }

        $dot = strrpos($state, '.');
        if ($dot === false) {
            return null;
        }

        $packed = Tools::substr($state, 0, $dot);
        $given = Tools::substr($state, $dot + 1);

        // Constant time, and before anything is decoded: a state that does not
        // verify is not worth parsing.
        if (!SomcpRandom::equals(self::sign($packed), $given)) {
            return self::refuse('signature', $idEmployee);
        }

        $body = json_decode((string) base64_decode(strtr($packed, '-_', '+/'), true), true);
        if (!is_array($body) || ($body['v'] ?? 0) !== 1) {
            return self::refuse('shape', $idEmployee);
        }

        if ((int) ($body['e'] ?? 0) !== $idEmployee) {
            return self::refuse('another account', $idEmployee);
        }
        if ((int) ($body['s'] ?? 0) !== Somcp::currentShopId()) {
            return self::refuse('another shop', $idEmployee);
        }
        if ((int) ($body['x'] ?? 0) < time()) {
            return self::refuse('expired', $idEmployee);
        }
        if (!SomcpRandom::equals((string) ($body['f'] ?? ''), self::fingerprint($tool, $arguments))) {
            return self::refuse('another call', $idEmployee);
        }

        return is_array($body['d'] ?? null) ? $body['d'] : [];
    }

    /**
     * What identifies the call a state belongs to.
     *
     * The tool name and a digest of its arguments. The specification has the
     * client replay the same call - same name, same arguments, a new JSON-RPC
     * id - and put what it fetched in `inputResponses`, beside the arguments
     * rather than inside them. So the two halves fingerprint identically, and a
     * state issued for one question cannot be presented on another.
     *
     * @param string $tool
     * @param array<string, mixed> $arguments
     *
     * @return string
     */
    private static function fingerprint(string $tool, array $arguments): string
    {
        ksort($arguments);

        return SomcpRandom::hash($tool . '|' . (string) json_encode($arguments));
    }

    /**
     * @param string $packed
     *
     * @return string
     */
    private static function sign(string $packed): string
    {
        return SomcpRandom::base64UrlEncode(hash_hmac('sha256', $packed, self::key(), true));
    }

    /**
     * The key states are signed with, minted on first use.
     *
     * Its own key rather than the shop's: a secret used for one purpose can be
     * rotated for that purpose. Written at the all-shops level, like every
     * other setting the module seeds.
     *
     * @return string
     */
    private static function key(): string
    {
        $key = (string) Configuration::get(SomcpConfig::STATE_KEY);
        if ($key === '') {
            $key = SomcpRandom::token(32);
            Configuration::updateValue(SomcpConfig::STATE_KEY, $key, false, 0, 0);
        }

        return $key;
    }

    /**
     * @param string $why
     * @param int $idEmployee
     *
     * @return null
     */
    private static function refuse(string $why, int $idEmployee)
    {
        SomcpLogger::log('request_state_refused', SomcpLogger::OUTCOME_FAILURE, $why, '', $idEmployee);

        return null;
    }
}
