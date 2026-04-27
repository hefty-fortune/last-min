import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Toaster, TooltipProvider } from '@/components/common';
import { AuthProvider } from '@/lib/auth';
import AppLayout from '@/layouts/AppLayout';
import LoginPage from '@/pages/LoginPage';
import OrganizationsPage from '@/pages/OrganizationsPage';
import ProvidersPage from '@/pages/ProvidersPage';
import UsersPage from '@/pages/UsersPage';
import ApiKeysPage from '@/pages/ApiKeysPage';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: false, refetchOnWindowFocus: false },
  },
});

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <AuthProvider>
          <BrowserRouter>
            <Routes>
              <Route path="/login" element={<LoginPage />} />
              <Route element={<AppLayout />}>
                <Route index element={<Navigate to="/organizations" replace />} />
                <Route path="/organizations" element={<OrganizationsPage />} />
                <Route path="/providers" element={<ProvidersPage />} />
                <Route path="/users" element={<UsersPage />} />
                <Route path="/api-keys" element={<ApiKeysPage />} />
              </Route>
            </Routes>
          </BrowserRouter>
          <Toaster />
        </AuthProvider>
      </TooltipProvider>
    </QueryClientProvider>
  );
}
