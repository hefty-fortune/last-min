import { waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderWithProviders } from '@/test/test-utils';
import ProviderPortalPage from '../ProviderPortalPage';

vi.mock('@/lib/api', () => ({
  getMyProvider: vi.fn(),
  linkProvider: vi.fn(),
  listOfferings: vi.fn(),
  createOffering: vi.fn(),
  listOpenings: vi.fn(),
  createOpening: vi.fn(),
  publishOpening: vi.fn(),
  cancelOpening: vi.fn(),
  deleteOffering: vi.fn(),
  deleteOpening: vi.fn(),
  listProviderBookings: vi.fn(),
  markClientNoShow: vi.fn(),
  markProviderNoShow: vi.fn(),
}));

vi.mock('@/lib/auth', () => ({
  useAuth: () => ({
    status: 'authenticated',
    me: { actor_id: 'actor-1', roles: ['provider', 'client'], upstream_subject: null, default_role: 'client', profile_id: 'actor-1' },
    login: vi.fn(),
    logout: vi.fn(),
  }),
}));

import { getMyProvider, listOfferings, listOpenings, listProviderBookings } from '@/lib/api';
const mockGetMyProvider = vi.mocked(getMyProvider);
const mockListOfferings = vi.mocked(listOfferings);
const mockListOpenings = vi.mocked(listOpenings);
const mockListProviderBookings = vi.mocked(listProviderBookings);

describe('ProviderPortalPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders become a provider card when not linked', async () => {
    mockGetMyProvider.mockRejectedValue(
      Object.assign(new Error('Provider not linked'), { status: 404, code: 'PROVIDER_NOT_LINKED' }),
    );
    const { getAllByText } = renderWithProviders(<ProviderPortalPage />);
    await waitFor(() => {
      expect(getAllByText('Become a provider').length).toBeGreaterThanOrEqual(2);
    });
  });

  it('renders provider sections when linked', async () => {
    mockGetMyProvider.mockResolvedValue({
      data: {
        provider_id: 'prov-1',
        organization_id: 'org-1',
        display_name: 'Jane the barber',
        status: 'active',
        provider_type: 'individual',
      },
      meta: { request_id: 'r1' },
    });
    mockListOfferings.mockResolvedValue({ data: [], meta: { request_id: 'r2' } });
    mockListOpenings.mockResolvedValue({ data: [], meta: { request_id: 'r3' } });
    mockListProviderBookings.mockResolvedValue({ data: [], meta: { request_id: 'r4' } });

    const { getByText } = renderWithProviders(<ProviderPortalPage />);
    await waitFor(() => getByText('Provider area'));
    expect(getByText('Jane the barber')).toBeTruthy();
    expect(getByText('My offerings')).toBeTruthy();
    expect(getByText('My openings')).toBeTruthy();
    expect(getByText('My bookings')).toBeTruthy();
  });
});
