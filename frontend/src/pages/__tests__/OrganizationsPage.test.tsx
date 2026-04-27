import { render, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { TooltipProvider } from '@/components/common';
import OrganizationsPage from '../OrganizationsPage';

vi.mock('@/lib/api', () => ({
  listOrganizations: vi.fn(),
  createOrganization: vi.fn(),
}));

import { listOrganizations } from '@/lib/api';
const mockList = vi.mocked(listOrganizations);

function renderPage() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <TooltipProvider>
        <MemoryRouter>
          <OrganizationsPage />
        </MemoryRouter>
      </TooltipProvider>
    </QueryClientProvider>,
  );
}

describe('OrganizationsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders with data', async () => {
    mockList.mockResolvedValue({
      data: [
        { organization_id: '1', legal_name: 'Acme LLC', display_name: 'Acme', tax_id: null, contact_email: 'a@b.com', contact_phone: '123' },
      ],
      meta: { request_id: 'r1' },
    });
    const { container } = renderPage();
    await waitFor(() => container.querySelector('table'));
    expect(container).toMatchSnapshot();
  });

  it('renders empty state', async () => {
    mockList.mockResolvedValue({ data: [], meta: { request_id: 'r1' } });
    const { container } = renderPage();
    await waitFor(() => container.querySelector('[class*="text-muted"]'));
    expect(container).toMatchSnapshot();
  });
});
