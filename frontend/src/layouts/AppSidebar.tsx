import { useLocation, Link } from 'react-router-dom';
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
  { label: 'API Keys', path: '/api-keys' },
];

export function AppSidebar() {
  const { pathname } = useLocation();
  const auth = useAuth();

  const me = auth.status === 'authenticated' ? auth.me : null;

  return (
    <Sidebar>
      <SidebarHeader className="p-4">
        <h2 className="text-lg font-semibold">Access Dashboard</h2>
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
      </SidebarContent>
      <SidebarFooter className="p-4">
        <Button
          variant="outline"
          className="w-full"
          onClick={() => auth.logout()}
        >
          Sign Out
        </Button>
      </SidebarFooter>
    </Sidebar>
  );
}
