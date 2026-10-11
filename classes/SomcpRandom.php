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
 * SomcpRandom - every secret the module hands out is minted here.
 *
 * Nothing the module stores can be turned back into a token: codes and tokens
 * go to the database as a SHA-256 digest and are looked up by that digest. A
 * database dump therefore gives an attacker nothing to present at the endpoint.
 * No salt and no slow hash: these are 256-bit random strings, not passwords -
 * there is no dictionary to run against them, and lookups happen on every call.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SomcpRandom
{
    /** @var int Bytes of entropy behind each token. 32 bytes = 256 bits. */
    private const TOKEN_BYTES = 32;

    /**
     * A URL-safe random string, base64url encoded without padding.
     *
     * @param int $bytes
     *
     * @return string
     */
    public static function token(int $bytes = self::TOKEN_BYTES): string
    {
        return self::base64UrlEncode(random_bytes($bytes));
    }

    /**
     * The value stored for a token. Used both to write and to look up.
     *
     * @param string $value
     *
     * @return string 64 hexadecimal characters
     */
    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    /**
     * Constant-time comparison of two secrets.
     *
     * @param string $known
     * @param string $given
     *
     * @return bool
     */
    public static function equals(string $known, string $given): bool
    {
        return hash_equals($known, $given);
    }

    /**
     * Identifier of a grant family: the code and all the tokens descended from
     * it share one, so revoking a stolen refresh token takes the whole branch.
     *
     * @return string
     */
    public static function familyId(): string
    {
        return self::base64UrlEncode(random_bytes(16));
    }

    /**
     * PKCE verification. Only S256 is accepted: OAuth 2.1 forbids `plain` for
     * public clients, and every client the module targets is public.
     *
     * @param string $verifier
     * @param string $challenge
     *
     * @return bool
     */
    public static function verifyPkce(string $verifier, string $challenge): bool
    {
        if (!self::isValidVerifier($verifier)) {
            return false;
        }

        return hash_equals($challenge, self::base64UrlEncode(hash('sha256', $verifier, true)));
    }

    /**
     * The verifier grammar of RFC 7636 section 4.1.
     *
     * @param string $verifier
     *
     * @return bool
     */
    public static function isValidVerifier(string $verifier): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier);
    }

    /**
     * A challenge is a base64url SHA-256 digest: 43 characters, unpadded.
     *
     * @param string $challenge
     *
     * @return bool
     */
    public static function isValidChallenge(string $challenge): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $challenge);
    }

    /**
     * @param string $binary
     *
     * @return string
     */
    public static function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
