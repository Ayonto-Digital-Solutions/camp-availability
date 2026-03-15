#!/bin/bash
#
# Version Bump Checker
#
# Verifies that all version locations in the plugin are in sync.
# Run this before committing a version bump to catch missed locations.
#
# Usage: bash check-version.sh [expected-version]
#   If no version is given, the version from the main plugin header is used.
#
# Exit codes:
#   0 = all locations match
#   1 = mismatch found
#

set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
MAIN_FILE="$PLUGIN_DIR/as-camp-availability-integration.php"
README_FILE="$PLUGIN_DIR/README.md"
CHANGELOG_FILE="$PLUGIN_DIR/CHANGELOG.md"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

errors=0

# Determine expected version.
if [ -n "${1:-}" ]; then
    EXPECTED="$1"
else
    EXPECTED=$(grep -m1 '^ \* Version:' "$MAIN_FILE" | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
fi

if [ -z "$EXPECTED" ]; then
    echo -e "${RED}FEHLER: Konnte keine Version ermitteln.${NC}"
    exit 1
fi

echo "Erwartete Version: $EXPECTED"
echo "-----------------------------------"

# Helper: check a file for a pattern containing the version.
check() {
    local file="$1"
    local label="$2"
    local pattern="$3"
    local relpath="${file#"$PLUGIN_DIR"/}"

    if [ ! -f "$file" ]; then
        echo -e "${YELLOW}SKIP${NC}  $relpath ($label) — Datei nicht vorhanden"
        return
    fi

    if grep -q "$pattern" "$file"; then
        echo -e "${GREEN}OK${NC}    $relpath ($label)"
    else
        echo -e "${RED}FAIL${NC}  $relpath ($label) — Version $EXPECTED nicht gefunden"
        errors=$((errors + 1))
    fi
}

# 1. Main plugin file — header
check "$MAIN_FILE" "Plugin Header: Version" "Version:[[:space:]]*${EXPECTED}"

# 2. Main plugin file — const VERSION
check "$MAIN_FILE" "const VERSION" "const VERSION = '${EXPECTED}'"

# 3. README.md — version metadata
check "$README_FILE" "Version Metadata" "\\*\\*Version:\\*\\* ${EXPECTED}"

# 4. README.md — Aktuelle Version heading
check "$README_FILE" "Aktuelle Version" "Aktuelle Version: ${EXPECTED}"

# 5. CHANGELOG.md — latest entry should mention the version
if [ -f "$CHANGELOG_FILE" ]; then
    # Check that the FIRST version entry in the changelog matches.
    first_entry=$(grep -m1 '^\#\#\s*\[' "$CHANGELOG_FILE" | sed 's/.*\[\(.*\)\].*/\1/' | tr -d '[:space:]')
    if [ "$first_entry" = "$EXPECTED" ]; then
        echo -e "${GREEN}OK${NC}    CHANGELOG.md (Neuester Eintrag: [$first_entry])"
    else
        echo -e "${YELLOW}WARN${NC}  CHANGELOG.md — Neuester Eintrag ist [$first_entry], erwartet [$EXPECTED]"
    fi
fi

echo "-----------------------------------"

if [ "$errors" -gt 0 ]; then
    echo -e "${RED}$errors Stelle(n) nicht aktualisiert!${NC}"
    exit 1
else
    echo -e "${GREEN}Alle Versionsstellen sind synchron.${NC}"
    exit 0
fi
