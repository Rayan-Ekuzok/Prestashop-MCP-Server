# Tools

This file describes the module's MCP tools: what they do, what they require and
what they return. It is written by hand and it is where a tool is decided - a
tool is described here before it is written in `tools/`.

Sections follow **what the data is**, not where PrestaShop put it in its menu. An
order uses a status, it does not contain one, so the two are not in the same
section.

---

## Shared conventions

These hold for every tool. Do not repeat them in a tool's entry.

**Shop.** Everything is bound to the shop the token belongs to. No tool takes a
shop identifier: the boundary comes from the token, never from what the client
asks for.

**Language.** Read in the shop's default language, unless a `language` parameter
gives an ISO code.

**Dates.** ISO 8601 in and out. Relative periods are worked out by the
assistant, which already knows the shop's time zone.

**Amounts.** Always with their currency code, and always with tax or without
said in the field name. Never a bare `total`.

**Paging.** `limit` defaults to 20 and caps at 100, `offset` for the rest. Every
list returns `total_count`, so an assistant knows there is more instead of
concluding from one page.

**Personal data.** A list stays plain. A detail gives everything, because it was
asked for one named object. What leaves goes to the assistant's vendor: that is
the line to hold.

**Reading.** Core methods first. For a list, `DbQuery` or `PrestaShopCollection`
are just as native and avoid loading hundreds of objects.

**Writing.** Always through core objects, never raw SQL, so that hooks fire and
other modules follow. The token's employee is carried on the object being
written, so the record keeps their name rather than an anonymous line - on an
order that is `OrderHistory::$id_employee`, which the core reads before falling
back to the context.

**One object per write.** A write tool changes one thing per call. The back
office can act on a selection, but a batch that half fails cannot be reported
honestly, and a destructive call is confirmed by the client once - one order is
one question, thirty orders is one click. An assistant with several to change
loops.

**Writes are not replayable.** No write tool may be retried on a lost answer.
Each one says so in its own description and names the read tool that tells the
assistant whether the change went through.

**Nature and permission.** `SomcpTool::acts($action, $controller)`: one
declaration, from which the module takes the permission required and what the
assistant is told about the call. `view` is read-only and repeatable; `add`,
`edit` and `delete` ask the merchant first and are never retried.

**Tools from other modules.** Another module publishes its own through the
`actionSomcpRegisterTools` hook, which is fired once per module so the registry
knows who it is serving. The name that module chose is prefixed with its folder
- `mymodule-get_tracking` - and it cannot opt out: the prefix is what stops
two modules that both thought of `get_tracking` from cancelling each other out,
and what stops a third party from publishing `somcp_get_order`. A name over 64
characters is refused rather than cut, so two tools can never land on the same
stub.

**Permissions from other modules.** A module publishing tools of its own
declares the permission that goes with them, in the same hook as the tools
that need it. Written against the array contract, which is the way to do it
(see [EXTENDING.md](EXTENDING.md)):

```php
return [
    'scopes' => ['tickets.read' => $this->trans('Read support tickets', [], 'Modules.Mymodule.Admin')],
    'tools' => [/* ... */],
];
```

It comes back prefixed - `mcp.mymodule.tickets.read` - like a tool name and for
the same reason. The label is what the merchant reads on the consent screen, so
the module passes it already translated: it knows what its tools do and this one
does not.

Borrowing one of ours instead would be lying to the merchant. Someone who let an
assistant read their orders has not let it read their customers' complaints, and
the consent screen has to be able to ask the two questions apart. The back office
permission follows the same rule: a tool declares the page where the merchant
would do the same thing by hand, which for another module's feature is that
module's own page, not `AdminOrders`.

A tool asking for a scope nobody declared is **refused at registration** and the
refusal written to the log. It used to be accepted and then unreachable forever
- never offered at consent, so never in a token, so never listed - which is the
worst of both.

**Reaching one client's own feature.** A tool may carry `_meta`, the place the
protocol keeps for what it has not defined itself - for instance
`openai/fileParams`, without which ChatGPT never fills a file parameter. A
client that does not know a key ignores it, so a key meant for one assistant
costs the others nothing.

The module publishes nothing there on its own; it carries what a tool declares,
as the eighth argument of the constructor. Two keys are refused and logged: one
that does not match the protocol's key format, and one whose prefix is reserved
for the protocol - a module writing under `io.modelcontextprotocol/` or
`dev.mcp/` would be putting words in a client's mouth from inside a tool
description.

**Prompts.** A tool returns data; a prompt is a guided task the merchant
starts from the assistant's menu, and it hands the assistant written
instructions for using the tools. They live one folder per prompt under
`prompts/`, each with a `prompt.md` and a `prompt.json` giving the title shown
in the menu, the description, the arguments the merchant may fill in, the
scopes and back office permission it needs, and the tools it talks about.
Adding a prompt is adding a folder.

They replaced skills. The Skills extension was implemented and no assistant
tested ever read it; putting the same text in the server instructions would
have charged every conversation for it. A prompt costs nothing until the
merchant picks it. What it gives up is initiative: it never starts on its own.

A prompt is filtered exactly like the tools it describes, and for the same
reason: guidance on changing an order status is a promise the shop will refuse
to keep for an account that may not change one. The same filters are applied
again on `prompts/get`, so one switched off this morning cannot be fetched from
a listing a client kept.

What the merchant types goes below the guide under its own heading, line
breaks removed, never spliced into the text: the guide is written by a module
author, the values by a person, and mixing the two lets a value rewrite the
instructions around it.

Another module publishes its own by returning them from
`actionSomcpRegisterPrompts`, as arrays - see `SomcpContract`. The file must
sit inside that module's own folder.

**Switching a tool off.** The merchant can switch off any tool from the module
settings, per shop, with the usual inheritance. What is stored is the list of
tools that are off, not the ones that are on: a tool added by an update works
without anyone going to allow it, and one switched off stays off even if the
module publishing it is reinstalled. A tool that is off is left out of the
listing, refused if called anyway, and stops making its scope worth granting.

**Shop, and the employee behind the token.** Everything is bound to the shop
the token belongs to, and the account is checked on every call rather than once
when the token was issued: an account disabled this morning stops working this
morning.

On a multistore installation the account must also be **attached to that
shop**. Back office permissions belong to the profile and are identical
everywhere, so without this an employee of one store could point an assistant
at another store's address and be handed its orders. The check is
`Employee::hasAuthOnShop()`, PrestaShop's own, and a super administrator passes
everywhere. It applies at the consent screen, on every tool call and on the
deposit page; on a single-shop installation it is skipped entirely.

**The shape of `inputSchema`.** `tools/call` sends named arguments, so the root
is always an object. Declared the natural PHP way, an empty `properties => []`
serialises to `[]` and a client refuses **the whole tools/list answer** - every
module's tools disappear at once over one module's habit.

somcp therefore repairs what it can at registration: a missing `type`, and an
empty array wherever an object belongs, at the root and one level down. What it
cannot repair - a root that is not an object, arguments with no names - refuses
that one tool and says which and why in the journal, as `tool_schema_refused`.
The other modules keep theirs.

