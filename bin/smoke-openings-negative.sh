#!/usr/bin/env bash
set -euo pipefail

API_BASE="${API_BASE:-http://localhost:8080/api/v1}"
PRICE_MINOR="${PRICE_MINOR:-2200}"
PRICE_CURRENCY="${PRICE_CURRENCY:-EUR}"

PROVIDER_ONE_ACTOR_ID="${PROVIDER_ONE_ACTOR_ID:-opening-neg-provider-1-actor}"
PROVIDER_ONE_SUBJECT="${PROVIDER_ONE_SUBJECT:-sso|opening-neg-provider-1}"
PROVIDER_ONE_PROFILE_ID="${PROVIDER_ONE_PROFILE_ID:-profile-opening-neg-provider-1}"

PROVIDER_TWO_ACTOR_ID="${PROVIDER_TWO_ACTOR_ID:-opening-neg-provider-2-actor}"
PROVIDER_TWO_SUBJECT="${PROVIDER_TWO_SUBJECT:-sso|opening-neg-provider-2}"
PROVIDER_TWO_PROFILE_ID="${PROVIDER_TWO_PROFILE_ID:-profile-opening-neg-provider-2}"

CLIENT_ACTOR_ID="${CLIENT_ACTOR_ID:-opening-neg-client-actor}"
CLIENT_SUBJECT="${CLIENT_SUBJECT:-sso|opening-neg-client}"
CLIENT_PROFILE_ID="${CLIENT_PROFILE_ID:-profile-opening-neg-client}"

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

assert_error_code() {
  local expected="$1"
  local actual
  actual="$(printf '%s' "$RESPONSE_BODY" | jq -r '.error.code')"
  [[ "$actual" == "$expected" ]] || fail "Expected error code $expected but got $actual."
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

create_provider() {
  local actor_id="$1"
  local subject="$2"
  local profile_id="$3"
  local provider_payload

  provider_payload="$(jq -nc '{provider_type:"individual"}')"
  api_call "POST" "$API_BASE/providers" "$provider_payload" \
    -H "X-Actor-Id: $actor_id" \
    -H "X-Actor-Subject: $subject" \
    -H 'X-Actor-Roles: provider' \
    -H "X-User-Profile-Id: $profile_id" \
    -H "Idempotency-Key: provider-create-$(uuid)"
  assert_status 201
  json_get '.data.provider_id'
}

seed_offering() {
  local provider_id="$1"
  local offering_id
  local now_iso

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
  'Openings Negative Smoke Service',
  NULL,
  30,
  $PRICE_MINOR,
  '$PRICE_CURRENCY',
  'active',
  '$now_iso',
  '$now_iso'
);
"
  printf '%s' "$offering_id"
}

require_cmd curl
require_cmd jq
require_cmd docker

log "Checking Docker and backend availability"
docker compose ps >/dev/null
curl -sS "$API_BASE/me" >/dev/null || true

provider_one_headers=(
  -H "X-Actor-Id: $PROVIDER_ONE_ACTOR_ID"
  -H "X-Actor-Subject: $PROVIDER_ONE_SUBJECT"
  -H 'X-Actor-Roles: provider'
  -H "X-User-Profile-Id: $PROVIDER_ONE_PROFILE_ID"
)

provider_two_headers=(
  -H "X-Actor-Id: $PROVIDER_TWO_ACTOR_ID"
  -H "X-Actor-Subject: $PROVIDER_TWO_SUBJECT"
  -H 'X-Actor-Roles: provider'
  -H "X-User-Profile-Id: $PROVIDER_TWO_PROFILE_ID"
)

client_headers=(
  -H "X-Actor-Id: $CLIENT_ACTOR_ID"
  -H "X-Actor-Subject: $CLIENT_SUBJECT"
  -H 'X-Actor-Roles: client'
  -H "X-User-Profile-Id: $CLIENT_PROFILE_ID"
)

