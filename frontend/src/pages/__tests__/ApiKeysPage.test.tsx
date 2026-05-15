import { render, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { TooltipProvider } from '@/components/common';
import ApiKeysPage from '../ApiKeysPage';

vi.mock('@/lib/api', () => ({
  listApiKeys: vi.fn(),
  createApiKey: vi.fn(),
  revokeApiKey: vi.fn(),
}));

import { listApiKeys } from '@/lib/api';
const mockListApiKeys = vi.mocked(listApiKeys);

function renderPage() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <TooltipProvider>
        <MemoryRouter>
          <ApiKeysPage />
        </MemoryRouter>
      </TooltipProvider>
    </QueryClientProvider>,
  );
}

describe('ApiKeysPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders with data', async () => {
    mockListApiKeys.mockResolvedValue({
      data: [{ client_id: 'c1', api_key_id: 'k1', name: 'My Key' }],
      meta: { request_id: 'r1' },
    });
    const { container } = renderPage();
    await waitFor(() => container.querySelector('table'));
    expect(container).toMatchSnapshot();
  });

  it('renders empty state', async () => {
    mockListApiKeys.mockResolvedValue({ data: [], meta: { request_id: 'r1' } });
    const { container } = renderPage();
    await waitFor(() => container.querySelector('[class*="text-muted"]'));
    expect(container).toMatchSnapshot();
  });
});