Declare a parameterless tool like this:

```php
[
    'type' => 'object',
    'properties' => new stdClass(),
    'additionalProperties' => false,
]
```

**Asking the merchant something mid-call.** A tool that is missing something
does not fail and does not guess: it returns `SomcpTool::needsInput()` with a
question built by `SomcpElicitation`, and the endpoint turns that into the
`input_required` result of the 2026-07-28 revision. The client fetches the
answer, replays the same call, and the tool reads what it asked for under
`resumed` in its context. Nothing is kept on the server between the two halves -
the state travels through the client, sealed, and is refused unless it comes
back on the same tool, the same arguments, the same employee, the same shop and
within fifteen minutes.

Two limits, both from the protocol. It exists in the modern revision only, and a
question is never sent to a client that has not declared it can answer that kind
- the specification says MUST NOT. When either is true the merchant is told, in
the tool's answer, that the assistant cannot carry the question.

**Getting a file from the merchant.** There is no attachment channel: a tool
argument is JSON, so a file put there is bytes the model writes out one token at
a time. `SomcpUpload::request()` instead opens a deposit and asks the client to
open a page of the merchant's own back office; `SomcpUpload::take()` reads the
file back on the second half of the call. The bytes never pass through the
assistant. One file or several, the extension checked against the actual
bytes, a name of the module's own on disk, and the row deleted as the file is
handed over - so a reference works exactly once. The page refuses anyone but the
employee the request was opened for, which is the identity check a URL-mode
elicitation requires.

The tool says what it wants: which extensions (or a shorthand - `image`,
`document`, `data`, `archive`), how many files, how large each may be. The
module's own limits are an outer edge - 20 files, 64 MiB - and the real
ceiling is read from `upload_max_filesize` and `post_max_size`, because beyond
those PHP truncates without telling anybody. What a tool cannot do is widen the
list of formats that exist at all: that list is closed, every entry says how
its bytes are verified, and an upload endpoint in a back office that accepts
any extension is the oldest hole there is.

Which of two paths is taken depends on the client, not on the tool. One that
declared it can open an address gets the multi-round question. One that did not
is handed the address and a reference as plain text; the merchant deposits,
says so, and the assistant calls the same tool again with `upload_ref`.

Declaring is not the same as doing. In production a client declared that it
could open an address, took the question, never showed the page and replayed
the call at once. So a call that comes back to a deposit that is still open
and empty is answered with the same address as text, whichever path it took,
and a reference that leads nowhere - invented, expired, already used - is
treated as a first call and opens a new deposit. A tool written against the
array contract (see EXTENDING.md) gets all of this without doing anything; one
written against the objects declares `upload_ref` as an optional string and
calls `request()` and `take()` itself.

The bytes are written under `var/somcp/uploads/`, not inside the module. A
module folder has to stay partly servable - its scripts, styles and images are
fetched over HTTP - so a deposit kept there relies on a rule nobody wrote for
it; the `.htaccess` the module writes covers Apache and is ignored by nginx.
Nothing under `var/` is linked to, and the configurations that deny a directory
outright already deny that one.

---

## Entry template

Copy this for every tool.

### `tool_name`
**State**: draft · to write · written · tested
**Nature**: `acts(VIEW, 'AdminXxx')` · **Scope**: `mcp.xxx.read`

**Does** - one sentence.

**Do not use for** - the cases where the assistant should reach for another
tool, named.

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| | | | |

**Returns** - the fields, and only those.

**Notes** - multistore, language, side effects, known traps.

---

## Sections

An entry marked **draft** is not open yet: the maintainer still has to approve
its design, and its issue carries the `spec pending` label. Do not start it;
comment on the issue if you have something to say about the design.

An entry marked **to write** is approved and is the specification: pick its
issue, write the tool to match, and change the state in the same pull request.
If something in the entry turns out wrong while writing it, say so in the issue
first.

The scopes of entries marked draft or to write do not exist yet. The first tool of a
section adds its scopes to `SomcpScope`, with the label the merchant reads on
the consent screen, in the four languages.

### Orders

The order and what belongs to it: its lines, its history, its messages, its
documents, its addresses.

**Not here**: order statuses (→ Configuration), invoices and credit slips
(→ Billing), carts that never became an order (→ Carts), customers
(→ Customers).

#### `somcp_search_orders`
**State**: written
**Nature**: `acts(VIEW, 'AdminOrders')` · **Scope**: `mcp.orders.read`

**Does** - finds orders by reference, customer, date, status, amount or payment
method, and returns one short line each.

**Do not use for** - reading an order already identified (→ `somcp_get_order`),
or carts that were never paid for (→ Carts).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_order` | integer | no | exact identifier |
| `reference` | string | no | whole or partial reference |
| `customer_name` | string | no | first name, last name or both, in any order |
| `customer_email` | string | no | exact match, more reliable than the name |
| `date_from` / `date_to` | string | no | `YYYY-MM-DD`, both days included |
| `status_id` | integer | no | shop-specific, never guessed |
| `total_min` / `total_max` | number | no | amount paid, tax included |
| `payment_method` | string | no | whole or partial |
| `only_valid` | boolean | no | paid orders only |
| `sort` | enum | no | `date_desc` (default), `date_asc`, `total_desc`, `total_asc` |
| `limit` / `offset` | integer | no | 20 by default, 100 at most |

**Returns** - per order: `id_order`, `reference`, `date`, `customer_name`,
`phone`, `total_paid`, `currency`, `payment_method`, `status`, `status_id`. Plus
`returned`, `total_count` and `offset`.

**Notes** - read through a query, not through Order objects: a page would
otherwise load twenty orders with their customers and addresses. The phone is
the delivery address one, mobile first, because that is what carriers use. The
list stays plain on purpose; the address book of a shop does not belong in an
answer about what came in yesterday.

#### `somcp_get_order`
**State**: written
**Nature**: `acts(VIEW, 'AdminOrders')` · **Scope**: `mcp.orders.read`

**Does** - returns everything about one order.

**Do not use for** - looking for orders (→ `somcp_search_orders`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_order` | integer | one of the two | identifier |
| `reference` | string | one of the two | reference |

**Returns** - `order`, `customer`, `addresses` (delivery and invoice),
`products`, `discounts`, `shipping`, `totals`, `payments`, `history`,
`documents` (invoices and credit slips), `messages`.

**Notes** - takes no search filter on purpose: given "Dupont's March orders" it
would have to pick one of twelve. Finding is one tool, reading is another. Read
through core objects, which one order can afford.

#### `somcp_set_order_status`
**State**: written
**Nature**: `acts(EDIT, 'AdminOrders')` · **Scope**: `mcp.orders.write`

**Does** - changes the status of one order, with every effect the shop attaches
to that status: the customer email, the stock movement, the invoice, the refund.

