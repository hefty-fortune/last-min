const BASE_URL = '/api/v1';
const API_KEY = import.meta.env.VITE_API_KEY ?? '';

export class ApiError extends Error {
  status: number;
  code: string;
  details: Array<{ field?: string; issue?: string }>;

  constructor(
    status: number,
    code: string,
    details: Array<{ field?: string; issue?: string }> = [],
    message: string,
  ) {
    super(message);
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

async function request<T>(
  path: string,
  options: RequestInit = {},
): Promise<T> {
  const sessionToken = localStorage.getItem('session_token');
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...((options.headers as Record<string, string>) ?? {}),
  };
  if (API_KEY) headers['X-Api-Key'] = API_KEY;
  if (sessionToken) headers['Authorization'] = `Bearer ${sessionToken}`;

  const res = await fetch(`${BASE_URL}${path}`, { ...options, headers });

  if (!res.ok) {
    const body = await res.json().catch(() => null);
    const err = body?.error;
    throw new ApiError(
      res.status,
      err?.code ?? 'UNKNOWN',
      err?.details ?? [],
      err?.message ?? res.statusText,
    );
  }

  return res.json();
}

// ── Auth ──

export const loginUser = (email: string, password: string) =>
  request<{ data: LoginResponse; meta: Meta }>('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });

export const logoutUser = () =>
  request<{ data: { logged_out: boolean }; meta: Meta }>('/auth/logout', {
    method: 'POST',
  });

// ── Identity ──

export const getMe = () =>
  request<{ data: MeResponse; meta: Meta }>('/me');

export const listApiKeys = () =>
  request<{ data: ApiKeyEntry[]; meta: Meta }>('/api-keys');

export const createApiKey = (body: { name: string }) =>
  request<{ data: ApiKeyCreated; meta: Meta }>('/api-key', {
    method: 'POST',
    body: JSON.stringify(body),
  });

export const revokeApiKey = (apiKeyId: string) =>
  request<{ data: { api_key_id: string; revoked: boolean }; meta: Meta }>(
    `/api-key/${apiKeyId}`,
    { method: 'DELETE' },
  );

export const deleteApiKey = (apiKeyId: string) =>
  request<{ data: { api_key_id: string; deleted: boolean }; meta: Meta }>(
    `/api-key/${apiKeyId}/destroy`,
    { method: 'DELETE' },
  );

// ── Admin: Organizations ──

export const listOrganizations = () =>
  request<{ data: Organization[]; meta: Meta }>('/admin/organizations');

export const getOrganization = (id: string) =>
  request<{ data: Organization; meta: Meta }>(`/admin/organizations/${id}`);

export const createOrganization = (body: CreateOrganizationPayload) =>
  request<{ data: Organization; meta: Meta }>('/admin/organizations', {
    method: 'POST',
    body: JSON.stringify(body),
  });

// ── Admin: Providers ──

export const listProviders = (orgId?: string) =>
  request<{ data: Provider[]; meta: Meta }>(
    `/admin/providers${orgId ? `?organization_id=${orgId}` : ''}`,
  );

export const getProvider = (id: string) =>
  request<{ data: Provider; meta: Meta }>(`/admin/providers/${id}`);

export const createProvider = (body: CreateProviderPayload) =>
  request<{ data: Provider; meta: Meta }>('/admin/providers', {
    method: 'POST',
    body: JSON.stringify(body),
  });

// ── Admin: Users ──

export const listUsers = (providerId?: string) =>
  request<{ data: User[]; meta: Meta }>(
    `/admin/users${providerId ? `?provider_id=${providerId}` : ''}`,
  );

export const getUser = (id: string) =>
  request<{ data: User; meta: Meta }>(`/admin/users/${id}`);

export const createUser = (body: CreateUserPayload) =>
  request<{ data: User; meta: Meta }>('/admin/users', {
    method: 'POST',
    body: JSON.stringify(body),
  });

export const updateUser = (id: string, body: Partial<Pick<User, 'first_name' | 'last_name' | 'email' | 'phone'>>) =>
  request<{ data: User; meta: Meta }>(`/admin/users/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(body),
  });

export const updateUserRoles = (id: string, roles: string[]) =>
  request<{ data: User; meta: Meta }>(`/admin/users/${id}/roles`, {
    method: 'PATCH',
    body: JSON.stringify({ roles }),
  });

export const resetUserPassword = (id: string, password: string) =>
  request<{ data: { user_id: string; password_reset: boolean }; meta: Meta }>(`/admin/users/${id}/reset-password`, {
    method: 'POST',
    body: JSON.stringify({ password }),
  });

// ── Marketplace: Offerings ──

const idempotent = () => ({ 'Idempotency-Key': crypto.randomUUID() });

export const listOfferings = (providerId: string) =>
  request<{ data: Offering[]; meta: Meta }>(`/providers/${providerId}/offerings`);

export const createOffering = (providerId: string, body: CreateOfferingPayload) =>
  request<{ data: Offering; meta: Meta }>(`/providers/${providerId}/offerings`, {
    method: 'POST',
    headers: idempotent(),
    body: JSON.stringify(body),
  });

// ── Marketplace: Openings ──

export const listOpenings = (providerId: string, status?: string) =>
  request<{ data: Opening[]; meta: Meta }>(
    `/providers/${providerId}/openings${status ? `?status=${status}` : ''}`,
  );

export const createOpening = (providerId: string, body: CreateOpeningPayload) =>
  request<{ data: Opening; meta: Meta }>(`/providers/${providerId}/openings`, {
    method: 'POST',
    headers: idempotent(),
    body: JSON.stringify(body),
  });

export const publishOpening = (providerId: string, openingId: string) =>
  request<{ data: Opening; meta: Meta }>(`/providers/${providerId}/openings/${openingId}:publish`, {
    method: 'POST',
    headers: idempotent(),
    body: JSON.stringify({}),
  });

export const cancelOpening = (providerId: string, openingId: string) =>
  request<{ data: Opening; meta: Meta }>(`/providers/${providerId}/openings/${openingId}:cancel`, {
    method: 'POST',
    headers: idempotent(),
    body: JSON.stringify({}),
  });

// ── Marketplace: Bookings ──

export const listAdminBookings = (state?: string) =>
  request<{ data: AdminBooking[]; meta: Meta }>(
    `/admin/bookings${state ? `?state=${state}` : ''}`,
  );

export const createBooking = (openingId: string) =>
  request<{ data: { booking_id: string; state: string }; meta: Meta }>('/bookings', {
    method: 'POST',
    headers: idempotent(),
    body: JSON.stringify({ opening_id: openingId }),
  });

export const initiatePayment = (bookingId: string) =>
  request<{ data: PaymentInitiated; meta: Meta }>(`/bookings/${bookingId}/payments/initiate`, {
    method: 'POST',
    headers: idempotent(),
    body: JSON.stringify({ payment_method_type: 'card' }),
  });

export const simulatePaymentSucceed = (paymentId: string) =>
  request<{ data: { payment_id: string; state: string }; meta: Meta }>(
    `/payments/${paymentId}:simulate-succeed`,
    { method: 'POST', body: JSON.stringify({}) },
  );

export const simulatePaymentFail = (paymentId: string, reason?: string) =>
  request<{ data: { payment_id: string; state: string }; meta: Meta }>(
    `/payments/${paymentId}:simulate-fail`,
    { method: 'POST', body: JSON.stringify({ reason: reason ?? 'card_declined' }) },
  );

export const markProviderNoShow = (bookingId: string) =>
  request<{ data: AdminBooking; meta: Meta }>(`/bookings/${bookingId}:mark-provider-no-show`, {
    method: 'POST',
    headers: idempotent(),
    body: JSON.stringify({}),
  });

export const markClientNoShow = (bookingId: string) =>
  request<{ data: AdminBooking; meta: Meta }>(`/bookings/${bookingId}:mark-client-no-show`, {
    method: 'POST',
    headers: idempotent(),
    body: JSON.stringify({}),
  });

// ── Client storefront ──

export const listPublicOpenings = () =>
  request<{ data: PublicOpening[]; meta: Meta }>('/public/openings');

export const listMyBookings = (state?: string) =>
  request<{ data: MyBooking[]; meta: Meta }>(
    `/me/bookings${state ? `?state=${state}` : ''}`,
  );

export const getBooking = (bookingId: string) =>
  request<{ data: BookingDetail; meta: Meta }>(`/bookings/${bookingId}`);

// ── Marketplace: Refunds ──

export const listAdminRefunds = (state?: string) =>
  request<{ data: Refund[]; meta: Meta }>(
    `/admin/refunds${state ? `?state=${state}` : ''}`,
  );

export const approveRefund = (refundId: string, note?: string) =>
  request<{ data: Refund; meta: Meta }>(`/refunds/${refundId}:approve`, {
    method: 'POST',
    headers: idempotent(),
    body: JSON.stringify(note ? { note } : {}),
  });

// ── Types ──

export type Meta = { request_id: string };

export type LoginResponse = {
  token: string;
  expires_at: string;
  user: {
    user_id: string;
    email: string;
    first_name: string;
    last_name: string;
    roles: string[];
  };
};

export type MeResponse = {
  actor_id: string;
  upstream_subject: string | null;
  roles: string[];
  default_role: string | null;
  profile_id: string | null;
};

export type ApiKeyEntry = {
  api_key_id: string;
  name: string;
  key_prefix: string;
  key_plain: string | null;
  created_by: string | null;
  created_at: string;
  revoked_at: string | null;
  is_active: boolean;
};

export type ApiKeyCreated = {
  api_key_id: string;
  name: string;
  api_key: string;
};

export type Organization = {
  organization_id: string;
  legal_name: string;
  display_name: string;
  tax_id: string | null;
  contact_email: string;
  contact_phone: string;
};

export type CreateOrganizationPayload = {
  legal_name: string;
  display_name: string;
  tax_id?: string;
  contact_email: string;
  contact_phone: string;
};

export type Provider = {
  provider_id: string;
  organization_id: string;
  display_name: string;
  status: string;
};

export type CreateProviderPayload = {
  organization_id: string;
  display_name: string;
  status: string;
};

export type User = {
  user_id: string;
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  roles: string[];
  provider_id: string;
};

export type CreateUserPayload = {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  roles: string[];
  provider_id: string;
};

export type Money = { currency: string; amount_minor: number };

export type Offering = {
  offering_id: string;
  provider_id: string;
  name: string;
  description: string | null;
  duration_minutes: number;
  base_price: Money;
  status: string;
};

export type CreateOfferingPayload = {
  name: string;
  description?: string;
  duration_minutes: number;
  base_price: Money;
};

export type Opening = {
  opening_id: string;
  provider_id: string;
  service_offering_id: string;
  starts_at: string;
  ends_at: string;
  status: string;
  price_snapshot?: Money;
};

export type CreateOpeningPayload = {
  service_offering_id: string;
  starts_at: string;
  ends_at: string;
};

export type AdminBooking = {
  booking_id: string;
  opening_id: string;
  provider_id: string;
  client_user_profile_id: string;
  state: string;
  amount: Money;
  payment: { payment_id: string; state: string } | null;
  no_show_actor: string | null;
  created_at: string;
};

export type PaymentInitiated = {
  payment_id: string;
  state: string;
  amount: Money;
  stripe: { payment_intent_id: string; client_secret: string };
};

export type Refund = {
  refund_id: string;
  payment_id: string;
  booking_id: string;
  state: string;
  reason: string;
  amount: Money;
  decided_by_actor_id: string | null;
  decided_at: string | null;
  created_at: string;
};

export type PublicOpening = Opening & {
  provider_display_name: string | null;
  offering_name: string | null;
  offering_duration_minutes: number | null;
};

export type MyBooking = {
  booking_id: string;
  opening_id: string;
  provider_id: string;
  state: string;
  amount: Money;
  reserved_at: string;
  expires_at: string | null;
  created_at: string;
};

export type BookingDetail = MyBooking & {
  payment: { payment_id: string; state: string; amount: Money } | null;
};
