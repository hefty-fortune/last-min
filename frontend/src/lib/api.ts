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

export const listApiKeys = (clientId?: string) =>
  request<{ data: ApiKeyEntry[]; meta: Meta }>(
    `/api-keys${clientId ? `?client_id=${clientId}` : ''}`,
  );

export const createApiKey = (body: { client_id: string; name?: string }) =>
  request<{ data: ApiKeyCreated; meta: Meta }>('/api-key', {
    method: 'POST',
    body: JSON.stringify(body),
  });

export const revokeApiKey = (apiKeyId: string) =>
  request<{ data: { api_key_id: string; revoked: boolean }; meta: Meta }>(
    `/api-key/${apiKeyId}`,
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
  client_id: string;
  api_key_id: string;
  name: string;
};

export type ApiKeyCreated = ApiKeyEntry & { api_key: string };

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
