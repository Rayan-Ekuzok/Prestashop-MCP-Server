# Badges

The README badges, generated as local SVG files rather than served by
shields.io, so the README depends on nothing outside the repository.

The PrestaShop, PHP and version badges state facts about the module: change
them in the same pull request as `ps_versions_compliancy`, `composer.json` or
the module version.

## Regenerate

From the repository root:

```bash
npm i simple-icons
python3 .github/badges/generate.py
```

Needs Python 3 with Pillow and the DejaVu Sans font. Edit the `BADGES` table at
the bottom of `generate.py` to change a label, a message or a colour. Logos come
from `simple-icons`: the `logo` key is a file name in
`node_modules/simple-icons/icons/`.

Other paths can be given with `SIMPLE_ICONS=/path/to/icons` and
`BADGE_FONT=/path/to/font.ttf`.