**Do not use for** - reading what the statuses are (→
`somcp_list_order_statuses`, which must be called first), or changing several
orders in one call, which it refuses by design.

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_order` | integer | one of the two | identifier |
| `reference` | string | one of the two | reference |
| `status_id` | integer | yes | from `somcp_list_order_statuses`, never guessed |

**Returns** - `changed`, `order`, `previous_status`, `status`,
`status_sends_email`, `email_sent`, `warning`, `changed_by_employee_id`. When the
order already has that status: `changed: false` with a `reason`, and nothing
happens.

**Notes** - the sequence is the core's own, read from `UpdateOrderStatusHandler`
on 8.0.0 and 9.1.5 rather than reinvented: refuse a redundant status, set
`id_employee`, `changeIdOrderState()` with the payments carried over when there
is no invoice yet, then `addWithemail()`.

Refusing a status the order already has is not politeness, it is the guard
against the likeliest harm this tool can do - a retried call mailing the
customer twice. The core refuses it as well.

`addWithemail()` returns false both when nothing was written and when the status
changed but the mail failed. Those are reported apart, on whether the history row
got an id: an assistant told only "it failed" would retry a change that already
happened.

Advanced stock management is synchronised on PrestaShop 8 only, guarded by
`method_exists`: `StockAvailable::dependsOnStock()` no longer exists in 9.

#### `somcp_set_order_tracking`
**State**: draft · [issue #19](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/19)
**Nature**: `acts(EDIT, 'AdminOrders')` · **Scope**: `mcp.orders.write`

**Does** - sets the tracking number of one order's shipment.

**Do not use for** - changing the order status to shipped (→ `somcp_set_order_status`), which is a separate decision.

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_order` | integer | one of the two |  |
| `reference` | string | one of the two |  |
| `tracking_number` | string | yes | checked against the carrier's format when it has one |

**Returns** - `changed`, `previous_tracking_number`, `tracking_number`, `tracking_url`, `customer_notified`.

**Notes** - written where the back office writes it, on the order's `OrderCarrier`, with `actionAdminOrdersTrackingNumberUpdate` fired so carrier modules follow. Whether the customer is emailed follows the shop's own setting.

### Billing

The accounting records an order produces: invoices, credit slips, delivery
slips. They follow from an order without being the order.

**Not here**: the amounts of an order, which belong to the order itself
(→ Orders).

_No tool described yet._

### Carts

What is not an order yet, abandoned carts included.

**Not here**: placed orders (→ Orders).

_No tool described yet._

### Configuration

What a tool needs so it does not have to guess an identifier: statuses,
carriers, currencies, languages, countries, payment methods.

These are called **before** a write, never for their own sake. Identifiers differ
from one shop to the next, and an assistant that guesses gets the most dangerous
action wrong.

#### `somcp_list_order_statuses`
**State**: written
**Nature**: `acts(VIEW, 'AdminStatuses')` · **Scope**: `mcp.orders.read`

**Does** - lists the order statuses of this shop with their identifiers, the role
each one plays in the shop, and what each one sets off: paid, shipped, delivery,
invoice, whether it emails the customer and what that email carries.

