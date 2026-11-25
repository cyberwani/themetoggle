#!/usr/bin/env bash
# Fails when languages/themetoggle.pot is missing strings from the source, or has
# strings that no longer exist. Compares the translatable entries only (context,
# singular, plural), so line-number references and header dates do not matter.
# Requires WP-CLI's i18n command (installed by `composer install`).
set -euo pipefail
cd "$(dirname "$0")/.."

committed=languages/themetoggle.pot
fresh=$(mktemp)
trap 'rm -f "$fresh"' EXIT

wp i18n make-pot . "$fresh" --slug=themetoggle --domain=themetoggle \
	--exclude=vendor,node_modules,tests,build,docs,bin >/dev/null

entries() {
	# Join msgctxt/msgid/msgid_plural continuation lines, then keep one line per entry.
	awk '
		/^(msgctxt|msgid|msgid_plural) / { if (cur != "") print cur; cur = $0; next }
		/^"/ && cur != "" { cur = cur $0; next }
		{ if (cur != "") print cur; cur = "" }
		END { if (cur != "") print cur }
	' "$1" | grep -v '^msgid ""$' | sort -u
}

if diff <(entries "$committed") <(entries "$fresh"); then
	echo "POT is up to date."
else
	echo "POT is out of date: run 'composer make-pot' and commit languages/themetoggle.pot." >&2
	exit 1
fi
