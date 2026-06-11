import { useLocation, Link } from 'react-router-dom';
import { useTheme } from 'next-themes';
import { Sun, Moon } from 'lucide-react';
import { useAuth } from '@/lib/auth';
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
];

export function AppSidebar() {
  const { pathname } = useLocation();
  const { theme, setTheme } = useTheme();
  const auth = useAuth();

  const me = auth.status === 'authenticated' ? auth.me : null;

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
                    {item.label}
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