log "Creating two providers for access-control scenarios"
provider_one_id="$(create_provider "$PROVIDER_ONE_ACTOR_ID" "$PROVIDER_ONE_SUBJECT" "$PROVIDER_ONE_PROFILE_ID")"
provider_two_id="$(create_provider "$PROVIDER_TWO_ACTOR_ID" "$PROVIDER_TWO_SUBJECT" "$PROVIDER_TWO_PROFILE_ID")"
printf 'provider_one_id=%s\nprovider_two_id=%s\n' "$provider_one_id" "$provider_two_id"

log "Seeding service offerings for both providers"
offering_one_id="$(seed_offering "$provider_one_id")"
offering_two_id="$(seed_offering "$provider_two_id")"
printf 'offering_one_id=%s\noffering_two_id=%s\n' "$offering_one_id" "$offering_two_id"

now_epoch="$(date -u +%s)"
base_start="$(timestamp_in_seconds "$((now_epoch + 7200))")"
base_end="$(timestamp_in_seconds "$((now_epoch + 9000))")"
alt_end="$(timestamp_in_seconds "$((now_epoch + 9300))")"
earlier_end="$(timestamp_in_seconds "$((now_epoch + 7100))")"
cancel_start="$(timestamp_in_seconds "$((now_epoch + 10800))")"
cancel_end="$(timestamp_in_seconds "$((now_epoch + 12600))")"

valid_create_payload="$(jq -nc \
  --arg offering_id "$offering_one_id" \
  --arg starts_at "$base_start" \
  --arg ends_at "$base_end" \
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

log "Create opening without auth should return 401"
api_call "POST" "$API_BASE/providers/$provider_one_id/openings" "$valid_create_payload" -H "Idempotency-Key: opening-no-auth-$(uuid)"
assert_status 401
assert_error_code 'AUTH_IDENTITY_NOT_LINKED'
pretty_print_response

log "Create opening with wrong role should return 403"
api_call "POST" "$API_BASE/providers/$provider_one_id/openings" "$valid_create_payload" "${client_headers[@]}" -H "Idempotency-Key: opening-wrong-role-$(uuid)"
assert_status 403
assert_error_code 'FORBIDDEN_ROLE_MISSING'
pretty_print_response

log "Create opening without idempotency key should return 422"
api_call "POST" "$API_BASE/providers/$provider_one_id/openings" "$valid_create_payload" "${provider_one_headers[@]}"
assert_status 422
assert_error_code 'VALIDATION_IDEMPOTENCY_KEY_REQUIRED'
pretty_print_response

log "Create opening with invalid time range should return 422"
invalid_time_payload="$(jq -nc \
  --arg offering_id "$offering_one_id" \
  --arg starts_at "$base_start" \
  --arg ends_at "$earlier_end" \
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
api_call "POST" "$API_BASE/providers/$provider_one_id/openings" "$invalid_time_payload" "${provider_one_headers[@]}" -H "Idempotency-Key: opening-invalid-time-$(uuid)"
assert_status 422
assert_error_code 'VALIDATION_TIME_RANGE_INVALID'
pretty_print_response

log "Create opening with invalid price should return 422"
invalid_price_payload="$(jq -nc \
  --arg offering_id "$offering_one_id" \
  --arg starts_at "$base_start" \
  --arg ends_at "$base_end" \
  '{
    service_offering_id: $offering_id,
    starts_at: $starts_at,
    ends_at: $ends_at,
    price_override: {
      currency: "EU",
      amount_minor: 0
    }
  }'
)"
api_call "POST" "$API_BASE/providers/$provider_one_id/openings" "$invalid_price_payload" "${provider_one_headers[@]}" -H "Idempotency-Key: opening-invalid-price-$(uuid)"
assert_status 422
assert_error_code 'VALIDATION_PRICE_INVALID'
pretty_print_response

log "Create opening with offering owned by another provider should return 422"
wrong_offering_payload="$(jq -nc \
  --arg offering_id "$offering_two_id" \
  --arg starts_at "$base_start" \
  --arg ends_at "$base_end" \
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
api_call "POST" "$API_BASE/providers/$provider_one_id/openings" "$wrong_offering_payload" "${provider_one_headers[@]}" -H "Idempotency-Key: opening-wrong-offering-$(uuid)"
assert_status 422
assert_error_code 'VALIDATION_SERVICE_OFFERING_NOT_FOUND'
pretty_print_response

