#!/usr/bin/env bash
set -euo pipefail

API_BASE="${API_BASE:-http://localhost:8080/api/v1}"
ACTOR_ID="${ACTOR_ID:-opening-smoke-actor}"
ACTOR_SUBJECT="${ACTOR_SUBJECT:-sso|opening-smoke-user}"
PROFILE_ID="${PROFILE_ID:-profile-opening-smoke-1}"
PRICE_MINOR="${PRICE_MINOR:-2200}"
PRICE_CURRENCY="${PRICE_CURRENCY:-EUR}"

TMP_BODY="$(mktemp)"
trap 'rm -f "$TMP_BODY"' EXIT

RESPONSE_STATUS=""
RESPONSE_BODY=""

log() {
  printf '\n==> %s\n' "$1"
}

fail() {
  printf 'ERROR: %s\n' "$1" >&2
  if [[ -n "${RESPONSE_BODY:-}" ]]; then
    printf 'Last response body:\n%s\n' "$RESPONSE_BODY" >&2
  fi
  exit 1
}

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || {
    printf 'Missing required command: %s\n' "$1" >&2
    exit 1
  }
}

uuid() {
  if [[ -r /proc/sys/kernel/random/uuid ]]; then
    cat /proc/sys/kernel/random/uuid
    return
  fi

  if command -v uuidgen >/dev/null 2>&1; then
    uuidgen
    return
  fi

  # Portable fallback (e.g. Git Bash on Windows): build a v4 UUID from urandom.
  od -An -N16 -tx1 /dev/urandom | tr -d ' \n' | awk '{printf "%s-%s-4%s-8%s-%s\n", substr($0,1,8), substr($0,9,4), substr($0,14,3), substr($0,18,3), substr($0,21,12)}'
}

timestamp_in_seconds() {
  local seconds="$1"
  date -u -d "@$seconds" '+%Y-%m-%dT%H:%M:%SZ'
}

api_call() {
  local method="$1"
  local url="$2"
  local body="$3"
  shift 3

  local -a curl_args=(
    -sS
    -X "$method"
    "$url"
    -H 'Accept: application/json'
    -o "$TMP_BODY"
    -w '%{http_code}'
  )

  # The API key gate requires X-Api-Key on every /api/v1 request.
  if [[ -n "${API_KEY:-}" ]]; then
    curl_args+=(-H "X-Api-Key: $API_KEY")
  fi

  if [[ -n "$body" ]]; then
    curl_args+=(
      -H 'Content-Type: application/json'
      --data "$body"
    )
  fi

  curl_args+=("$@")

  RESPONSE_STATUS="$(curl "${curl_args[@]}")"
  RESPONSE_BODY="$(cat "$TMP_BODY")"
}

assert_status() {
  local expected="$1"
  [[ "$RESPONSE_STATUS" == "$expected" ]] || fail "Expected HTTP $expected but got $RESPONSE_STATUS."
}

json_get() {
  local expr="$1"
  printf '%s' "$RESPONSE_BODY" | jq -r "$expr"
}

assert_json_true() {
  local expr="$1"
  local result
  result="$(printf '%s' "$RESPONSE_BODY" | jq -r "$expr")"
  [[ "$result" == "true" ]] || fail "Expected jq expression to evaluate to true: $expr"
}

psql_exec() {
  local sql="$1"
  printf '%s\n' "$sql" | docker compose exec -T postgres sh -lc 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"' >/dev/null
}

pretty_print_response() {
  printf 'HTTP %s\n' "$RESPONSE_STATUS"
  printf '%s\n' "$RESPONSE_BODY" | jq .
}

require_cmd curl
require_cmd jq
require_cmd docker

log "Checking Docker and backend availability"
docker compose ps >/dev/null
curl -sS "$API_BASE/me" >/dev/null || true

provider_headers=(
  -H "X-Actor-Id: $ACTOR_ID"
  -H "X-Actor-Subject: $ACTOR_SUBJECT"
  -H "X-Actor-Roles: provider"
  -H "X-User-Profile-Id: $PROFILE_ID"
)

