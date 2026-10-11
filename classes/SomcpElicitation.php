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
 * SomcpElicitation - asking the merchant something in the middle of a call.
 *
 * Two modes, and the difference is not cosmetic.
 *
 * A form is answered inside the assistant: the client draws the fields and
 * sends back what was typed, so the answer passes through it. Good for a
 * price, a category, a yes or no. The protocol restricts it to flat objects of
 * primitive fields, which is why nothing here accepts a nested schema - a file
 * cannot travel this way at all.
 *
 * A URL is answered outside it: the client asks the merchant's permission and
 * opens a page, and what happens there never reaches the assistant. That is
 * the only way to hand the shop a file, and the only way to ask for anything
 * the assistant has no business seeing.
 *
 * The protocol forbids sending either to a client that has not said it
 * understands it, so every builder here is paired with a check on what the
 * client declared for this request.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SomcpElicitation
{
    /** @var string */
    public const MODE_FORM = 'form';

    /** @var string */
    public const MODE_URL = 'url';

    /** @var array<int, string> The only field types a form may carry. */
    private const PRIMITIVES = ['string', 'number', 'integer', 'boolean'];

    /**
     * A question answered inside the assistant.
     *
     * @param string $message why the merchant is being asked, in their language
     * @param array<string, array<string, mixed>> $fields name to JSON Schema of a primitive
     * @param array<int, string> $required names that must come back
     *
     * @return array<string, mixed> an elicitation/create request
     */
    public static function form(string $message, array $fields, array $required = []): array
    {
        $properties = [];
        foreach ($fields as $name => $schema) {
            // Anything but a flat primitive is dropped rather than sent: the
            // protocol says clients may refuse the whole request over it, and
            // losing one field beats losing the question.
            if (self::isPrimitive($schema)) {
                $properties[$name] = $schema;
            }
        }

        return [
            'method' => 'elicitation/create',
            'params' => [
                'mode' => self::MODE_FORM,
                'message' => $message,
                'requestedSchema' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => array_values(array_intersect($required, array_keys($properties))),
                ],
            ],
        ];
    }

    /**
     * A question answered on a page of the shop, outside the assistant.
     *
     * The address carries nothing about the merchant and grants nothing on its
     * own: the specification forbids both, and the page checks who opened it
     * against who asked.
     *
     * @param string $message
     * @param string $url
     *
     * @return array<string, mixed>
     */
    public static function url(string $message, string $url): array
    {
        return [
            'method' => 'elicitation/create',
            'params' => [
                'mode' => self::MODE_URL,
                'message' => $message,
                'url' => $url,
            ],
        ];
    }

    /**
     * Whether the client said, on this request, that it understands a mode.
     *
     * An empty elicitation object means form only - the protocol says so, for
     * clients written before URL mode existed.
     *
     * @param array<string, mixed> $capabilities what the client declared
     * @param string $mode
     *
     * @return bool
     */
    public static function supported(array $capabilities, string $mode): bool
    {
        if (!isset($capabilities['elicitation']) || !is_array($capabilities['elicitation'])) {
            return false;
        }

        $declared = $capabilities['elicitation'];
        if ($declared === []) {
            return $mode === self::MODE_FORM;
        }

        return isset($declared[$mode]);
    }

    /**
     * The answer a client gave to one of our questions, if it accepted.
     *
     * Declining and cancelling are different acts - one is a refusal, the
     * other a dismissal - but neither carries content, and a tool has nothing
     * more to do with either.
     *
     * @param array<string, mixed> $responses the inputResponses map
     * @param string $key the identifier the question was filed under
     *
     * @return array<string, mixed>|null the content, or null when there is none
     */
    public static function accepted(array $responses, string $key): ?array
    {
        $answer = $responses[$key] ?? null;
        if (!is_array($answer) || ($answer['action'] ?? '') !== 'accept') {
            return null;
        }

        return is_array($answer['content'] ?? null) ? $answer['content'] : [];
    }

    /**
     * @param mixed $schema
     *
     * @return bool
     */
    private static function isPrimitive($schema): bool
    {
        if (!is_array($schema)) {
            return false;
        }

        $type = (string) ($schema['type'] ?? '');
        if (in_array($type, self::PRIMITIVES, true)) {
            return true;
        }

        // A multi-select is the one array the protocol allows, and only over
        // an enumeration.
        return $type === 'array'
            && is_array($schema['items'] ?? null)
            && (isset($schema['items']['enum']) || isset($schema['items']['anyOf']));
    }
}