log "Create opening replay with different payload under same idempotency key should return 409"
idempotency_mismatch_key="opening-idem-mismatch-$(uuid)"
api_call "POST" "$API_BASE/providers/$provider_one_id/openings" "$valid_create_payload" "${provider_one_headers[@]}" -H "Idempotency-Key: $idempotency_mismatch_key"
assert_status 201
opening_mismatch_id="$(json_get '.data.opening_id')"
[[ -n "$opening_mismatch_id" && "$opening_mismatch_id" != "null" ]] || fail "Mismatch test opening was not created."
pretty_print_response

mismatch_payload="$(jq -nc \
  --arg offering_id "$offering_one_id" \
  --arg starts_at "$base_start" \
  --arg ends_at "$alt_end" \
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
api_call "POST" "$API_BASE/providers/$provider_one_id/openings" "$mismatch_payload" "${provider_one_headers[@]}" -H "Idempotency-Key: $idempotency_mismatch_key"
assert_status 409
assert_error_code 'CONFLICT_IDEMPOTENCY_PAYLOAD_MISMATCH'
pretty_print_response

log "Creating draft opening for state and access-control tests"
state_opening_create_key="opening-state-draft-$(uuid)"
api_call "POST" "$API_BASE/providers/$provider_one_id/openings" "$mismatch_payload" "${provider_one_headers[@]}" -H "Idempotency-Key: $state_opening_create_key"
assert_status 201
state_opening_id="$(json_get '.data.opening_id')"
[[ -n "$state_opening_id" && "$state_opening_id" != "null" ]] || fail "State opening id is missing."
pretty_print_response

log "Draft opening must not appear in public list"
api_call "GET" "$API_BASE/public/openings?provider_id=$provider_one_id&service_offering_id=$offering_one_id&limit=50" "" 
assert_status 200
draft_public_count="$(printf '%s' "$RESPONSE_BODY" | jq -r --arg opening_id "$state_opening_id" '.data | map(select(.opening_id == $opening_id)) | length')"
[[ "$draft_public_count" == "0" ]] || fail "Draft opening leaked into public list."
pretty_print_response

log "Provider two must not list provider one openings"
api_call "GET" "$API_BASE/providers/$provider_one_id/openings?limit=20" "" "${provider_two_headers[@]}"
assert_status 403
assert_error_code 'FORBIDDEN_PROVIDER_ACCESS'
pretty_print_response

log "Provider two must not fetch provider one opening"
api_call "GET" "$API_BASE/providers/$provider_one_id/openings/$state_opening_id" "" "${provider_two_headers[@]}"
assert_status 403
assert_error_code 'FORBIDDEN_PROVIDER_ACCESS'
pretty_print_response

log "Fetching missing opening should return 404"
api_call "GET" "$API_BASE/providers/$provider_one_id/openings/$(uuid)" "" "${provider_one_headers[@]}"
assert_status 404
assert_error_code 'OPENING_NOT_FOUND'
pretty_print_response

log "Publish without idempotency key should return 422"
api_call "POST" "$API_BASE/providers/$provider_one_id/openings/$state_opening_id:publish" "{}" "${provider_one_headers[@]}"
assert_status 422
assert_error_code 'VALIDATION_IDEMPOTENCY_KEY_REQUIRED'
pretty_print_response

log "Provider two must not publish provider one opening"
api_call "POST" "$API_BASE/providers/$provider_one_id/openings/$state_opening_id:publish" "{}" "${provider_two_headers[@]}" -H "Idempotency-Key: opening-cross-publish-$(uuid)"
assert_status 403
assert_error_code 'FORBIDDEN_PROVIDER_ACCESS'
pretty_print_response

log "Publishing state opening"
publish_key="opening-state-publish-$(uuid)"
api_call "POST" "$API_BASE/providers/$provider_one_id/openings/$state_opening_id:publish" "{}" "${provider_one_headers[@]}" -H "Idempotency-Key: $publish_key"
assert_status 200
assert_json_true '.data.status == "published"'
pretty_print_response

