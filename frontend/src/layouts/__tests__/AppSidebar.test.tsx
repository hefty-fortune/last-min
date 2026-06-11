import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { SidebarProvider } from '@/components/common';
import { AppSidebar } from '../AppSidebar';

vi.mock('@/lib/auth', () => ({
  useAuth: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  listAdminRefunds: vi.fn().mockResolvedValue({ data: [], meta: { request_id: 'r1' } }),
}));

import { useAuth } from '@/lib/auth';
const mockUseAuth = vi.mocked(useAuth);

function renderSidebar(route = '/') {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[route]}>
        <SidebarProvider>
          <AppSidebar />
        </SidebarProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('AppSidebar', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders sidebar with single role', () => {
    mockUseAuth.mockReturnValue({
      status: 'authenticated',
      me: { actor_id: 'u1', upstream_subject: null, roles: ['admin'], default_role: 'admin', profile_id: null },
      login: vi.fn(),
      logout: vi.fn(),
    });
    const { container } = renderSidebar();
    expect(container).toMatchSnapshot();
  });

  it('renders sidebar with multiple roles', () => {
    mockUseAuth.mockReturnValue({
      status: 'authenticated',
      me: { actor_id: 'actor-123', upstream_subject: null, roles: ['admin', 'super-admin'], default_role: 'admin', profile_id: null },
      login: vi.fn(),
      logout: vi.fn(),
    });
    const { container } = renderSidebar();
    expect(container).toMatchSnapshot();
  });

  it('renders with active route highlighted', () => {
    mockUseAuth.mockReturnValue({
      status: 'authenticated',
      me: { actor_id: 'u1', upstream_subject: null, roles: ['admin'], default_role: 'admin', profile_id: null },
      login: vi.fn(),
      logout: vi.fn(),
    });
    const { container } = renderSidebar('/providers');
    expect(container).toMatchSnapshot();
  });
});
