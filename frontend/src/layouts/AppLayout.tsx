import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '@/lib/auth';
import { SidebarProvider, SidebarTrigger } from '@/components/common';
import { AppSidebar } from './AppSidebar';

export default function AppLayout() {
  const auth = useAuth();

  if (auth.status === 'loading') {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <p className="text-muted-foreground">Loading...</p>
      </div>
    );
  }

  if (auth.status === 'unauthenticated') {
    return <Navigate to="/login" replace />;
  }

  return (
    <SidebarProvider>
      <AppSidebar />
      <main className="flex-1 overflow-auto">
        <div className="p-4 md:p-6">
          <SidebarTrigger className="mb-4 md:hidden" />
          <Outlet />
        </div>
      </main>
    </SidebarProvider>
  );
}
