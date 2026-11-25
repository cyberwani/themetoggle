#!/usr/bin/env bash
# Shared helpers for ThemeToggle HTTP tests.
BASE=${TT_BASE:-http://127.0.0.1:8899}
WP_PATH=${TT_WP_PATH:?Set TT_WP_PATH to the WordPress root}
WP="${TT_WP_CLI:-wp} --path=$WP_PATH"
JAR=${TT_JAR:-/tmp/tt-jars}
HERE=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
mkdir -p "$JAR"
PASS=0; FAIL=0

ok()   { PASS=$((PASS+1)); echo "PASS  $1"; }
bad()  { FAIL=$((FAIL+1)); echo "FAIL  $1"; [ -n "$2" ] && echo "      $2"; }
check() { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1" "expected [$3] got [$2]"; fi; }
contains() { if grep -q -- "$3" <<<"$2"; then ok "$1"; else bad "$1" "missing [$3]"; fi; }
lacks() { if grep -q -- "$3" <<<"$2"; then bad "$1" "unexpected [$3]"; else ok "$1"; fi; }

login() { # user pass
	rm -f "$JAR/$1"
	curl -s -c "$JAR/$1" -b "wordpress_test_cookie=WP%20Cookie%20check" -o /dev/null \
		--data-urlencode "log=$1" --data-urlencode "pwd=$2" -d "testcookie=1" -d "wp-submit=Log+In" \
		"$BASE/wp-login.php"
}

get() { # user url [extra curl args]
	local u=$1 url=$2; shift 2
	if [ "$u" = "anon" ]; then curl -s "$@" "$url"; else curl -s -b "$JAR/$u" -c "$JAR/$u" "$@" "$url"; fi
}

# Theme directories referenced by stylesheet/script URLs, sorted and joined.
themes_in() { grep -oE "wp-content/themes/[A-Za-z0-9_-]+/" | sed -E 's#wp-content/themes/##; s#/##' | sort -u | paste -sd, -; }

theme_of() { get "$1" "$2" | themes_in; }

meta() { $WP user meta get "$1" tt_user_theme 2>/dev/null; }

summary() { echo; echo "RESULT: $PASS passed, $FAIL failed"; [ "$FAIL" -eq 0 ]; }
