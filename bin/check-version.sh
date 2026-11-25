#!/usr/bin/env bash
# Checks that every place holding the plugin version agrees.
# Usage: bin/check-version.sh [expected-version]   (e.g. 1.0.1; CI passes the tag without "v")
set -euo pipefail
cd "$(dirname "$0")/.."

header=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' themetoggle.php | head -1)
constant=$(sed -n "s/^const VERSION[[:space:]]*=[[:space:]]*'\([^']*\)'.*/\1/p" themetoggle.php | head -1)
stable=$(sed -n 's/^Stable tag:[[:space:]]*\([^[:space:]]*\).*/\1/p' readme.txt | head -1)
package=$(sed -n 's/^[[:space:]]*"version":[[:space:]]*"\([^"]*\)".*/\1/p' package.json | head -1)

expected=${1:-$header}
status=0

check() {
	if [ "$2" = "$expected" ]; then
		echo "ok    $1: $2"
	else
		echo "FAIL  $1: '$2' (expected '$expected')"
		status=1
	fi
}

check "themetoggle.php header Version" "$header"
check "themetoggle.php const VERSION" "$constant"
check "readme.txt Stable tag" "$stable"
check "package.json version" "$package"

if grep -q "^= ${expected} =$" readme.txt; then
	echo "ok    readme.txt changelog entry: = ${expected} ="
else
	echo "FAIL  readme.txt has no changelog entry '= ${expected} ='"
	status=1
fi

exit $status