**Do not use for** - reading orders, or counting them by status (→
`somcp_search_orders`, which takes a `status_id` and returns `total_count`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `language` | string | no | ISO code, shop default otherwise |

**Returns** - per status: `id`, `name`, `roles`, `paid`, `shipped`, `delivery`,
`sends_email`, `invoice`, `counts_as_valid`, `hidden_from_customer`,
`attaches_invoice_pdf`, `attaches_delivery_slip_pdf`, `built_in`,
`email_template`, `payment_module`, `color`. Plus `count` and `language_id`.

**Notes** - in Configuration, not in Orders: an order uses a status without
containing one. Every field is a real column of `order_state`, checked against
the class after a first draft invented a `refunds` one.

`roles` is the point of the tool. PrestaShop records which status plays which
part in its own configuration - fourteen keys, the same on 8.0.0 and 9.1.5 - and
that is the only reliable way to find one: the name is translated and the
merchant can rename it. Without it, an assistant asked to mark an order shipped
guesses from a label. A list rather than one value, because
`PS_OS_OUTOFSTOCK` and `PS_OS_OUTOFSTOCK_UNPAID` land on the same status in a
default install. Empty for a status the merchant created.

No count of orders per status here, on purpose. It would hand order data to an
account holding only the right to see the statuses page, and the count already
exists in `somcp_search_orders`, where the permission is the right one.

`deleted` was returned and always false: `OrderState::getOrderStates()` filters
them out before we see them. Removed rather than left lying.

#### `somcp_list_carriers`
**State**: draft · [issue #20](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/20)
**Nature**: `acts(VIEW, 'AdminCarriers')` · **Scope**: `mcp.orders.read`

**Does** - lists the carriers of this shop with their identifiers, delays and tracking URL.

**Do not use for** - reading the carrier of one order (→ `somcp_get_order`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `active` | boolean | no | true by default |
| `language` | string | no | ISO code, shop default otherwise |

**Returns** - per carrier: `id_carrier`, `id_reference`, `name`, `delay`, `active`, `is_free`, `tracking_url`, `external_module`.

**Notes** - `id_carrier` changes each time a carrier is edited in PrestaShop; `id_reference` does not. Both are returned, and the description says which to keep.

### Diagnostics

Tools that describe the connection itself and touch no shop data.

#### `somcp_ping`
**State**: tested
**Nature**: `actsOnNothing()` - no permission required · **Scope**: `mcp.access`

**Does** - checks that the connection to the shop works and says what it
reached: which shop answered, the PrestaShop version, the languages, currency
and time zone its values are expressed in, and the employee account the
connection acts under. On a multistore installation it also names the shops this
connection cannot reach.

**Do not use for** - reading shop data: it reads none.

**Parameters** - none.

**Returns** - seven lines of text, eight on a multistore shop.

**Notes** - outside the permission system by design: it is how an assistant with
no rights anywhere can still say which account it is connected as.

### Catalogue

The products and the categories they are sorted into: texts, prices, combinations, images, the category tree.

**Not here**: stock quantities (→ Stock), what sold (→ Statistics).

#### `somcp_search_products`
**State**: to write · [issue #1](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/1)
**Nature**: `acts(VIEW, 'AdminProducts')` · **Scope**: `mcp.catalog.read`

**Does** - finds products with the filters of the back office product list (ID, name, reference, category, price without and with tax, quantity, status, EAN13, MPN, UPC, ISBN), and returns one short line each.

**Do not use for** - reading a product already identified (→ `somcp_get_product`), or stock levels across the catalogue (→ `somcp_list_low_stock`).

**Parameters** - every filter is optional and they combine (AND). Text filters match a part of the value, case-insensitive.

| Name | Type | Required | Role |
|---|---|---|---|
| `id_product` | integer | no | one product by its ID |
| `name` | string | no | part of the name, in `language` |
| `reference` | string | no | part of the reference of the product **or of one of its combinations** |
| `id_category` | integer | no | products associated with this category, from `somcp_list_categories` |
| `price_tax_excl_min` / `price_tax_excl_max` | number | no | price without tax, bounds included |
| `price_tax_incl_min` / `price_tax_incl_max` | number | no | price with tax, bounds included |
| `quantity_min` / `quantity_max` | integer | no | total quantity, combinations included, bounds included |
| `active` | boolean | no | active or inactive products only |
| `ean13` | string | no | part of the EAN13 of the product **or of one of its combinations** |
| `mpn` | string | no | part of the product's MPN |
| `upc` | string | no | part of the product's UPC barcode |
| `isbn` | string | no | part of the product's ISBN |
| `sort` | enum | no | `date_add_desc` (default), `name_asc`, `price_asc`, `price_desc`, `quantity_asc`, `quantity_desc` |
| `language` | string | no | ISO code, shop default otherwise |
| `limit` / `offset` | integer | no | 20 by default, 100 at most |

**Returns** - per product: `id_product`, `reference`, `name`, `price_tax_excl`, `price_tax_incl`, `currency`, `quantity`, `active`, `id_category_default`, `ean13`. When the product was found through a combination's reference or EAN13: `matched_combinations`, a list of `id_product_attribute`, `reference`, `ean13` for the combinations that matched. Plus `returned`, `total_count` and `offset`.

**Notes**
- Read through a query (`DbQuery`), not through Product objects. Same columns as the back office product list: `product_shop` for price and status, `product_lang` for the name, `category_product` for the category, `stock_available` with `id_product_attribute = 0` for the quantity.
- Reference and EAN13 also look in `product_attribute`; MPN, UPC and ISBN only on the product. A product found through several combinations comes back once.
- Prices are the product's base price, before specific prices and combination impacts. The price with tax uses the product's tax rule in the shop's default country, as the back office list does.
- At least one filter is not required: without any, the tool lists the catalogue page by page.

#### `somcp_get_product`
**State**: to write · [issue #2](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/2)
**Nature**: `acts(VIEW, 'AdminProducts')` · **Scope**: `mcp.catalog.read`

**Does** - returns everything the back office product page shows for one product, tab by tab, combinations included.

**Do not use for** - looking for products (→ `somcp_search_products`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_product` | integer | yes | from `somcp_search_products` |
| `id_product_attribute` | integer | no | one combination of that product: `combinations` then holds that one only |
| `language` | string | no | ISO code, shop default otherwise |

**Returns** - one object, grouped like the tabs of the product page. Texts are in `language`; every amount comes with `currency`.

| Group | Fields | Where it is read |
|---|---|---|
| `basics` | `id_product`, `product_type` (standard, combinations, pack, virtual), `name`, `description_short` (summary), `description` | `product`, `product_lang` |
| `categories` | `id_category_default`, `categories` (id and name of each associated category) | `category_product` |
| `brand` | `id_manufacturer`, `manufacturer_name` | `manufacturer` |
| `related_products` | id, reference and name of each related product | `Product::getAccessories()` |
| `references` | `reference`, `mpn`, `upc`, `ean13` (EAN-13 or JAN), `isbn` | `product` |
| `features` | per feature: `id_feature`, `name`, `value`, `custom` | `Product::getFrontFeatures()` |
| `attachments` | per document: `id_attachment`, `name`, `description`, `file_name`, `mime` | `Product::getAttachments()` |
| `condition` | `condition` (new, used, refurbished), `show_condition` | `product` |
| `customization` | `customizable`, `uploadable_files`, `text_fields`, and each field: `id_customization_field`, `type` (file or text), `name`, `required` | `customization_field`, `customization_field_lang` |
| `stock` | `quantity` (total, combinations included), `location` (stock location), `out_of_stock` (deny, allow, shop default), `available_now` (label when in stock), `available_later` (label when out of stock and backorders allowed), `available_date` | `stock_available`, `product`, `product_lang` |
| `shipping` | `width`, `height`, `depth`, `weight`, `additional_delivery_times` (none, shop default, this product's), `delivery_in_stock`, `delivery_out_stock`, `additional_shipping_cost`, `carriers` (every carrier of the shop with `id_carrier`, `name` and `available` true or false for this product) | `product`, `product_lang`, `Product::getCarriers()` |
| `pricing` | `price_tax_excl`, `id_tax_rules_group`, `tax_rules_group_name`, `tax_rate`, `price_tax_incl`, `wholesale_price` (purchase price, tax excluded), `unit_price_tax_excl`, `unit_price_tax_incl`, `unity` (the unit), `on_sale` | `product`, `TaxRulesGroup`, `Product::getTaxesRate()` |
| `specific_prices` | each one: `id_specific_price`, `id_product_attribute`, shop, currency, country, group, customer, `from_quantity`, `price` (fixed or -1), `reduction`, `reduction_type`, `reduction_tax`, `from`, `to`; plus `priorities`, the order used to pick between them | `SpecificPrice::getByProductId()`, `SpecificPrice::getPriority()` |
| `seo` | `meta_title`, `meta_description`, `link_rewrite` (friendly URL), `url` (full product URL), `redirect_type`, `id_type_redirected` (redirection page), `meta_keywords` | `product_lang`, `Link::getProductLink()` |
| `options` | `visibility` (everywhere, catalog only, search only, nowhere), `available_for_order`, `show_price`, `online_only` (web exclusive, not sold in store), `active` | `product_shop` |
| `suppliers` | `id_supplier_default`, and per supplier and combination: `id_supplier`, `supplier_name`, `id_product_attribute`, `product_supplier_reference` (supplier SKU), `product_supplier_price_te` (supplier purchase price), `currency` (supplier currency) | `product_supplier`, `supplier` |
| `images` | per image: `id_image`, `position`, `cover` (the default image), `legend`, `url` (large size) | `Image::getImages()`, `image_lang`, `Link::getImageLink()` |
| `combinations` | per combination: `id_product_attribute`, `attributes` (group and value, for example Size: M), `default_on`, `reference`, `mpn`, `upc`, `ean13`, `isbn`, `quantity`, `location`, `available_date`, `available_now`, `available_later`, `price_impact_tax_excl`, `price_impact_tax_incl`, `unit_price_impact_tax_excl`, `unit_price_impact_tax_incl`, `weight_impact`, `wholesale_price`, `minimal_quantity`, `images` (ids) | `product_attribute`, `product_attribute_lang`, `product_attribute_combination`, `stock_available`, `product_attribute_image` |

**Notes**
- Called by identifiers only, never by a reference, an EAN13 or a name: finding the product is the job of `somcp_search_products`, which also returns the `id_product_attribute` of a matching combination.
- An `id_product_attribute` that does not belong to `id_product` is refused, never silently ignored.
- The values are the product's own, before specific prices and cart rules; `specific_prices` lists the rules apart, so the assistant does not mistake a promotion for the price.
- PrestaShop 8.0 has no `product_attribute_lang`: the `available_now` and `available_later` of a combination exist from 8.1 and are `null` before. PrestaShop 9 dropped `meta_keywords`: the field is `null` there. Check with the column, not the version number.
- `tax_rate` is the rate of the tax rule in the shop's default country, the one the back office shows.
- Read the product in the shop of the token (`product_shop`), never the default shop.

#### `somcp_create_product`
**State**: draft · [issue #3](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/3)
**Nature**: `acts(ADD, 'AdminProducts')` · **Scope**: `mcp.catalog.write`

**Does** - creates one product, inactive by default, with its name, reference, price, tax rule, default category and quantity.

**Do not use for** - changing an existing product (→ `somcp_update_product`), or creating several at once, which it refuses by design.

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `name` | string | yes | in the shop's default language |
| `price_tax_excl` | number | yes | base price |
| `id_tax_rules_group` | integer | no | from the shop, never guessed |
| `id_category_default` | integer | no | from `somcp_list_categories`, Home otherwise |
| `reference` | string | no |  |
| `description_short` | string | no | HTML allowed, cleaned |
| `quantity` | integer | no | initial stock, 0 by default |
| `active` | boolean | no | false by default |

**Returns** - `created`, `id_product`, and the product as `somcp_get_product` would return it.

**Notes** - inactive unless asked otherwise, so a product half described never appears on the storefront. Written through `Product::add()` so hooks fire. Not replayable: the read tool to check is `somcp_search_products` with the reference.

#### `somcp_update_product`
**State**: draft · [issue #4](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/4)
**Nature**: `acts(EDIT, 'AdminProducts')` · **Scope**: `mcp.catalog.write`

**Does** - changes the texts of one product: name, short and long description, meta title, meta description, friendly URL, in one language.

**Do not use for** - prices (→ `somcp_set_product_price`), stock (→ `somcp_set_stock_quantity`), switching a product on or off (→ `somcp_set_product_active`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_product` | integer | yes |  |
| `language` | string | yes | the language being written |
| `name` | string | no |  |
| `description_short` | string | no | HTML allowed, cleaned |
| `description` | string | no | HTML allowed, cleaned |
| `meta_title` | string | no |  |
| `meta_description` | string | no |  |
| `link_rewrite` | string | no | checked for format |

**Returns** - `changed`, the fields written with their old and new values.

**Notes** - only the fields given are touched; a field left out keeps its value. HTML goes through the core's own cleaning, as in the back office.

#### `somcp_set_product_price`
**State**: draft · [issue #5](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/5)
**Nature**: `acts(EDIT, 'AdminProducts')` · **Scope**: `mcp.catalog.write`

**Does** - sets the price of one product, or the price impact of one of its combinations.

**Do not use for** - discounts and specific prices, which are not covered yet.

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_product` | integer | yes |  |
| `id_product_attribute` | integer | no | a combination, from `somcp_get_product` |
| `price_tax_excl` | number | one of the two | the new price |
| `price_tax_incl` | number | one of the two | converted with the product's tax rule |

**Returns** - `changed`, `previous_price_tax_excl`, `price_tax_excl`, `price_tax_incl`, `currency`.

**Notes** - a price given tax included is converted with the product's own tax rule for the shop's country, and both values are returned so the merchant sees what was stored.

#### `somcp_set_product_active`
**State**: draft · [issue #6](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/6)
**Nature**: `acts(EDIT, 'AdminProducts')` · **Scope**: `mcp.catalog.write`

**Does** - switches one product on or off on the storefront.

**Do not use for** - deleting a product (→ `somcp_delete_product`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_product` | integer | yes |  |
| `active` | boolean | yes |  |

**Returns** - `changed`, `active`. When the product is already in that state: `changed: false` and nothing happens.

#### `somcp_delete_product`
**State**: draft · [issue #7](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/7)
**Nature**: `acts(DELETE, 'AdminProducts')` · **Scope**: `mcp.catalog.write`

**Does** - deletes one product, with its combinations, images and stock.

**Do not use for** - hiding a product from the storefront, which is reversible (→ `somcp_set_product_active`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_product` | integer | yes |  |

**Returns** - `deleted`, `id_product`, `name`.

**Notes** - irreversible, and said so in the description so the client asks the merchant. Through `Product::delete()`, so hooks fire. Orders that contain the product keep their lines.

#### `somcp_list_categories`
**State**: draft · [issue #8](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/8)
**Nature**: `acts(VIEW, 'AdminCategories')` · **Scope**: `mcp.catalog.read`

**Does** - lists the categories as a tree, with their identifiers.

**Do not use for** - listing the products of a category (→ `somcp_search_products` with `id_category`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_parent` | integer | no | a branch only, the whole tree otherwise |
| `active` | boolean | no |  |
| `language` | string | no | ISO code, shop default otherwise |

**Returns** - per category: `id_category`, `name`, `id_parent`, `level`, `active`, `products_count`.

**Notes** - called before any write that takes a category, which must never guess an identifier.

#### `somcp_create_category`
**State**: draft · [issue #9](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/9)
**Nature**: `acts(ADD, 'AdminCategories')` · **Scope**: `mcp.catalog.write`

**Does** - creates one category under a given parent.

**Do not use for** - renaming or moving a category (→ `somcp_update_category`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `name` | string | yes |  |
| `id_parent` | integer | yes | from `somcp_list_categories` |
| `description` | string | no |  |
| `active` | boolean | no | true by default |

**Returns** - `created`, `id_category`.

**Notes** - not replayable: the read tool to check is `somcp_list_categories`.

#### `somcp_update_category`
**State**: draft · [issue #10](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/10)
**Nature**: `acts(EDIT, 'AdminCategories')` · **Scope**: `mcp.catalog.write`

**Does** - changes one category: name, description, SEO fields, parent, active.

**Do not use for** - creating one (→ `somcp_create_category`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_category` | integer | yes |  |
| `language` | string | no | for the texts |
| `name` | string | no |  |
| `description` | string | no |  |
| `id_parent` | integer | no | moving it |
| `active` | boolean | no |  |

**Returns** - `changed`, the fields written with their old and new values.

**Notes** - the root and Home categories are refused, as in the back office.

#### `somcp_delete_category`
**State**: draft · [issue #11](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/11)
**Nature**: `acts(DELETE, 'AdminCategories')` · **Scope**: `mcp.catalog.write`

**Does** - deletes one category.

**Do not use for** - hiding it (→ `somcp_update_category` with `active: false`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_category` | integer | yes |  |

**Returns** - `deleted`, `id_category`, `name`, `products_moved`.

**Notes** - irreversible. Products whose default category it was are moved to its parent, which is what the back office does by default. Root and Home are refused.

### Stock

The quantities the storefront sells against, per product and combination.

**Not here**: prices and texts (→ Catalogue).

#### `somcp_get_stock`
**State**: draft · [issue #12](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/12)
**Nature**: `acts(VIEW, 'AdminStockManagement')` · **Scope**: `mcp.stock.read`

**Does** - returns the quantities of one product, per combination.

**Do not use for** - finding what runs low across the catalogue (→ `somcp_list_low_stock`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_product` | integer | yes |  |

**Returns** - `quantity` (total), and per combination: `id_product_attribute`, `attributes`, `quantity`, `physical_quantity`, `reserved_quantity`, `low_stock_threshold`.

**Notes** - read from `StockAvailable`, the quantities the storefront sells against.

#### `somcp_list_low_stock`
**State**: draft · [issue #13](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/13)
**Nature**: `acts(VIEW, 'AdminStockManagement')` · **Scope**: `mcp.stock.read`

**Does** - lists the products and combinations at or under a quantity.

**Do not use for** - the stock of one known product (→ `somcp_get_stock`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `threshold` | integer | no | each product's own low stock threshold when absent, 0 when it has none |
| `active` | boolean | no | active products only, true by default |
| `limit` / `offset` | integer | no | 20 by default, 100 at most |

**Returns** - per line: `id_product`, `id_product_attribute`, `reference`, `name`, `quantity`, `threshold`. Plus `total_count`.

#### `somcp_set_stock_quantity`
**State**: draft · [issue #14](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/14)
**Nature**: `acts(EDIT, 'AdminStockManagement')` · **Scope**: `mcp.stock.write`

**Does** - sets the available quantity of one product or one combination.

**Do not use for** - reading stock (→ `somcp_get_stock`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_product` | integer | yes |  |
| `id_product_attribute` | integer | no | a combination |
| `quantity` | integer | one of the two | the new quantity |
| `delta` | integer | one of the two | a movement, positive or negative |

**Returns** - `changed`, `previous_quantity`, `quantity`.

**Notes** - through `StockAvailable::setQuantity()` / `updateQuantity()`, so stock movements and hooks follow. A delta is not replayable: a lost answer retried would move the stock twice, which the description says, naming `somcp_get_stock` to check.

### Customers

Customer accounts and what belongs to them: groups, addresses, a summary of their orders.

**Not here**: the orders themselves (→ Orders).

#### `somcp_search_customers`
**State**: draft · [issue #15](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/15)
**Nature**: `acts(VIEW, 'AdminCustomers')` · **Scope**: `mcp.customers.read`

**Does** - finds customers by name, email, company or registration date, and returns one short line each.

**Do not use for** - reading a customer already identified (→ `somcp_get_customer`), or their orders (→ `somcp_search_orders` with `customer_email`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `query` | string | no | name, email or company, whole or partial |
| `date_from` / `date_to` | string | no | registration date |
| `active` | boolean | no |  |
| `newsletter` | boolean | no |  |
| `limit` / `offset` | integer | no | 20 by default, 100 at most |

**Returns** - per customer: `id_customer`, `name`, `email`, `company`, `date_add`, `active`, `orders_count`. Plus `total_count`.

**Notes** - the list stays plain: no address, no phone. Those come with one named customer.

#### `somcp_get_customer`
**State**: draft · [issue #16](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/16)
**Nature**: `acts(VIEW, 'AdminCustomers')` · **Scope**: `mcp.customers.read`

**Does** - returns everything about one customer: account, groups, addresses, and a summary of their orders.

**Do not use for** - looking for customers (→ `somcp_search_customers`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_customer` | integer | one of the two |  |
| `email` | string | one of the two | exact |

**Returns** - `customer` (name, email, birthday, company, groups, newsletter, optin, active, dates), `addresses`, `orders` (count, total spent, last order), `messages_count`.

**Notes** - never the password hash, the secure key or the reset token.

#### `somcp_create_customer`
**State**: draft · [issue #17](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/17)
**Nature**: `acts(ADD, 'AdminCustomers')` · **Scope**: `mcp.customers.write`

**Does** - creates one customer account.

**Do not use for** - creating a guest checkout, or an address alone.

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `firstname` | string | yes |  |
| `lastname` | string | yes |  |
| `email` | string | yes | refused if it already exists |
| `id_default_group` | integer | no | Customer by default |
| `newsletter` | boolean | no | false by default |
| `send_welcome_email` | boolean | no | false by default |

**Returns** - `created`, `id_customer`.

**Notes** - no password goes through the assistant: the account is created with a random one, and the customer sets theirs through the usual reset link. Not replayable: the read tool to check is `somcp_search_customers` with the email.

#### `somcp_update_customer`
**State**: draft · [issue #18](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/18)
**Nature**: `acts(EDIT, 'AdminCustomers')` · **Scope**: `mcp.customers.write`

**Does** - changes one customer's account: name, email, company, birthday, groups, newsletter, active.

**Do not use for** - addresses, which are not covered yet.

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_customer` | integer | yes |  |
| `firstname` / `lastname` | string | no |  |
| `email` | string | no | refused if another account uses it |
| `company` | string | no |  |
| `birthday` | string | no | `YYYY-MM-DD` |
| `newsletter` | boolean | no |  |
| `active` | boolean | no |  |

**Returns** - `changed`, the fields written with their old and new values.

**Notes** - never the password.

### Statistics

Figures over a period, computed from valid orders.

**Not here**: the orders behind them (→ Orders).

#### `somcp_get_sales_summary`
**State**: draft · [issue #21](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/21)
**Nature**: `acts(VIEW, 'AdminStats')` · **Scope**: `mcp.stats.read`

**Does** - returns sales over a period: revenue, number of orders, average basket, new customers.

**Do not use for** - the list of orders behind the figures (→ `somcp_search_orders`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `date_from` / `date_to` | string | yes | `YYYY-MM-DD`, both days included |
| `compare_previous` | boolean | no | adds the same figures for the previous period of the same length |

**Returns** - `revenue_tax_excl`, `revenue_tax_incl`, `currency`, `orders_count`, `average_basket_tax_incl`, `new_customers`, and `previous` when asked.

**Notes** - counts valid orders only (`Order::valid`), converted to the shop's default currency, as the dashboard does.

#### `somcp_list_best_sellers`
**State**: draft · [issue #22](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/22)
**Nature**: `acts(VIEW, 'AdminStats')` · **Scope**: `mcp.stats.read`

**Does** - lists the products that sold most over a period.

**Do not use for** - sales totals (→ `somcp_get_sales_summary`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `date_from` / `date_to` | string | yes |  |
| `sort` | enum | no | `quantity` (default) or `revenue` |
| `limit` / `offset` | integer | no | 20 by default, 100 at most |

**Returns** - per product: `id_product`, `name`, `quantity_sold`, `revenue_tax_excl`, `currency`. Plus `total_count`.

**Notes** - valid orders only.

### Content

The CMS pages of the storefront.

**Not here**: product texts (→ Catalogue).

#### `somcp_list_cms_pages`
**State**: draft · [issue #23](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/23)
**Nature**: `acts(VIEW, 'AdminCmsContent')` · **Scope**: `mcp.content.read`

**Does** - lists the CMS pages with their category and status.

**Do not use for** - reading one page (→ `somcp_get_cms_page`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_cms_category` | integer | no |  |
| `active` | boolean | no |  |
| `language` | string | no | ISO code, shop default otherwise |

**Returns** - per page: `id_cms`, `meta_title`, `id_cms_category`, `active`, `link`.

#### `somcp_get_cms_page`
**State**: draft · [issue #24](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/24)
**Nature**: `acts(VIEW, 'AdminCmsContent')` · **Scope**: `mcp.content.read`

**Does** - returns one CMS page with its content.

**Do not use for** - listing pages (→ `somcp_list_cms_pages`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id_cms` | integer | yes |  |
| `language` | string | no | ISO code, shop default otherwise |

**Returns** - `id_cms`, `meta_title`, `meta_description`, `content` (HTML), `link_rewrite`, `active`, `link`.

### Modules

The modules present on the shop, their state and version.

**Not here**: tools published by other modules, which they declare themselves (see EXTENDING.md).

#### `somcp_list_modules`
**State**: draft · [issue #25](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/25)
**Nature**: `acts(VIEW, 'AdminModules')` · **Scope**: `mcp.modules.read`

**Does** - lists the modules present on the shop with their version and state.

**Do not use for** - details of one module (→ `somcp_get_module`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `installed` | boolean | no |  |
| `active` | boolean | no |  |
| `query` | string | no | part of the name or display name |
| `limit` / `offset` | integer | no | 20 by default, 100 at most |

**Returns** - per module: `name`, `display_name`, `version`, `installed`, `active`, `author`.

#### `somcp_get_module`
**State**: draft · [issue #26](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/26)
**Nature**: `acts(VIEW, 'AdminModules')` · **Scope**: `mcp.modules.read`

**Does** - returns one module's details: version on disk and in the database, state, author, hooks it is attached to.

**Do not use for** - listing modules (→ `somcp_list_modules`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `name` | string | yes | technical name, the folder |

**Returns** - `name`, `display_name`, `description`, `version_on_disk`, `version_installed`, `installed`, `active`, `author`, `hooks`, `needs_upgrade`.

#### `somcp_install_module`
**State**: draft · [issue #27](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/27)
**Nature**: `acts(ADD, 'AdminModules')` · **Scope**: `mcp.modules.write`

**Does** - installs one module already present in the shop's `modules` folder.

**Do not use for** - uploading or downloading a module, which this tool does not do: the files must already be on the shop.

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `name` | string | yes | technical name, the folder |

**Returns** - `installed`, `name`, `version`, `errors`.

**Notes** - through the core's `ModuleManager`, exactly as the back office does it, so the module's own install checks run. Not replayable: the read tool to check is `somcp_get_module`.

#### `somcp_set_module_active`
**State**: draft · [issue #28](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/28)
**Nature**: `acts(EDIT, 'AdminModules')` · **Scope**: `mcp.modules.write`

**Does** - enables or disables one installed module.

**Do not use for** - installing or uninstalling it.

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `name` | string | yes |  |
| `active` | boolean | yes |  |

**Returns** - `changed`, `name`, `active`. When the module is already in that state: `changed: false`.

**Notes** - somcp refuses to disable itself: the answer would never reach the assistant.

### Appearance

What the storefront looks like: the main menu of `ps_mainmenu`, the active theme and its page layouts.

**Not here**: content of the pages (→ Content).

#### `somcp_list_menu_links`
**State**: draft · [issue #29](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/29)
**Nature**: `acts(VIEW, 'AdminModules')` · **Scope**: `mcp.appearance.read`

**Does** - lists the entries of the storefront's main menu, from the `ps_mainmenu` module.

**Do not use for** - the shop's categories (→ `somcp_list_categories`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `language` | string | no | ISO code, shop default otherwise |

**Returns** - per entry, in menu order: `type` (category, CMS page, custom link, ...), `id`, `label`, `link`, `new_window`.

**Notes** - only listed when `ps_mainmenu` is installed and active; absent otherwise, never failing.

#### `somcp_add_menu_link`
**State**: draft · [issue #30](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/30)
**Nature**: `acts(ADD, 'AdminModules')` · **Scope**: `mcp.appearance.write`

**Does** - adds one custom link, a category or a CMS page to the main menu.

**Do not use for** - changing an existing custom link (→ `somcp_update_menu_link`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `type` | enum | yes | `category`, `cms`, `link` |
| `id` | integer | for category and cms |  |
| `label` | string | for link |  |
| `url` | string | for link |  |
| `new_window` | boolean | no |  |
| `position` | integer | no | at the end by default |

**Returns** - `added`, the menu as `somcp_list_menu_links` returns it.

**Notes** - written through `ps_mainmenu`'s own storage so its cache is cleared like the back office does. Not replayable.

#### `somcp_update_menu_link`
**State**: draft · [issue #31](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/31)
**Nature**: `acts(EDIT, 'AdminModules')` · **Scope**: `mcp.appearance.write`

**Does** - changes the label, URL or target of one custom link in the main menu.

**Do not use for** - adding an entry (→ `somcp_add_menu_link`).

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `id` | integer | yes | custom link, from `somcp_list_menu_links` |
| `label` | string | no |  |
| `url` | string | no |  |
| `new_window` | boolean | no |  |
| `language` | string | no | ISO code, shop default otherwise |

**Returns** - `changed`, the fields written with their old and new values.

#### `somcp_get_theme`
**State**: draft · [issue #32](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/32)
**Nature**: `acts(VIEW, 'AdminThemes')` · **Scope**: `mcp.appearance.read`

**Does** - returns the active theme: name, version, parent theme, logos and the layout used by each page.

**Parameters** - none.

**Returns** - `name`, `version`, `parent`, `logos`, `layouts` (page → layout), `available_layouts`.

#### `somcp_update_theme_setting`
**State**: draft · [issue #33](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/33)
**Nature**: `acts(EDIT, 'AdminThemes')` · **Scope**: `mcp.appearance.write`

**Does** - sets the layout of one page of the active theme, as Appearance > Theme & Logo > Choose layouts does.

**Do not use for** - installing or switching themes.

**Parameters**

| Name | Type | Required | Role |
|---|---|---|---|
| `page` | string | yes | page name, from `somcp_get_theme` |
| `layout` | string | yes | one of `available_layouts` |

**Returns** - `changed`, `page`, `previous_layout`, `layout`.

**Notes** - the scope is the page layouts on purpose: other theme settings depend on each theme. Wider settings can be proposed in the issue.

### System

How the shop runs: caching.

**Not here**: the connection itself (→ Diagnostics).

#### `somcp_get_cache_status`
**State**: draft · [issue #34](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/34)
**Nature**: `acts(VIEW, 'AdminPerformance')` · **Scope**: `mcp.system.read`

**Does** - returns how caching is configured: template compilation and cache, CCC for CSS and JavaScript, the caching system, debug mode.

**Parameters** - none.

**Returns** - `smarty_compile`, `smarty_cache`, `ccc_css`, `ccc_js`, `cache_enabled`, `cache_system`, `debug_mode`.

#### `somcp_clear_cache`
**State**: draft · [issue #35](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/35)
**Nature**: `acts(EDIT, 'AdminPerformance')` · **Scope**: `mcp.system.write`

**Does** - clears the shop's caches, as the Clear cache button in Advanced parameters > Performance does.

**Parameters** - none.

**Returns** - `cleared`.

**Notes** - the storefront is slower for a while as the cache rebuilds; the description says so.

---

## Prompts

A prompt is the guide an assistant reads before using the tools of one part of the
shop. There is one per section, so a merchant starts from the menu of their
assistant with the right instructions loaded. How a prompt is built - `prompt.md`
plus `prompt.json`, filtering, arguments - is in the shared conventions above and in
[EXTENDING.md](EXTENDING.md).

Every tool the module ships belongs to at least one prompt, and CI checks it: a
pull request that adds a tool adds it to the prompt of its section, in both
`prompt.json` and the guide.

| Prompt | Section | State |
|---|---|---|
| `somcp-connection-check` | Diagnostics | written |
| `somcp-order-lookup` | Orders | written |
| `somcp-order-status-change` | Orders | written |
| `somcp-catalogue-manage` | Catalogue | draft · [issue #36](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/36) |
| `somcp-stock-check` | Stock | draft · [issue #37](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/37) |
| `somcp-customer-lookup` | Customers | draft · [issue #38](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/38) |
| `somcp-sales-report` | Statistics | draft · [issue #39](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/39) |
| `somcp-content-pages` | Content | draft · [issue #40](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/40) |
| `somcp-modules-review` | Modules | draft · [issue #41](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/41) |
| `somcp-storefront-menu` | Appearance | draft · [issue #42](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/42) |
| `somcp-cache-maintenance` | System | draft · [issue #43](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/43) |

#### `somcp-catalogue-manage`
**State**: draft · [issue #36](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/36)
**Section**: Catalogue · **Scope**: `mcp.catalog.read` · **Permission**: `view` on `AdminProducts`

**Title** - Manage products

**Description** - Find, read and change products and categories in the PrestaShop shop, one at a time, without guessing an identifier.

**Arguments** - `product` (name, reference or ean13 of a product).

**Tools** - `somcp_ping`, `somcp_search_products`, `somcp_get_product`, `somcp_list_categories`, `somcp_create_product`, `somcp_update_product`, `somcp_set_product_price`, `somcp_set_product_active`, `somcp_delete_product`, `somcp_create_category`, `somcp_update_category`, `somcp_delete_category`. A tool joins the prompt in the pull request that writes it; the prompt ships with the first tool of its section.

**The guide must say** - search before reading, and read before changing; take category identifiers from `somcp_list_categories`, never guess them; say whether a price is tax included or excluded; one product per write, never retried on a lost answer; switching a product off is reversible, deleting it is not, and the merchant is told which is happening.

#### `somcp-stock-check`
**State**: draft · [issue #37](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/37)
**Section**: Stock · **Scope**: `mcp.stock.read` · **Permission**: `view` on `AdminStockManagement`

**Title** - Check and adjust stock

**Description** - See what runs low in the PrestaShop shop and set quantities, one product or combination at a time.

**Arguments** - `threshold` (quantity at or under which a product counts as low).

**Tools** - `somcp_ping`, `somcp_list_low_stock`, `somcp_get_stock`, `somcp_search_products`, `somcp_set_stock_quantity`. A tool joins the prompt in the pull request that writes it; the prompt ships with the first tool of its section.

**The guide must say** - start from `somcp_list_low_stock` or a named product; a quantity is per combination when the product has some; a `delta` moves stock and is never retried: on a lost answer, read `somcp_get_stock` first.

#### `somcp-customer-lookup`
**State**: draft · [issue #38](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/38)
**Section**: Customers · **Scope**: `mcp.customers.read` · **Permission**: `view` on `AdminCustomers`

**Title** - Find a customer

**Description** - Find a customer of the PrestaShop shop, read their account and orders, and update it when asked.

**Arguments** - `customer` (name, email or company).

**Tools** - `somcp_ping`, `somcp_search_customers`, `somcp_get_customer`, `somcp_search_orders`, `somcp_create_customer`, `somcp_update_customer`. A tool joins the prompt in the pull request that writes it; the prompt ships with the first tool of its section.

**The guide must say** - search, then read the one customer the merchant means; the email is more reliable than the name; personal data stays in the answer it was asked for; no password ever goes through the conversation.

#### `somcp-sales-report`
**State**: draft · [issue #39](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/39)
**Section**: Statistics · **Scope**: `mcp.stats.read` · **Permission**: `view` on `AdminStats`

**Title** - Sales report

**Description** - Report the PrestaShop shop's sales over a period: revenue, orders, average basket, best sellers.

**Arguments** - `period` (period to report on, for example last month).

**Tools** - `somcp_ping`, `somcp_get_sales_summary`, `somcp_list_best_sellers`, `somcp_search_orders`. A tool joins the prompt in the pull request that writes it; the prompt ships with the first tool of its section.

**The guide must say** - turn the period into two dates in the shop's time zone and say which; figures count valid orders only; compare with the previous period when it helps; amounts always with their currency and tax said.

#### `somcp-content-pages`
**State**: draft · [issue #40](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/40)
**Section**: Content · **Scope**: `mcp.content.read` · **Permission**: `view` on `AdminCmsContent`

**Title** - Read content pages

**Description** - List and read the CMS pages of the PrestaShop shop.

**Arguments** - `page` (title of a page).

**Tools** - `somcp_ping`, `somcp_list_cms_pages`, `somcp_get_cms_page`. A tool joins the prompt in the pull request that writes it; the prompt ships with the first tool of its section.

**The guide must say** - list, then read the page the merchant means; quote the content rather than paraphrasing it when asked what a page says.

#### `somcp-modules-review`
**State**: draft · [issue #41](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/41)
**Section**: Modules · **Scope**: `mcp.modules.read` · **Permission**: `view` on `AdminModules`

**Title** - Review modules

**Description** - See which modules the PrestaShop shop runs, their version and state, and install or switch one when asked.

**Arguments** - `module` (name of a module).

**Tools** - `somcp_ping`, `somcp_list_modules`, `somcp_get_module`, `somcp_install_module`, `somcp_set_module_active`. A tool joins the prompt in the pull request that writes it; the prompt ships with the first tool of its section.

**The guide must say** - read before acting; name the module and what it does before installing or switching it; one module per call; a payment or carrier module switched off stops orders or deliveries, say so before doing it.

#### `somcp-storefront-menu`
**State**: draft · [issue #42](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/42)
**Section**: Appearance · **Scope**: `mcp.appearance.read` · **Permission**: `view` on `AdminThemes`

**Title** - Storefront menu and layout

**Description** - Read and change the main menu of the PrestaShop storefront and the page layouts of its theme.

**Arguments** - none.

**Tools** - `somcp_ping`, `somcp_list_menu_links`, `somcp_add_menu_link`, `somcp_update_menu_link`, `somcp_get_theme`, `somcp_update_theme_setting`, `somcp_list_categories`, `somcp_list_cms_pages`. A tool joins the prompt in the pull request that writes it; the prompt ships with the first tool of its section.

**The guide must say** - read the menu or the theme first; take category and page identifiers from their list tools; one change per call.

#### `somcp-cache-maintenance`
**State**: draft · [issue #43](https://github.com/novalo-dev/Prestashop-MCP-Server/issues/43)
**Section**: System · **Scope**: `mcp.system.read` · **Permission**: `view` on `AdminPerformance`

**Title** - Cache maintenance

**Description** - Check how the PrestaShop shop caches and clear the cache when changes do not show.

**Arguments** - none.

**Tools** - `somcp_ping`, `somcp_get_cache_status`, `somcp_clear_cache`. A tool joins the prompt in the pull request that writes it; the prompt ships with the first tool of its section.

**The guide must say** - read the status first; clearing the cache slows the storefront while it rebuilds, so say so and clear it once, not as a reflex.
