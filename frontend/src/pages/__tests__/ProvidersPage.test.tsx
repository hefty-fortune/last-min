import { render, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { TooltipProvider } from '@/components/common';
import ProvidersPage from '../ProvidersPage';

vi.mock('@/lib/api', () => ({
  listProviders: vi.fn(),
  listOrganizations: vi.fn(),
  createProvider: vi.fn(),
}));

import { listProviders, listOrganizations } from '@/lib/api';
const mockListProviders = vi.mocked(listProviders);
const mockListOrgs = vi.mocked(listOrganizations);

function renderPage() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <TooltipProvider>
        <MemoryRouter>
          <ProvidersPage />
        </MemoryRouter>
      </TooltipProvider>
    </QueryClientProvider>,
  );
}

describe('ProvidersPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockListOrgs.mockResolvedValue({ data: [], meta: { request_id: 'r1' } });
  });

  it('renders with data', async () => {
    mockListProviders.mockResolvedValue({
      data: [{ provider_id: 'p1', organization_id: 'o1', display_name: 'Test Provider', status: 'active' }],
      meta: { request_id: 'r1' },
    });
    const { container } = renderPage();
    await waitFor(() => container.querySelector('table'));
    expect(container).toMatchSnapshot();
  });

  it('renders empty state', async () => {
    mockListProviders.mockResolvedValue({ data: [], meta: { request_id: 'r1' } });
    const { container } = renderPage();
    await waitFor(() => container.querySelector('[class*="text-muted"]'));
    expect(container).toMatchSnapshot();
  });
});
