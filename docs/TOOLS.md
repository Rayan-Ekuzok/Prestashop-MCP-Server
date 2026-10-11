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
**State**: to write · written · tested
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

---

## Sections to open later

Catalogue · Customers · Stock · Shipping · Content · Statistics.

Each follows the same shape: what the data is, what is not in it, then the
entries.
