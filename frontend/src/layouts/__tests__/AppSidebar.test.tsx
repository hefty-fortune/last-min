import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { SidebarProvider } from '@/components/common';
import { AppSidebar } from '../AppSidebar';

vi.mock('@/lib/auth', () => ({
  useAuth: vi.fn(),
}));

import { useAuth } from '@/lib/auth';
const mockUseAuth = vi.mocked(useAuth);

function renderSidebar(route = '/') {
  return render(
    <MemoryRouter initialEntries={[route]}>
      <SidebarProvider>
        <AppSidebar />
      </SidebarProvider>
    </MemoryRouter>,
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
