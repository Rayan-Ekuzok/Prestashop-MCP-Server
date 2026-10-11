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
 * somcp_get_order - everything about one order.
 *
 * It takes an identifier or a reference and nothing else. Giving it the filters
 * of the search tool would let an assistant ask for "Dupont's March orders" and
 * leave the module to pick one of twelve - so finding is one tool and reading is
 * another, and an assistant that is unsure searches first.
 *
 * Read through the core objects here, not through a query: one order costs
 * little to load, and Order, Customer and Address already know how to assemble
 * what a merchant would see in the back office.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SomcpToolGetOrder
{
    /**
     * @return SomcpTool
     */
    public static function define(): SomcpTool
    {
        return new SomcpTool(
            'somcp_get_order',
            Somcp::t('Get an order'),
            'Returns everything about one order of this shop: the customer and how to reach them, '
                . 'both addresses, every product line with its quantity and prices, discounts, '
                . 'carrier and shipping, payments received, the current status and how it got '
                . 'there, the invoices and credit slips it produced, the messages exchanged, and '
                . 'the totals. Identify the order by its identifier or its reference. '
                . 'Do not use it to look for orders - somcp_search_orders finds them, and this '
                . 'tool reads one you have already identified.',
            [
                'type' => 'object',
                'properties' => [
                    'id_order' => [
                        'type' => 'integer',
                        'description' => 'Identifier of the order to read.',
                    ],
                    'reference' => [
                        'type' => 'string',
                        'description' => 'Reference of the order to read, used when no identifier is known.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            [SomcpScope::ORDERS_READ],
            [self::class, 'handle'],
            SomcpTool::acts(SomcpPermission::VIEW, 'AdminOrders')
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public static function handle(array $arguments, array $context): array
    {
        $order = self::locate($arguments);
        if ($order === null) {
            return SomcpTool::text('No order of this shop matches what was given.', true);
        }

        $idLang = (int) $order->id_lang;
        $currency = new Currency((int) $order->id_currency);
        $customer = new Customer((int) $order->id_customer);

        return SomcpTool::json([
            'order' => [
                'id_order' => (int) $order->id,
                'reference' => (string) $order->reference,
                'date_add' => (string) $order->date_add,
                'valid' => (bool) $order->valid,
                'currency' => (string) $currency->iso_code,
                'language_id' => $idLang,
                'payment_method' => (string) $order->payment,
                'status' => self::status($order, $idLang),
            ],
            'customer' => self::customer($customer),
            'addresses' => [
                'delivery' => self::address((int) $order->id_address_delivery, $idLang),
                'invoice' => self::address((int) $order->id_address_invoice, $idLang),
            ],
            'products' => self::products($order),
            'discounts' => self::discounts($order),
            'shipping' => self::shipping($order, $idLang),
            'totals' => self::totals($order),
            'payments' => self::payments($order),
            'history' => self::history($order, $idLang),
            'documents' => self::documents($order),
            'messages' => self::messages($order),
        ]);
    }

    /**
     * Finds the order, and only within the shop the token belongs to.
     *
     * @param array<string, mixed> $arguments
     *
     * @return Order|null
     */
    private static function locate(array $arguments): ?Order
    {
        $idShop = Somcp::currentShopId();
        $idOrder = (int) ($arguments['id_order'] ?? 0);

        if ($idOrder <= 0 && !empty($arguments['reference'])) {
            $idOrder = (int) Db::getInstance()->getValue(
                'SELECT `id_order` FROM `' . _DB_PREFIX_ . "orders`
                 WHERE `reference` = '" . pSQL((string) $arguments['reference']) . "'
                   AND `id_shop` = " . $idShop . '
                 ORDER BY `id_order` DESC'
            );
        }

        if ($idOrder <= 0) {
            return null;
        }

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order) || (int) $order->id_shop !== $idShop) {
            return null;
        }

        return $order;
    }

    /**
     * @param Order $order
     * @param int $idLang
     *
     * @return array<string, mixed>
     */
    private static function status(Order $order, int $idLang): array
    {
        $state = new OrderState((int) $order->current_state, $idLang);

        return [
            'id' => (int) $order->current_state,
            'name' => Validate::isLoadedObject($state) ? (string) $state->name : '',
            'paid' => Validate::isLoadedObject($state) ? (bool) $state->paid : false,
            'shipped' => Validate::isLoadedObject($state) ? (bool) $state->shipped : false,
        ];
    }

    /**
     * @param Customer $customer
     *
     * @return array<string, mixed>
     */
    private static function customer(Customer $customer): array
    {
        if (!Validate::isLoadedObject($customer)) {
            // A guest order, or an account deleted since.
            return ['id_customer' => 0];
        }

        return [
            'id_customer' => (int) $customer->id,
            'firstname' => (string) $customer->firstname,
            'lastname' => (string) $customer->lastname,
            'email' => (string) $customer->email,
            'company' => (string) $customer->company,
            'is_guest' => (bool) $customer->is_guest,
            'newsletter' => (bool) $customer->newsletter,
            'date_add' => (string) $customer->date_add,
        ];
    }

    /**
     * @param int $idAddress
     * @param int $idLang
     *
     * @return array<string, mixed>
     */
    private static function address(int $idAddress, int $idLang): array
    {
        $address = new Address($idAddress);
        if (!Validate::isLoadedObject($address)) {
            return [];
        }

        $country = new Country((int) $address->id_country, $idLang);

        return [
            'firstname' => (string) $address->firstname,
            'lastname' => (string) $address->lastname,
            'company' => (string) $address->company,
            'address1' => (string) $address->address1,
            'address2' => (string) $address->address2,
            'postcode' => (string) $address->postcode,
            'city' => (string) $address->city,
            'country' => Validate::isLoadedObject($country) ? (string) $country->name : '',
            'phone' => (string) $address->phone,
            'phone_mobile' => (string) $address->phone_mobile,
            'vat_number' => (string) $address->vat_number,
            'other' => (string) $address->other,
        ];
    }

    /**
     * @param Order $order
     *
     * @return array<int, array<string, mixed>>
     */
    private static function products(Order $order): array
    {
        $lines = [];
        foreach ($order->getProducts() as $product) {
            $lines[] = [
                'id_product' => (int) $product['product_id'],
                'id_product_attribute' => (int) $product['product_attribute_id'],
                'reference' => (string) $product['product_reference'],
                'name' => (string) $product['product_name'],
                'quantity' => (int) $product['product_quantity'],
                'quantity_refunded' => (int) $product['product_quantity_refunded'],
                'unit_price_tax_excluded' => (float) $product['unit_price_tax_excl'],
                'unit_price_tax_included' => (float) $product['unit_price_tax_incl'],
                'total_tax_excluded' => (float) $product['total_price_tax_excl'],
                'total_tax_included' => (float) $product['total_price_tax_incl'],
            ];
        }

        return $lines;
    }

    /**
     * @param Order $order
     *
     * @return array<int, array<string, mixed>>
     */
    private static function discounts(Order $order): array
    {
        $discounts = [];
        foreach ($order->getCartRules() as $rule) {
            $discounts[] = [
                'name' => (string) $rule['name'],
                'value_tax_included' => (float) $rule['value'],
                'value_tax_excluded' => (float) $rule['value_tax_excl'],
            ];
        }

        return $discounts;
    }

    /**
     * @param Order $order
     * @param int $idLang
     *
     * @return array<string, mixed>
     */
    private static function shipping(Order $order, int $idLang): array
    {
        $carrier = new Carrier((int) $order->id_carrier, $idLang);
        $tracking = [];

        foreach ($order->getShipping() as $shipping) {
            if (!empty($shipping['tracking_number'])) {
                $tracking[] = (string) $shipping['tracking_number'];
            }
        }

        return [
            'carrier' => Validate::isLoadedObject($carrier) ? (string) $carrier->name : '',
            'tracking_numbers' => array_values(array_unique($tracking)),
            'weight' => (float) $order->getTotalWeight(),
            'shipping_tax_excluded' => (float) $order->total_shipping_tax_excl,
            'shipping_tax_included' => (float) $order->total_shipping_tax_incl,
        ];
    }

    /**
     * @param Order $order
     *
     * @return array<string, float>
     */
    private static function totals(Order $order): array
    {
        return [
            'products_tax_excluded' => (float) $order->total_products,
            'products_tax_included' => (float) $order->total_products_wt,
            'discounts_tax_excluded' => (float) $order->total_discounts_tax_excl,
            'discounts_tax_included' => (float) $order->total_discounts_tax_incl,
            'shipping_tax_excluded' => (float) $order->total_shipping_tax_excl,
            'shipping_tax_included' => (float) $order->total_shipping_tax_incl,
            'wrapping_tax_included' => (float) $order->total_wrapping_tax_incl,
            'total_tax_excluded' => (float) $order->total_paid_tax_excl,
            'total_tax_included' => (float) $order->total_paid_tax_incl,
            'total_paid' => (float) $order->total_paid,
            'total_paid_real' => (float) $order->total_paid_real,
        ];
    }

    /**
     * @param Order $order
     *
     * @return array<int, array<string, mixed>>
     */
    private static function payments(Order $order): array
    {
        $payments = [];
        foreach ($order->getOrderPayments() as $payment) {
            $payments[] = [
                'date' => (string) $payment->date_add,
                'amount' => (float) $payment->amount,
                'payment_method' => (string) $payment->payment_method,
                'transaction_id' => (string) $payment->transaction_id,
            ];
        }

        return $payments;
    }

    /**
     * @param Order $order
     * @param int $idLang
     *
     * @return array<int, array<string, mixed>>
     */
    private static function history(Order $order, int $idLang): array
    {
        $history = [];
        foreach ($order->getHistory($idLang) as $step) {
            $history[] = [
                'date' => (string) $step['date_add'],
                'status' => (string) $step['ostate_name'],
                'status_id' => (int) $step['id_order_state'],
                'employee' => trim((string) ($step['employee_firstname'] ?? '') . ' ' . (string) ($step['employee_lastname'] ?? '')),
            ];
        }

        return $history;
    }

    /**
     * @param Order $order
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function documents(Order $order): array
    {
        $invoices = [];
        foreach ($order->getInvoicesCollection() as $invoice) {
            // The collection is declared as holding ObjectModel, so the fields
            // and the formatting method below cannot be resolved from it. The
            // test is what says which class this really is, rather than a
            // docblock asserting it.
            if (!$invoice instanceof OrderInvoice) {
                continue;
            }

            $invoices[] = [
                'number' => (string) $invoice->getInvoiceNumberFormatted((int) $order->id_lang),
                'date' => (string) $invoice->date_add,
                'total_tax_excluded' => (float) $invoice->total_paid_tax_excl,
                'total_tax_included' => (float) $invoice->total_paid_tax_incl,
            ];
        }

        $slips = [];
        foreach (OrderSlip::getOrdersSlip((int) $order->id_customer, (int) $order->id) as $slip) {
            $slips[] = [
                'id_order_slip' => (int) $slip['id_order_slip'],
                'date' => (string) $slip['date_add'],
                'total_tax_excluded' => (float) $slip['total_products_tax_excl'],
                'total_tax_included' => (float) $slip['total_products_tax_incl'],
            ];
        }

        return ['invoices' => $invoices, 'credit_slips' => $slips];
    }

    /**
     * @param Order $order
     *
     * @return array<int, array<string, mixed>>
     */
    private static function messages(Order $order): array
    {
        $messages = [];
        foreach (CustomerMessage::getMessagesByOrderId((int) $order->id, false) as $message) {
            $messages[] = [
                'date' => (string) $message['date_add'],
                'private' => (bool) $message['private'],
                'message' => (string) $message['message'],
            ];
        }

        return $messages;
    }
}
