#!/usr/bin/env bash
set -euo pipefail

# Simple curl-based smoke test for SPA cookie auth and token auth.
#
# Usage:
#   scripts/curl-auth-smoke.sh [BASE_URL] [EMAIL] [PASSWORD]
#
# Examples:
#   scripts/curl-auth-smoke.sh               # defaults: http://localhost demo@anchorless.dev password
#   scripts/curl-auth-smoke.sh http://localhost demo@anchorless.dev password

BASE_URL=${1:-"http://localhost"}
EMAIL=${2:-"demo@anchorless.dev"}
PASSWORD=${3:-"password"}

API_BASE="$BASE_URL/api"
STATEFUL_ORIGIN=${STATEFUL_ORIGIN:-"http://localhost:5173"}

WORK_DIR="$(mktemp -d)"
COOKIE_JAR="$WORK_DIR/cookiejar.txt"

cleanup() {
  rm -rf "$WORK_DIR" >/dev/null 2>&1 || true
}
trap cleanup EXIT

say() { printf "\n=== %s\n" "$*"; }

urldecode() {
  local s=${1//+/ }               # + to space
  printf '%b' "${s//%/\\x}" 2>/dev/null || true
}

get_xsrf() {
  local raw
  raw=$(awk '$6=="XSRF-TOKEN"{print $7}' "$COOKIE_JAR" | tail -n1)
  urldecode "$raw"
}

curl_json() {
  # Args: method url [json_body]
  local method=$1; shift
  local url=$1; shift
  local data=${1:-}
  local upper
  upper=$(printf '%s' "$method" | tr '[:lower:]' '[:upper:]')
  local XSRF_OPT=""
  if [[ "$upper" == "POST" || "$upper" == "PUT" || "$upper" == "PATCH" || "$upper" == "DELETE" ]]; then
    # Re-read XSRF from cookie jar to handle regeneration on login
    local xsrf
    xsrf=$(get_xsrf)
    if [[ -n "$xsrf" ]]; then
      XSRF_OPT="-H X-XSRF-TOKEN: $xsrf"
    fi
  fi
  if [[ -n "$data" ]]; then
    curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" -H 'Accept: application/json' -H 'Content-Type: application/json' \
      -H "Origin: $STATEFUL_ORIGIN" -H "Referer: $STATEFUL_ORIGIN/" ${XSRF_OPT:+-H "$XSRF_OPT"} \
      -X "$method" "$url" --data "$data"
  else
    curl -sS -b "$COOKIE_JAR" -c "$COOKIE_JAR" -H 'Accept: application/json' \
      -H "Origin: $STATEFUL_ORIGIN" -H "Referer: $STATEFUL_ORIGIN/" ${XSRF_OPT:+-H "$XSRF_OPT"} \
      -X "$method" "$url"
  fi
}

status_of() {
  local method=$1; shift
  local url=$1; shift
  local data=${1:-}
  local upper
  upper=$(printf '%s' "$method" | tr '[:lower:]' '[:upper:]')
  local XSRF_OPT=""
  if [[ "$upper" == "POST" || "$upper" == "PUT" || "$upper" == "PATCH" || "$upper" == "DELETE" ]]; then
    local xsrf
    xsrf=$(get_xsrf)
    if [[ -n "$xsrf" ]]; then
      XSRF_OPT="-H X-XSRF-TOKEN: $xsrf"
    fi
  fi
  if [[ -n "$data" ]]; then
    curl -sS -o /dev/null -w '%{http_code}' -b "$COOKIE_JAR" -c "$COOKIE_JAR" -H 'Accept: application/json' -H 'Content-Type: application/json' \
      -H "Origin: $STATEFUL_ORIGIN" -H "Referer: $STATEFUL_ORIGIN/" ${XSRF_OPT:+-H "$XSRF_OPT"} \
      -X "$method" "$url" --data "$data"
  else
    curl -sS -o /dev/null -w '%{http_code}' -b "$COOKIE_JAR" -c "$COOKIE_JAR" -H 'Accept: application/json' \
      -H "Origin: $STATEFUL_ORIGIN" -H "Referer: $STATEFUL_ORIGIN/" ${XSRF_OPT:+-H "$XSRF_OPT"} \
      -X "$method" "$url"
  fi
}

say "1) Get CSRF cookie ($BASE_URL/sanctum/csrf-cookie)"
curl -sS -o /dev/null -w 'HTTP %{http_code}\n' -c "$COOKIE_JAR" \
  -H "Origin: $STATEFUL_ORIGIN" -H "Referer: $STATEFUL_ORIGIN/" \
  "$BASE_URL/sanctum/csrf-cookie"

XSRF_DECODED=$(get_xsrf)
if [[ -z "$XSRF_DECODED" ]]; then
  echo "Failed to read XSRF-TOKEN from cookie jar" >&2
  exit 1
fi

say "2) Session login ($BASE_URL/login) as $EMAIL"
LOGIN_STATUS=$(curl -sS -o /dev/null -w '%{http_code}' -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -H "X-XSRF-TOKEN: $XSRF_DECODED" \
  -H "Origin: $STATEFUL_ORIGIN" -H "Referer: $STATEFUL_ORIGIN/" \
  -X POST "$BASE_URL/login" \
  --data "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")
echo "HTTP $LOGIN_STATUS"
[[ "$LOGIN_STATUS" == "200" ]] || { echo "Login failed" >&2; exit 1; }

# XSRF may be regenerated at login; refresh from cookie jar
XSRF_DECODED=$(get_xsrf)

say "3) Auth check via cookie (GET $API_BASE/auth/me)"
curl_json GET "$API_BASE/auth/me" | sed -e 's/{/\n&/;s/,/\n&/g' | sed 's/^/  /'

say "4) List visa applications (GET $API_BASE/visa-applications)"
curl_json GET "$API_BASE/visa-applications" | sed -e 's/{/\n&/;s/,/\n&/g' | sed 's/^/  /'

say "5) Create a new application (POST $API_BASE/visa-applications)"
CREATE_STATUS=$(status_of POST "$API_BASE/visa-applications" '{"country":"US"}')
echo "HTTP $CREATE_STATUS"

say "6) List again (GET $API_BASE/visa-applications)"
curl_json GET "$API_BASE/visa-applications" | sed -e 's/{/\n&/;s/,/\n&/g' | sed 's/^/  /'

say "7) Create token (POST $API_BASE/auth/token/create)"
TOKEN_JSON=$(curl_json POST "$API_BASE/auth/token/create" "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\",\"device_name\":\"curl-smoke\"}")
TOKEN=$(echo "$TOKEN_JSON" | awk -F '"token"\s*:\s*"' 'NF>1{print $2}' | awk -F '"' '{print $1}')
if [[ -z "$TOKEN" ]]; then
  echo "Failed to create token" >&2
  echo "$TOKEN_JSON" >&2
  exit 1
fi
echo "  Bearer token: ${TOKEN:0:8}…"

say "8) Auth check via Bearer (GET $API_BASE/auth/me)"
curl -sS -H 'Accept: application/json' -H "Authorization: Bearer $TOKEN" "$API_BASE/auth/me" | sed -e 's/{/\n&/;s/,/\n&/g' | sed 's/^/  /'

say "9) Revoke token (POST $API_BASE/auth/token/revoke)"
REV_STATUS=$(curl -sS -o /dev/null -w '%{http_code}' -H 'Accept: application/json' -H "Authorization: Bearer $TOKEN" \
  -X POST "$API_BASE/auth/token/revoke")
echo "HTTP $REV_STATUS"

say "10) Check token-only endpoint returns 401 after revoke (GET $API_BASE/auth/me-token)"
AFTER_STATUS=$(curl -sS -o /dev/null -w '%{http_code}' -H 'Accept: application/json' -H "Authorization: Bearer $TOKEN" \
  "$API_BASE/auth/me-token")
echo "HTTP $AFTER_STATUS"
if [[ "$AFTER_STATUS" != "401" ]]; then
  echo "Expected 401 after revoke, got $AFTER_STATUS" >&2
  exit 1
fi

say "Done."