log "Publishing already published opening with a fresh key should return 409"
api_call "POST" "$API_BASE/providers/$provider_one_id/openings/$state_opening_id:publish" "{}" "${provider_one_headers[@]}" -H "Idempotency-Key: opening-publish-conflict-$(uuid)"
assert_status 409
assert_error_code 'CONFLICT_OPENING_STATE_INVALID'
pretty_print_response

log "Published opening should appear in public list"
api_call "GET" "$API_BASE/public/openings?provider_id=$provider_one_id&service_offering_id=$offering_one_id&limit=50" "" 
assert_status 200
published_public_count="$(printf '%s' "$RESPONSE_BODY" | jq -r --arg opening_id "$state_opening_id" '.data | map(select(.opening_id == $opening_id and .status == "published")) | length')"
[[ "$published_public_count" == "1" ]] || fail "Published opening did not appear in public list."
pretty_print_response

log "Creating second draft opening for cancel-state checks"
cancel_payload="$(jq -nc \
  --arg offering_id "$offering_one_id" \
  --arg starts_at "$cancel_start" \
  --arg ends_at "$cancel_end" \
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
api_call "POST" "$API_BASE/providers/$provider_one_id/openings" "$cancel_payload" "${provider_one_headers[@]}" -H "Idempotency-Key: opening-cancel-draft-$(uuid)"
assert_status 201
cancel_opening_id="$(json_get '.data.opening_id')"
[[ -n "$cancel_opening_id" && "$cancel_opening_id" != "null" ]] || fail "Cancel opening id is missing."
pretty_print_response

log "Cancel without idempotency key should return 422"
api_call "POST" "$API_BASE/providers/$provider_one_id/openings/$cancel_opening_id:cancel" "{}" "${provider_one_headers[@]}"
assert_status 422
assert_error_code 'VALIDATION_IDEMPOTENCY_KEY_REQUIRED'
pretty_print_response

log "Provider two must not cancel provider one opening"
api_call "POST" "$API_BASE/providers/$provider_one_id/openings/$cancel_opening_id:cancel" "{}" "${provider_two_headers[@]}" -H "Idempotency-Key: opening-cross-cancel-$(uuid)"
assert_status 403
assert_error_code 'FORBIDDEN_PROVIDER_ACCESS'
pretty_print_response

log "Cancelling draft opening"
cancel_key="opening-cancel-success-$(uuid)"
api_call "POST" "$API_BASE/providers/$provider_one_id/openings/$cancel_opening_id:cancel" "{}" "${provider_one_headers[@]}" -H "Idempotency-Key: $cancel_key"
assert_status 200
assert_json_true '.data.status == "cancelled_by_provider"'
pretty_print_response

log "Cancelling already cancelled opening with a fresh key should return 409"
api_call "POST" "$API_BASE/providers/$provider_one_id/openings/$cancel_opening_id:cancel" "{}" "${provider_one_headers[@]}" -H "Idempotency-Key: opening-cancel-conflict-$(uuid)"
assert_status 409
assert_error_code 'CONFLICT_OPENING_STATE_INVALID'
pretty_print_response

log "Cancelled opening must not appear in public list"
api_call "GET" "$API_BASE/public/openings?provider_id=$provider_one_id&service_offering_id=$offering_one_id&limit=50" "" 
assert_status 200
cancelled_public_count="$(printf '%s' "$RESPONSE_BODY" | jq -r --arg opening_id "$cancel_opening_id" '.data | map(select(.opening_id == $opening_id)) | length')"
[[ "$cancelled_public_count" == "0" ]] || fail "Cancelled opening leaked into public list."
pretty_print_response

log "Negative openings smoke test completed successfully"
printf 'provider_one_id=%s\n' "$provider_one_id"
printf 'provider_two_id=%s\n' "$provider_two_id"
printf 'offering_one_id=%s\n' "$offering_one_id"
printf 'offering_two_id=%s\n' "$offering_two_id"
printf 'state_opening_id=%s\n' "$state_opening_id"
printf 'cancel_opening_id=%s\n' "$cancel_opening_id"
