import { useLocation, Link } from 'react-router-dom';
import { useTheme } from 'next-themes';
import { Sun, Moon } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '@/lib/auth';
import { listAdminRefunds } from '@/lib/api';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  Button,
  Badge,
} from '@/components/common';

const adminNav = [
  { label: 'Organizations', path: '/organizations' },
  { label: 'Providers', path: '/providers' },
  { label: 'Users', path: '/users' },
  { label: 'API keys', path: '/api-keys' },
];

const marketplaceNav = [
  { label: 'Openings', path: '/openings' },
  { label: 'Bookings', path: '/bookings' },
  { label: 'Refunds', path: '/refunds' },
  { label: 'Storefront', path: '/market' },
];

export function AppSidebar() {
  const { pathname } = useLocation();
  const { theme, setTheme } = useTheme();
  const auth = useAuth();

  const me = auth.status === 'authenticated' ? auth.me : null;

  // Surface refund requests needing review without anyone checking manually.
  const { data: requestedRefunds } = useQuery({
    queryKey: ['admin-refunds', 'requested'],
    queryFn: () => listAdminRefunds('requested').then((r) => r.data),
    refetchInterval: 30_000,
  });
  const pendingReviewCount = requestedRefunds?.length ?? 0;

  return (
    <Sidebar>
      <SidebarHeader className="p-4">
        <h2 className="text-lg font-semibold">Access dashboard</h2>
        {me && (
          <div className="mt-2 space-y-1">
            <p className="text-xs text-muted-foreground truncate">
              {me.actor_id}
            </p>
            <div className="flex gap-1 flex-wrap">
              {me.roles.map((role) => (
                <Badge key={role} variant="secondary" className="text-xs">
                  {role}
                </Badge>
              ))}
            </div>
          </div>
        )}
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Admin</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {adminNav.map((item) => (
                <SidebarMenuItem key={item.path}>
                  <SidebarMenuButton isActive={pathname === item.path} render={<Link to={item.path} />}>
                    {item.label}
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
        <SidebarGroup>
          <SidebarGroupLabel>Marketplace</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {marketplaceNav.map((item) => (
                <SidebarMenuItem key={item.path}>
                  <SidebarMenuButton isActive={pathname === item.path} render={<Link to={item.path} />}>
                    <span className="flex w-full items-center justify-between">
                      {item.label}
                      {item.path === '/refunds' && pendingReviewCount > 0 && (
                        <Badge variant="destructive" className="text-xs">{pendingReviewCount}</Badge>
                      )}
                    </span>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarFooter className="p-4 space-y-2">
        <Button
          variant="outline"
          className="w-full"
          onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
        >
          {theme === 'dark' ? <Sun className="size-4 mr-2" /> : <Moon className="size-4 mr-2" />}
          {theme === 'dark' ? 'Light mode' : 'Dark mode'}
        </Button>
        <Button
          variant="outline"
          className="w-full"
          onClick={() => auth.logout()}
        >
          Sign out
        </Button>
      </SidebarFooter>
    </Sidebar>
  );
}
