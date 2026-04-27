import { render, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { TooltipProvider } from '@/components/common';
import UsersPage from '../UsersPage';

vi.mock('@/lib/api', () => ({
  listUsers: vi.fn(),
  listProviders: vi.fn(),
  createUser: vi.fn(),
}));

import { listUsers, listProviders } from '@/lib/api';
const mockListUsers = vi.mocked(listUsers);
const mockListProviders = vi.mocked(listProviders);

function renderPage() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <TooltipProvider>
        <MemoryRouter>
          <UsersPage />
        </MemoryRouter>
      </TooltipProvider>
    </QueryClientProvider>,
  );
}

describe('UsersPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockListProviders.mockResolvedValue({ data: [], meta: { request_id: 'r1' } });
  });

  it('renders with data', async () => {
    mockListUsers.mockResolvedValue({
      data: [
        { user_id: 'u1', first_name: 'John', last_name: 'Doe', email: 'john@test.com', phone: '123', roles: ['admin'], provider_id: 'p1' },
      ],
      meta: { request_id: 'r1' },
    });
    const { container } = renderPage();
    await waitFor(() => container.querySelector('table'));
    expect(container).toMatchSnapshot();
  });

  it('renders empty state', async () => {
    mockListUsers.mockResolvedValue({ data: [], meta: { request_id: 'r1' } });
    const { container } = renderPage();
    await waitFor(() => container.querySelector('[class*="text-muted"]'));
    expect(container).toMatchSnapshot();
  });
});
