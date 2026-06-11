import { waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderWithProviders } from '@/test/test-utils';
import MyBookingsPage from '../MyBookingsPage';

vi.mock('@/lib/api', () => ({
  listMyBookings: vi.fn(),
  getBooking: vi.fn(),
  initiatePayment: vi.fn(),
  simulatePaymentSucceed: vi.fn(),
  simulatePaymentFail: vi.fn(),
}));

vi.mock('@/lib/auth', () => ({
  useAuth: () => ({
    status: 'authenticated',
    me: { actor_id: 'actor-1', roles: ['client'], upstream_subject: null, default_role: 'client', profile_id: 'actor-1' },
    login: vi.fn(),
    logout: vi.fn(),
  }),
}));

import { listMyBookings, getBooking } from '@/lib/api';
const mockList = vi.mocked(listMyBookings);
const mockGet = vi.mocked(getBooking);

const booking = {
  booking_id: 'bk-12345678',
  opening_id: 'op-1',
  provider_id: 'prov-1',
  state: 'reserved',
  amount: { currency: 'EUR', amount_minor: 2500 },
  reserved_at: '2026-06-12T09:00:00+00:00',
  expires_at: '2026-06-12T09:10:00+00:00',
  created_at: '2026-06-12T09:00:00+00:00',
};

describe('MyBookingsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a reserved booking with pay action', async () => {
    mockList.mockResolvedValue({ data: [booking], meta: { request_id: 'r1' } });
    mockGet.mockResolvedValue({ data: { ...booking, payment: null }, meta: { request_id: 'r2' } });
    const { getByText } = renderWithProviders(<MyBookingsPage />);
    await waitFor(() => getByText('reserved'));
    expect(getByText('25.00 EUR')).toBeTruthy();
    await waitFor(() => getByText('Pay'));
  });

  it('renders empty state', async () => {
    mockList.mockResolvedValue({ data: [], meta: { request_id: 'r1' } });
    const { getByText } = renderWithProviders(<MyBookingsPage />);
    await waitFor(() => getByText('No bookings yet'));
  });
});