log "Creating individual provider through API"
provider_idempotency_key="provider-create-$(uuid)"
provider_payload="$(jq -nc '{provider_type:"individual"}')"
api_call "POST" "$API_BASE/providers" "$provider_payload" "${provider_headers[@]}" -H "Idempotency-Key: $provider_idempotency_key"
assert_status 201
provider_id="$(json_get '.data.provider_id')"
[[ -n "$provider_id" && "$provider_id" != "null" ]] || fail "Provider id is missing from response."
pretty_print_response

log "Seeding active service offering directly in Postgres"
offering_id="$(uuid)"
now_iso="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
psql_exec "
INSERT INTO service_offerings (
  id,
  provider_id,
  name,
  description,
  duration_minutes,
  price_amount,
  price_currency,
  status,
  created_at,
  updated_at
) VALUES (
  '$offering_id',
  '$provider_id',
  'Openings Smoke Service',
  NULL,
  30,
  $PRICE_MINOR,
  '$PRICE_CURRENCY',
  'active',
  '$now_iso',
  '$now_iso'
);
"
printf 'Seeded offering_id=%s for provider_id=%s\n' "$offering_id" "$provider_id"

now_epoch="$(date -u +%s)"
opening_a_start="$(timestamp_in_seconds "$((now_epoch + 7200))")"
opening_a_end="$(timestamp_in_seconds "$((now_epoch + 9000))")"
opening_b_start="$(timestamp_in_seconds "$((now_epoch + 10800))")"
opening_b_end="$(timestamp_in_seconds "$((now_epoch + 12600))")"

log "Creating draft opening A"
opening_a_create_key="opening-a-create-$(uuid)"
opening_a_payload="$(jq -nc \
  --arg offering_id "$offering_id" \
  --arg starts_at "$opening_a_start" \
  --arg ends_at "$opening_a_end" \
  --arg currency "$PRICE_CURRENCY" \
  --argjson amount_minor "$PRICE_MINOR" \
  '{
    service_offering_id: $offering_id,
    starts_at: $starts_at,
    ends_at: $ends_at,
    price_override: {
      currency: $currency,
      amount_minor: $amount_minor
    }
  }'
)"
api_call "POST" "$API_BASE/providers/$provider_id/openings" "$opening_a_payload" "${provider_headers[@]}" -H "Idempotency-Key: $opening_a_create_key"
assert_status 201
opening_a_id="$(json_get '.data.opening_id')"
[[ -n "$opening_a_id" && "$opening_a_id" != "null" ]] || fail "Opening A id is missing from response."
assert_json_true '.data.status == "draft"'
assert_json_true '.meta.idempotency_replayed == false'
pretty_print_response

log "Replaying draft opening A create with same idempotency key"
api_call "POST" "$API_BASE/providers/$provider_id/openings" "$opening_a_payload" "${provider_headers[@]}" -H "Idempotency-Key: $opening_a_create_key"
assert_status 201
assert_json_true '.meta.idempotency_replayed == true'
[[ "$(json_get '.data.opening_id')" == "$opening_a_id" ]] || fail "Opening A replay returned a different opening_id."
pretty_print_response

log "Listing provider draft openings"
api_call "GET" "$API_BASE/providers/$provider_id/openings?status=draft&limit=20" "" "${provider_headers[@]}"
assert_status 200
draft_count="$(printf '%s' "$RESPONSE_BODY" | jq -r --arg opening_id "$opening_a_id" '.data | map(select(.opening_id == $opening_id and .status == "draft")) | length')"
[[ "$draft_count" == "1" ]] || fail "Expected opening A to appear exactly once in draft list."
pretty_print_response

log "Fetching opening A by id"
api_call "GET" "$API_BASE/providers/$provider_id/openings/$opening_a_id" "" "${provider_headers[@]}"
assert_status 200
[[ "$(json_get '.data.opening_id')" == "$opening_a_id" ]] || fail "Fetched opening id does not match opening A."
assert_json_true '.data.status == "draft"'
pretty_print_response

log "Publishing opening A"
opening_a_publish_key="opening-a-publish-$(uuid)"
api_call "POST" "$API_BASE/providers/$provider_id/openings/$opening_a_id:publish" "{}" "${provider_headers[@]}" -H "Idempotency-Key: $opening_a_publish_key"
assert_status 200
assert_json_true '.data.status == "published"'
assert_json_true '.data.published_at != null'
pretty_print_response

