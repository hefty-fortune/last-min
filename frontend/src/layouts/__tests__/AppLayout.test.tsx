import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { TooltipProvider } from '@/components/common';
import AppLayout from '../AppLayout';

vi.mock('@/lib/auth', () => ({
  useAuth: vi.fn(),
}));

import { useAuth } from '@/lib/auth';
const mockUseAuth = vi.mocked(useAuth);

function renderLayout(initialRoute = '/') {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <MemoryRouter initialEntries={[initialRoute]}>
          <AppLayout />
        </MemoryRouter>
      </TooltipProvider>
    </QueryClientProvider>,
  );
}

describe('AppLayout', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders loading state', () => {
    mockUseAuth.mockReturnValue({ status: 'loading', login: vi.fn(), logout: vi.fn() });
    const { container } = renderLayout();
    expect(container).toMatchSnapshot();
  });

  it('renders unauthenticated state', () => {
    mockUseAuth.mockReturnValue({ status: 'unauthenticated', login: vi.fn(), logout: vi.fn() });
    const { container } = renderLayout();
    expect(container).toMatchSnapshot();
  });

  it('renders authenticated state', () => {
    mockUseAuth.mockReturnValue({
      status: 'authenticated',
      me: { actor_id: 'test', upstream_subject: null, roles: ['admin'], default_role: 'admin', profile_id: null },
      login: vi.fn(),
      logout: vi.fn(),
    });
    const { container } = renderLayout();
    expect(container).toMatchSnapshot();
  });
});
