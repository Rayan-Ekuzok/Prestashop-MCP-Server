<?php
/**
 * Example module for O'So MCP Server.
 *
 * Publishes one tool and one prompt through somcp without naming a single class
 * of somcp: everything goes through two hooks that return arrays. Copy this
 * folder to modules/mymodule, install it next to somcp, and both appear in the
 * assistant.
 *
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License v3.0 (AFL-3.0)
 */
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

class Mymodule extends Module
{
    public function __construct()
    {
        $this->name = 'mymodule';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'You';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '9.99.99'];

        parent::__construct();

        $this->displayName = $this->trans('My module', [], 'Modules.Mymodule.Admin');
        $this->description = $this->trans('Example of a module publishing a tool and a prompt to O\'So MCP Server.', [], 'Modules.Mymodule.Admin');
    }

    public function install(): bool
    {
        // registerHook() creates a hook that does not exist yet, so this works
        // whether somcp is installed before or after this module.
        return parent::install()
            && $this->registerHook('actionSomcpRegisterTools')
            && $this->registerHook('actionSomcpRegisterPrompts');
    }

    /**
     * The tools this module publishes, and the permission they need.
     *
     * Returned, never registered by calling into somcp: the PrestaShop Addons
     * validator analyses this module on its own, and a class it cannot find is
     * a rejection even when the line only runs with somcp installed.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function hookActionSomcpRegisterTools(array $params): array
    {
        return [
            // Short name => label. The label is what the merchant reads on the
            // consent screen, so it is translated.
            'scopes' => [
                'customers.read' => $this->trans('Count the customers of the shop', [], 'Modules.Mymodule.Admin'),
            ],
            'tools' => [
                [
                    'name' => 'count_customers',
                    'title' => $this->trans('Count customers', [], 'Modules.Mymodule.Admin'),
                    // Read by the assistant, not shown to the merchant: plain
                    // English, and no translation.
                    'description' => 'Counts the customer accounts of this shop, optionally only those created '
                        . 'since a date. Use it for "how many customers do I have". Do not use it to find '
                        . 'or read a customer. Never guess the date: if the merchant gave none, leave it out.',
                    'input' => [
                        'type' => 'object',
                        'properties' => [
                            'since' => [
                                'type' => 'string',
                                'format' => 'date',
                                'description' => 'Only count accounts created on or after this day, YYYY-MM-DD.',
                            ],
                        ],
                        'additionalProperties' => false,
                    ],
                    'scopes' => ['customers.read'],
                    // The page where a merchant would see the same thing by
                    // hand. "view" also tells the assistant the call only reads.
                    'permission' => ['view', 'AdminCustomers'],
                    'handler' => [$this, 'somcpCountCustomers'],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $args what the assistant sent
     * @param array<string, mixed> $context id_shop, id_employee, id_lang
     *
     * @return array<string, mixed>
     */
    public function somcpCountCustomers(array $args, array $context): array
    {
        $since = (string) ($args['since'] ?? '');
        if ($since !== '' && !Validate::isDate($since)) {
            return ['text' => 'The date must be written YYYY-MM-DD.', 'error' => true];
        }

        // The shop comes from the context, decided by somcp from the token.
        // Never from the arguments: the assistant does not choose the shop.
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'customer`
                WHERE `deleted` = 0 AND `id_shop` = ' . (int) $context['id_shop'];
        if ($since !== '') {
            $sql .= " AND `date_add` >= '" . pSQL($since) . "'";
        }

        return ['json' => [
            'customers' => (int) Db::getInstance()->getValue($sql),
            'since' => $since === '' ? null : $since,
        ]];
    }

    /**
     * The prompts this module publishes.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function hookActionSomcpRegisterPrompts(array $params): array
    {
        return [
            'prompts' => [
                [
                    'name' => 'customer-overview',
                    'title' => $this->trans('Customer overview', [], 'Modules.Mymodule.Admin'),
                    'description' => $this->trans('How many customers the shop has, and how many are new.', [], 'Modules.Mymodule.Admin'),
                    'file' => 'prompts/customer-overview.md',
                    'scopes' => ['customers.read'],
                    'tools' => ['mymodule-count_customers'],
                    'permission' => ['view', 'AdminCustomers'],
                    'arguments' => [
                        [
                            'name' => 'since',
                            'description' => $this->trans('Count new accounts from this day (YYYY-MM-DD)', [], 'Modules.Mymodule.Admin'),
                            'required' => false,
                        ],
                    ],
                ],
            ],
        ];
    }
}
