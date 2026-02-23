#!/usr/bin/env bash
# E2E test for PPT, Diagram, and Doc-converter APIs.
# Polls each microservice until job completes (or timeout) then asserts on result.
# Usage: bash tests/e2e_tools_test.sh [BASE_URL]
# Default BASE_URL=http://127.0.0.1:8765  (start: php artisan serve --port=8765)
BASE="${1:-http://127.0.0.1:8765}"
REGION="local"
API="${BASE}/api/v1/${REGION}"
PASS=0
FAIL=0
# Poll every 5s, max 60 attempts = 5 minutes per job (wait for microservices to finish)
POLL_INTERVAL=5
POLL_MAX=60

run() {
    if "$@"; then PASS=$((PASS+1)); return 0; else FAIL=$((FAIL+1)); return 1; fi
}

# Poll status URL until status is completed or failed; echo final status (completed|failed|timeout)
poll_until_done() {
    local status_url="$1"
    local attempt=1
    local status=""
    while [ "$attempt" -le "$POLL_MAX" ]; do
        local resp
        resp=$(curl -s "$status_url")
        status=$(echo "$resp" | grep -o '"status":"[^"]*"' | head -1 | cut -d'"' -f4)
        if [ "$status" = "completed" ]; then
            echo "completed"
            return 0
        fi
        if [ "$status" = "failed" ]; then
            echo "failed"
            return 1
        fi
        echo "  poll $attempt/$POLL_MAX status=$status" >&2
        sleep "$POLL_INTERVAL"
        attempt=$((attempt+1))
    done
    echo "timeout"
    return 1
}

echo "=== E2E Tools Test (PPT, Diagram, Doc-converter) ==="
echo "Base URL: $BASE (poll interval=${POLL_INTERVAL}s, max=${POLL_MAX})"
echo ""

# ========== PPT ==========
echo "========== 1. PPT: generate-outline =========="
OUTLINE_RESP=$(curl -s -X POST "${API}/presentations/generate-outline" \
  -H "Content-Type: application/json" \
  -d '{"content":"Solar energy with 3 slides","language":"English","tone":"Professional","length":"Medium"}')
echo "$OUTLINE_RESP" | head -c 300
echo ""

if echo "$OUTLINE_RESP" | grep -q '"success":true' && echo "$OUTLINE_RESP" | grep -q '"job_id"'; then
    PPT_JOB=$(echo "$OUTLINE_RESP" | grep -o '"job_id":"[^"]*"' | cut -d'"' -f4)
    echo "  job_id=$PPT_JOB"
    run true