log "Replaying opening A publish with same idempotency key"
api_call "POST" "$API_BASE/providers/$provider_id/openings/$opening_a_id:publish" "{}" "${provider_headers[@]}" -H "Idempotency-Key: $opening_a_publish_key"
assert_status 200
assert_json_true '.meta.idempotency_replayed == true'
[[ "$(json_get '.data.opening_id')" == "$opening_a_id" ]] || fail "Opening A publish replay returned a different opening_id."
pretty_print_response

log "Listing public openings for this provider"
api_call "GET" "$API_BASE/public/openings?provider_id=$provider_id&service_offering_id=$offering_id&limit=20" "" 
assert_status 200
public_count="$(printf '%s' "$RESPONSE_BODY" | jq -r --arg opening_id "$opening_a_id" '.data | map(select(.opening_id == $opening_id and .status == "published")) | length')"
[[ "$public_count" == "1" ]] || fail "Expected opening A to appear exactly once in public list."
pretty_print_response

log "Creating draft opening B for cancel test"
opening_b_create_key="opening-b-create-$(uuid)"
opening_b_payload="$(jq -nc \
  --arg offering_id "$offering_id" \
  --arg starts_at "$opening_b_start" \
  --arg ends_at "$opening_b_end" \
  --arg currency "$PRICE_CURRENCY" \
  --argjson amount_minor "$PRICE_MINOR" \
  '{
    service_offering_id: $offering_id,
    starts_at: $starts_at,
    ends_at: $ends_at,
    price_override: {
      currency: $currency,
      amount_minor: $amount_minor
    }
  }'
)"
api_call "POST" "$API_BASE/providers/$provider_id/openings" "$opening_b_payload" "${provider_headers[@]}" -H "Idempotency-Key: $opening_b_create_key"
assert_status 201
opening_b_id="$(json_get '.data.opening_id')"
[[ -n "$opening_b_id" && "$opening_b_id" != "null" ]] || fail "Opening B id is missing from response."
assert_json_true '.data.status == "draft"'
pretty_print_response

log "Cancelling draft opening B"
opening_b_cancel_key="opening-b-cancel-$(uuid)"
api_call "POST" "$API_BASE/providers/$provider_id/openings/$opening_b_id:cancel" "{}" "${provider_headers[@]}" -H "Idempotency-Key: $opening_b_cancel_key"
assert_status 200
assert_json_true '.data.status == "cancelled_by_provider"'
assert_json_true '.data.cancelled_at != null'
pretty_print_response

log "Replaying opening B cancel with same idempotency key"
api_call "POST" "$API_BASE/providers/$provider_id/openings/$opening_b_id:cancel" "{}" "${provider_headers[@]}" -H "Idempotency-Key: $opening_b_cancel_key"
assert_status 200
assert_json_true '.meta.idempotency_replayed == true'
[[ "$(json_get '.data.opening_id')" == "$opening_b_id" ]] || fail "Opening B cancel replay returned a different opening_id."
pretty_print_response

log "Listing all provider openings after publish and cancel"
api_call "GET" "$API_BASE/providers/$provider_id/openings?limit=20" "" "${provider_headers[@]}"
assert_status 200
published_count="$(printf '%s' "$RESPONSE_BODY" | jq -r --arg opening_id "$opening_a_id" '.data | map(select(.opening_id == $opening_id and .status == "published")) | length')"
cancelled_count="$(printf '%s' "$RESPONSE_BODY" | jq -r --arg opening_id "$opening_b_id" '.data | map(select(.opening_id == $opening_id and .status == "cancelled_by_provider")) | length')"
[[ "$published_count" == "1" ]] || fail "Expected opening A to remain published in provider list."
[[ "$cancelled_count" == "1" ]] || fail "Expected opening B to appear cancelled in provider list."
pretty_print_response

log "Openings smoke test completed successfully"
printf 'provider_id=%s\n' "$provider_id"
printf 'service_offering_id=%s\n' "$offering_id"
printf 'opening_a_id=%s (published)\n' "$opening_a_id"
printf 'opening_b_id=%s (cancelled_by_provider)\n' "$opening_b_id"
