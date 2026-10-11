# Roadmap

The foundations are in place: both eras of the MCP protocol, the OAuth 2.1
server, back office permissions, multistore, file deposits, and the contract
that lets other modules publish tools and prompts. What is listed here is the
work that would make the module useful to many more shops.

Each item can be taken on its own. Comment on the matching issue, or open one,
before starting something large, so two people do not build the same thing.

## More of PrestaShop as tools

Today the module covers orders and order statuses. The next areas, in the order
they are planned in [docs/TOOLS.md](docs/TOOLS.md):

- **Catalogue**: search products, read one product with its combinations and
  prices.
- **Customers**: search customers, read one customer with their addresses and
  order history.
- **Stock**: read quantities per product and combination, change a quantity.
- **Shipping**: carriers, tracking numbers on an order.
- **Content**: CMS pages.
- **Statistics**: sales over a period, best sellers.

Every tool is described in `docs/TOOLS.md` before it is written, follows the
conventions at the top of that file, and is tied to the back office page where
a merchant would do the same thing by hand. Reads first; a write tool changes
one object per call.

## Tests

The module has no automated test suite in this repository yet. A PHPUnit suite
running against PrestaShop 8 and 9 would be the most valuable contribution of
all: OAuth flows, permission checks, the multistore boundary, and the contract
with other modules first.

## Clients

somcp has been used with Claude, Claude Code and ChatGPT. Wanted:

- **Cursor** and **VS Code**: connect, run the tools, report what works and
  document the exact configuration in the README.
- Any other MCP client with OAuth support.

A client that behaves differently from the specification is worth an issue
even if you do not have a fix: the module already works around one client that
declares it can open an address and then does not.

## Translations

The interface is in English, French, Spanish and German. More languages are
welcome: add a column to `dev/translations.py`, see
[CONTRIBUTING.md](CONTRIBUTING.md#translations).

## Smaller things

- A `docker compose` setup with PrestaShop 8 and 9 for development.
- Native app redirect URIs (private-use schemes, RFC 8252), refused for now.
- Shops installed in a subfolder: today the authorization server metadata
  cannot be served there.