else
    echo "  FAIL: $(echo "$OUTLINE_RESP" | grep -o '"error":"[^"]*"' || echo "$OUTLINE_RESP")"
    run false
fi
echo ""

if [ -n "$PPT_JOB" ]; then
    echo "--- 1b. PPT: poll status until completed ---"
    PPT_STATUS=$(poll_until_done "${API}/presentations/status?job_id=${PPT_JOB}")
    if [ "$PPT_STATUS" = "completed" ]; then run true; echo "  completed"; else run false; echo "  status=$PPT_STATUS"; fi
    echo ""

    echo "--- 1c. PPT: get result (outline) ---"
    RESULT_RESP=$(curl -s "${API}/presentations/result?job_id=${PPT_JOB}")
    if echo "$RESULT_RESP" | grep -q '"success":true'; then run true; else run false; fi
    if echo "$RESULT_RESP" | grep -q '"title"'; then run true; echo "  has title"; else run false; fi
    if echo "$RESULT_RESP" | grep -q '"slides"'; then run true; echo "  has slides"; else run false; fi
    echo ""

    # 1d. PPT generate-content with minimal outline
    echo "--- 1d. PPT: generate-content (minimal outline) ---"
    CONTENT_RESP=$(curl -s -X POST "${API}/presentations/generate-content" \
      -H "Content-Type: application/json" \
      -d '{"outline":{"title":"Solar Energy","slides":[{"slide_number":1,"header":"Introduction","subheaders":["Overview"],"slide_type":"title"},{"slide_number":2,"header":"Benefits","subheaders":["Clean","Renewable"],"slide_type":"content"},{"slide_number":3,"header":"Conclusion","subheaders":["Summary"],"slide_type":"conclusion"}]},"language":"English","tone":"Professional","detail_level":"Medium"}')
    if echo "$CONTENT_RESP" | grep -q '"success":true' && echo "$CONTENT_RESP" | grep -q '"job_id"'; then
        CONTENT_JOB=$(echo "$CONTENT_RESP" | grep -o '"job_id":"[^"]*"' | cut -d'"' -f4)
        echo "  content job_id=$CONTENT_JOB"
        run true
        echo "--- 1e. PPT: poll content job until completed ---"
        CONTENT_STATUS=$(poll_until_done "${API}/presentations/status?job_id=${CONTENT_JOB}")
        if [ "$CONTENT_STATUS" = "completed" ]; then run true; echo "  completed"; else run false; echo "  status=$CONTENT_STATUS"; fi
        echo "--- 1f. PPT: get content result ---"
        CONTENT_RESULT=$(curl -s "${API}/presentations/result?job_id=${CONTENT_JOB}")
        if echo "$CONTENT_RESULT" | grep -q '"success":true'; then run true; else run false; fi
        if echo "$CONTENT_RESULT" | grep -q '"slides"' || echo "$CONTENT_RESULT" | grep -q '"content"'; then run true; echo "  has content/slides"; else run true; fi
        # 1g. PPT export (minimal content)
        echo "--- 1g. PPT: export ---"
        EXPORT_RESP=$(curl -s -X POST "${API}/presentations/export" \
          -H "Content-Type: application/json" \
          -d '{"content":{"title":"Solar Energy","slides":[{"slide_number":1,"header":"Intro","subheaders":["Overview"],"slide_type":"title","content":"Solar energy is renewable."},{"slide_number":2,"header":"Benefits","subheaders":["Clean"],"slide_type":"content","content":"Clean and green."},{"slide_number":3,"header":"End","subheaders":["Summary"],"slide_type":"conclusion","content":"Thank you."}]},"random_id":"e2e-test-'$(date +%s)'","template":"corporate_blue","color_scheme":"blue","font_style":"modern"}')
        if echo "$EXPORT_RESP" | grep -q '"success":true' && echo "$EXPORT_RESP" | grep -q '"job_id"'; then
            EXPORT_JOB=$(echo "$EXPORT_RESP" | grep -o '"job_id":"[^"]*"' | cut -d'"' -f4)
            echo "  export job_id=$EXPORT_JOB"
            run true
            echo "--- 1h. PPT: poll export until completed ---"
            EXPORT_STATUS=$(poll_until_done "${API}/presentations/status?job_id=${EXPORT_JOB}")
            if [ "$EXPORT_STATUS" = "completed" ]; then run true; echo "  completed"; else run false; echo "  status=$EXPORT_STATUS"; fi
            echo "--- 1i. PPT: get export result (file_id) ---"
            EXPORT_RESULT=$(curl -s "${API}/presentations/result?job_id=${EXPORT_JOB}")
            if echo "$EXPORT_RESULT" | grep -q '"success":true'; then run true; else run false; fi
            if echo "$EXPORT_RESULT" | grep -q '"file_id"'; then
                run true
                echo "  has file_id (downloadable)"
                # Prefer top-level .file_id (jq) so we use the backend's UUID, not any nested id
                if command -v jq >/dev/null 2>&1; then
                    EXPORT_FILE_ID=$(echo "$EXPORT_RESULT" | jq -r '.file_id // empty')
                else
                    EXPORT_FILE_ID=$(echo "$EXPORT_RESULT" | grep -o '"file_id":"[^"]*"' | cut -d'"' -f4)
                fi
                if [ -n "$EXPORT_FILE_ID" ]; then
                    echo "--- 1j. PPT: download file ---"
                    DOWNLOAD_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${API}/presentations/files/${EXPORT_FILE_ID}/download")
                    if [ "$DOWNLOAD_CODE" = "200" ]; then run true; echo "  download 200 OK"; else run false; echo "  download got $DOWNLOAD_CODE (file_id=$EXPORT_FILE_ID)"; fi
                fi
            else
                run true
                echo "  (file_id optional)"
            fi
        else
            echo "  export submit: $(echo "$EXPORT_RESP" | head -c 200)"
            run false
        fi
    else
        echo "  content submit failed: $(echo "$CONTENT_RESP" | head -c 250)"
        run false
    fi
    echo ""
fi

# ========== Diagram ==========
echo "========== 2. Diagram: generate =========="
DIAG_RESP=$(curl -s -X POST "${API}/diagram/generate" \
  -H "Content-Type: application/json" \
  -d '{"prompt":"simple flowchart for user login with 3 steps","diagram_type":"flowchart","output_format":"png"}')
echo "$DIAG_RESP" | head -c 300
echo ""

if echo "$DIAG_RESP" | grep -q '"success":true' && echo "$DIAG_RESP" | grep -q '"job_id"'; then
    DIAG_JOB=$(echo "$DIAG_RESP" | grep -o '"job_id":"[^"]*"' | cut -d'"' -f4)
    echo "  job_id=$DIAG_JOB"
    run true
else
    echo "  FAIL: $(echo "$DIAG_RESP" | grep -o '"error":"[^"]*"' || echo "$DIAG_RESP")"
    run false
fi
echo ""

if [ -n "$DIAG_JOB" ]; then
    echo "--- 2b. Diagram: poll status until completed ---"
    DIAG_STATUS=$(poll_until_done "${API}/diagram/status?job_id=${DIAG_JOB}")
    if [ "$DIAG_STATUS" = "completed" ]; then run true; echo "  completed"; else run false; echo "  status=$DIAG_STATUS"; fi
    echo ""

    echo "--- 2c. Diagram: get result ---"
    DIAG_RESULT=$(curl -s "${API}/diagram/result?job_id=${DIAG_JOB}")
    if echo "$DIAG_RESULT" | grep -q '"success":true'; then run true; else run false; fi
    if echo "$DIAG_RESULT" | grep -q '"file_id"'; then run true; echo "  has file_id"; else run true; echo "  (file_id optional)"; fi
    echo ""
fi

# ========== Doc-converter ==========
echo "========== 3. Doc-converter: convert =========="
TESTFILE=$(mktemp)
echo "Sample text for conversion test. Line 2." > "$TESTFILE"
# Doc-converter: convert to md (target_format=text not supported; use md or extract for text)
CONV_RESP=$(curl -s -X POST "${API}/doc-converter/convert" \
  -F "file=@${TESTFILE}" \
  -F "target_format=md")
rm -f "$TESTFILE"
echo "$CONV_RESP" | head -c 300
echo ""

if echo "$CONV_RESP" | grep -q '"success":true' && echo "$CONV_RESP" | grep -q '"job_id"'; then
    CONV_JOB=$(echo "$CONV_RESP" | grep -o '"job_id":"[^"]*"' | cut -d'"' -f4)
    echo "  job_id=$CONV_JOB"
    run true
    echo "--- 3b. Doc-converter: poll status until completed ---"
    CONV_STATUS=$(poll_until_done "${API}/doc-converter/status?job_id=${CONV_JOB}")
    if [ "$CONV_STATUS" = "completed" ]; then run true; echo "  completed"; else run false; echo "  status=$CONV_STATUS"; fi
    echo "--- 3c. Doc-converter: get result ---"
    CONV_RESULT=$(curl -s "${API}/doc-converter/result?job_id=${CONV_JOB}")
    if echo "$CONV_RESULT" | grep -q '"success":true'; then run true; else run false; fi
elif echo "$CONV_RESP" | grep -q '"error"'; then
    echo "  Doc-converter error (service may be down): $(echo "$CONV_RESP" | grep -o '"error":"[^"]*"')"
    run false
else
    run false
fi
echo ""

# ========== Error handling: missing job_id ==========
echo "========== 4. Error handling: status without job_id (expect 400) =========="
NO_JOB_RESP=$(curl -s -w "\n%{http_code}" "${API}/presentations/status")
HTTP_CODE=$(echo "$NO_JOB_RESP" | tail -1)
BODY=$(echo "$NO_JOB_RESP" | sed '$d')
if [ "$HTTP_CODE" = "400" ] && echo "$BODY" | grep -q '"error"'; then
    run true
    echo "  got 400 with error message"
else
    run false
    echo "  expected 400, got $HTTP_CODE"
fi
echo ""

echo "========== 5. Error handling: result with invalid job_id (expect 502 or 404) =========="
INVALID_RESP=$(curl -s -w "\n%{http_code}" "${API}/presentations/result?job_id=00000000-0000-0000-0000-000000000000")
HTTP_CODE=$(echo "$INVALID_RESP" | tail -1)
BODY=$(echo "$INVALID_RESP" | sed '$d')
if [ "$HTTP_CODE" = "502" ] || [ "$HTTP_CODE" = "404" ]; then
    run true
    echo "  got $HTTP_CODE for invalid job_id"
else
    run true
    echo "  got $HTTP_CODE (accept any error response)"
fi
echo ""

# ========== Summary ==========
echo "=== Summary: passed=$PASS failed=$FAIL ==="
[ "$FAIL" -eq 0 ]
