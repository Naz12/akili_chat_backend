#!/usr/bin/env bash
# Run PPT, Diagram, and Doc-converter integration tests (standalone PHP scripts).
# These use the app bootstrap and .env (no PHPUnit SQLite). Requires DB and snazrawi@gmail.com user.
# Usage: bash tests/run_tools_tests.sh

set -e
cd "$(dirname "$0")/.."
ROOT="$(pwd)"

run_script() {
    local name="$1"
    local script="$2"
    echo ""
    echo "========== $name =========="
    if php "$script"; then
        echo "[PASS] $name"
        return 0
    else
        echo "[FAIL] $name"
        return 1
    fi
}

FAIL=0
export PPT_DIAGRAM_E2E_SKIP_POLL=1

run_script "Chat tools E2E" "tests/test_chat_tools_e2e.php" || FAIL=$((FAIL+1))
run_script "Doc-converter integration" "tests/test_doc_converter_integration.php" || FAIL=$((FAIL+1))
run_script "Chat PPT and Diagram E2E" "tests/test_chat_ppt_diagram_e2e.php" || FAIL=$((FAIL+1))
run_script "E2E Upload + Doc-converter (upload → convert → result)" "tests/test_chat_upload_doc_converter_e2e.php" || FAIL=$((FAIL+1))

echo ""
echo "========== Summary =========="
if [ "$FAIL" -eq 0 ]; then
    echo "All tool integration tests passed."
    exit 0
fi
echo "$FAIL test script(s) failed."
exit 1
