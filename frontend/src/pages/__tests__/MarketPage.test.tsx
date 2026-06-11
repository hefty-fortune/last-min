import { waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderWithProviders } from '@/test/test-utils';
import MarketPage from '../MarketPage';

vi.mock('@/lib/api', () => ({
  listPublicOpenings: vi.fn(),
  createBooking: vi.fn(),
}));

vi.mock('@/lib/auth', () => ({
  useAuth: () => ({
    status: 'authenticated',
    me: { actor_id: 'actor-1', roles: ['client'], upstream_subject: null, default_role: 'client', profile_id: 'actor-1' },
    login: vi.fn(),
    logout: vi.fn(),
  }),
}));

import { listPublicOpenings } from '@/lib/api';
const mockList = vi.mocked(listPublicOpenings);

describe('MarketPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders opening cards', async () => {
    mockList.mockResolvedValue({
      data: [
        {
          opening_id: 'op-1',
          provider_id: 'prov-1',
          service_offering_id: 'off-1',
          starts_at: '2026-06-12T10:00:00+00:00',
          ends_at: '2026-06-12T10:30:00+00:00',
          status: 'published',
          price_snapshot: { currency: 'EUR', amount_minor: 2500 },
          provider_display_name: 'Demo provider',
          offering_name: 'Haircut',
          offering_duration_minutes: 30,
        },
      ],
      meta: { request_id: 'r1' },
    });
    const { container, getByText } = renderWithProviders(<MarketPage />);
    await waitFor(() => getByText('Haircut'));
    expect(getByText('Demo provider')).toBeTruthy();
    expect(getByText('25.00 EUR')).toBeTruthy();
    expect(container.querySelectorAll('button').length).toBeGreaterThan(0);
  });

  it('renders empty state', async () => {
    mockList.mockResolvedValue({ data: [], meta: { request_id: 'r1' } });
    const { getByText } = renderWithProviders(<MarketPage />);
    await waitFor(() => getByText(/Nothing available right now/));
  });
});
