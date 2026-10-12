# Contributing

Thanks for looking. somcp is built for PrestaShop developers by PrestaShop
developers, and the most useful contributions are the ones listed in
[ROADMAP.md](ROADMAP.md): more of PrestaShop as tools, tests, clients,
languages.

Before anything large, open an issue or comment on an existing one, so we agree
on the shape before you spend an evening on it. For a new tool, use the
**New tool** issue template.

By taking part you agree to the [code of conduct](CODE_OF_CONDUCT.md).

Before your first pull request is merged, you sign the
[Contributor License Agreement](CLA.md). You keep the copyright on what you
write; the agreement lets the maintainer distribute it under the project's
license, and under another license, commercial included, if the project needs
it later. The CLA Assistant bot posts the link on your pull request: you sign
once, in a few seconds, with your GitHub account.

---

## Set up

You need a PrestaShop 8 and, ideally, a PrestaShop 9, reachable over HTTPS: an
MCP client connects from the outside.

1. Run a shop with the official images, for example
   `prestashop/prestashop:8.0.0-8.1` and `prestashop/prestashop:9.0-8.1`, with
   a MySQL container next to each.
2. Give it a public HTTPS address with a tunnel (`cloudflared tunnel --url
   http://localhost:8080`, or `ngrok http 8080`), set that domain as the shop
   domain, and enable friendly URLs.
3. Clone this repository into `modules/somcp` (the folder name matters) and
   install the module.
4. Press **Run the diagnostic** in the module settings: every line green means
   a client can connect.

Development tools live apart from the module, so they never touch its
`vendor/` folder:

```bash
composer install --working-dir=dev/tools
```

## Checks

CI runs all of these on every pull request. Run them before pushing:

```bash
# PrestaShop coding standard
dev/tools/vendor/bin/php-cs-fixer fix --dry-run --diff

# Static analysis, against a PrestaShop folder
_PS_ROOT_DIR_=/path/to/prestashop dev/tools/vendor/bin/phpstan analyse -c phpstan.dist.neon

# Every visible string in the four languages
php .github/scripts/check-translations.php .
```

CI also lints every file on PHP 8.1 to 8.4 and compiles `config/services.yml`
with the Symfony versions of PrestaShop 8 (4.4) and 9 (6.4), with and without
what PrestaShop 9 adds. That last check exists because a service asking for a
PrestaShop 9 class once took the whole PrestaShop 8 back office down.

---

## Rules of the code

- **PHP 8.1, PrestaShop 8.0.0 to 9.x.** No PrestaShop 9 class in a type hint, a
  constructor or a `use` that runs on 8. Check `method_exists()` or
  `class_exists()` where the two differ, and say why in a comment.
- **Comments in English, saying why.** The reader can see what the line does.
  A comment that became wrong is fixed in the same change.
- **Every PHP file of the module** starts with the license header and refuses to run outside
  PrestaShop (`if (!defined('_PS_VERSION_')) { exit; }`): the Addons validator
  checks both.
- **Reuse before writing.** If the logic exists somewhere in the module, call
  it. A second version of the same rule is how two parts of the module end up
  disagreeing.
- **Nothing secret in the log**, in an error message or in an exception: no
  token, no code, no client secret, not even truncated.
- **The shop comes from the token**, never from an argument. The employee's
  rights are checked on every call.

## Tools

A tool is described in [docs/TOOLS.md](docs/TOOLS.md) before it is written:
open a pull request with the entry alone if you want feedback first. It follows
the [shared conventions](docs/TOOLS.md#shared-conventions), is tied to the back
office page where a merchant does the same thing by hand, and is one file in
`tools/`.

Reads first. A write changes one object per call, refuses a call that would
change nothing (that is what stops a repeated call from mailing a customer
twice), and goes through PrestaShop's own objects so every hook fires.

Tools for your own module do not belong here: publish them from your module,
see [docs/EXTENDING.md](docs/EXTENDING.md).

## Translations

No text a merchant can see is written in a template or a class without going
through the translation system, domain `Modules.Somcp.Admin`. Tool and schema
descriptions are the exception: the model reads them, so they stay in English.

The four catalogues in `translations/` are generated from one table:

1. Add the English string and its French, Spanish and German forms to
   `dev/translations.py`.
2. Run `python3 dev/translations.py`.
3. Commit the table and the regenerated files together.

Never edit an XLF file by hand: CI regenerates them and fails on any
difference. Not sure of a translation? Write your best attempt and say so in
the pull request.

A new language is a new column in the table and a new entry in `LANGS`.

## Version 1.0.0

The module stays at 1.0.0 until its first release on the Addons marketplace.
So, for now:

- no version bump and no file in `upgrade/`;
- a change to the database schema goes in `sql/install.sql` and
  `sql/uninstall.sql` only (testers reinstall);
- `CHANGELOG.md` has one entry, `1.0.0 - Initial version`: add your change in
  the section it belongs to.

---

## Pull requests

A good pull request:

- does one thing, and says what and why in a few sentences;
- says on which PrestaShop versions it was tried, multistore or not, and with
  which MCP client;
- updates `docs/TOOLS.md`, `docs/EXTENDING.md` or `docs/ARCHITECTURE.md` when
  it changes what they describe;
- passes CI.

Commits are yours, under your name. Keep the history readable: squash the
"fix typo" commits before asking for review.

**Welcome:** tools from the roadmap, tests, fixes with a way to reproduce them,
client reports, translations, documentation that was wrong or missing.

**Probably declined:** a dependency added to the module (it ships with none, on
purpose), a tool that does several unrelated things, a write that acts on many
objects at once, tools specific to one third-party module (publish those from
that module), reformatting unrelated code.

## Building the Addons package

Files only useful to developers are marked `export-ignore` in
`.gitattributes`, so the package is built straight from git:

```bash
git archive --format=zip --prefix=somcp/ -o somcp.zip HEAD
```

## Security

Found a vulnerability? Do not open an issue or a pull request: see
[SECURITY.md](SECURITY.md).
