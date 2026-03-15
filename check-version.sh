#!/bin/bash
#
# Version Bump & Code Hygiene Checker
#
# Verifies that all version locations in the plugin are in sync
# and checks for stale/deprecated code patterns.
#
# Usage: bash check-version.sh [expected-version]
#   If no version is given, the version from the main plugin header is used.
#
# Exit codes:
#   0 = all checks pass
#   1 = issue found
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
warnings=0

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
echo "==================================="
echo ""

# --- Helper functions ---

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

check_absent() {
    local file="$1"
    local label="$2"
    local pattern="$3"
    local relpath="${file#"$PLUGIN_DIR"/}"

    if [ ! -f "$file" ]; then
        return
    fi

    if grep -q "$pattern" "$file"; then
        echo -e "${RED}FAIL${NC}  $relpath — Veralteter Code gefunden: $label"
        errors=$((errors + 1))
    else
        echo -e "${GREEN}OK${NC}    $relpath ($label nicht vorhanden)"
    fi
}

# ===================================
echo "Versionsstellen:"
echo "-----------------------------------"

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
    first_entry=$(grep -m1 '^\#\#\s*\[' "$CHANGELOG_FILE" | sed 's/.*\[\(.*\)\].*/\1/' | tr -d '[:space:]')
    if [ "$first_entry" = "$EXPECTED" ]; then
        echo -e "${GREEN}OK${NC}    CHANGELOG.md (Neuester Eintrag: [$first_entry])"
    else
        echo -e "${YELLOW}WARN${NC}  CHANGELOG.md — Neuester Eintrag ist [$first_entry], erwartet [$EXPECTED]"
        warnings=$((warnings + 1))
    fi
fi

echo ""
echo "Veralteter Code:"
echo "-----------------------------------"

# 6. No deprecated admin notices
check_absent "$MAIN_FILE" "display_optional_plugins_notice" "display_optional_plugins_notice"

# 7. No old Koalaapps references in active code (not comments)
check_absent "$MAIN_FILE" "Koalaapps optional_plugins Referenz" "optional_plugins.*Koalaapps"

# 8. No hardcoded old domain references
for f in "$MAIN_FILE" "$PLUGIN_DIR/includes/class-as-cai-admin.php" "$PLUGIN_DIR/includes/class-as-cai-status-display.php"; do
    if [ -f "$f" ]; then
        relpath="${f#"$PLUGIN_DIR"/}"
        if grep -qi "battleground" "$f"; then
            echo -e "${RED}FAIL${NC}  $relpath — Enthält noch 'Battleground' Referenzen"
            errors=$((errors + 1))
        fi
    fi
done

# 9. No debug console.log in production JS
for jsfile in "$PLUGIN_DIR"/assets/js/*.js; do
    if [ -f "$jsfile" ]; then
        relpath="${jsfile#"$PLUGIN_DIR"/}"
        # Check for unguarded console.log (not behind isDebug check)
        unguarded=$(grep -n 'console\.log' "$jsfile" | grep -v 'isDebug\|asCaiDebug\|if.*debug\|// ' | head -5 || true)
        if [ -n "$unguarded" ]; then
            echo -e "${YELLOW}WARN${NC}  $relpath — Ungeschützte console.log Aufrufe gefunden"
            warnings=$((warnings + 1))
        fi
    fi
done

echo ""
echo "==================================="

if [ "$errors" -gt 0 ]; then
    echo -e "${RED}$errors Fehler gefunden!${NC}"
    [ "$warnings" -gt 0 ] && echo -e "${YELLOW}$warnings Warnung(en)${NC}"
    exit 1
elif [ "$warnings" -gt 0 ]; then
    echo -e "${GREEN}Alle Versionsstellen sind synchron.${NC}"
    echo -e "${YELLOW}$warnings Warnung(en)${NC}"
    exit 0
else
    echo -e "${GREEN}Alle Prüfungen bestanden.${NC}"
    exit 0
fi
