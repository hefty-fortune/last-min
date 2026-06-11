import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider } from 'next-themes';
import { Toaster, TooltipProvider } from '@/components/common';
import { AuthProvider } from '@/lib/auth';
import AppLayout from '@/layouts/AppLayout';
import LoginPage from '@/pages/LoginPage';
import OrganizationsPage from '@/pages/OrganizationsPage';
import OrganizationDetailPage from '@/pages/OrganizationDetailPage';
import ProvidersPage from '@/pages/ProvidersPage';
import ProviderDetailPage from '@/pages/ProviderDetailPage';
import UsersPage from '@/pages/UsersPage';
import UserDetailPage from '@/pages/UserDetailPage';
import ApiKeysPage from '@/pages/ApiKeysPage';
import OpeningsPage from '@/pages/OpeningsPage';
import BookingsPage from '@/pages/BookingsPage';
import RefundsPage from '@/pages/RefundsPage';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { retry: false, refetchOnWindowFocus: false },
  },
});

export default function App() {
  return (
    <ThemeProvider attribute="class" defaultTheme="dark" enableSystem={false}>
      <QueryClientProvider client={queryClient}>
        <TooltipProvider>
          <AuthProvider>
            <BrowserRouter>
              <Routes>
                <Route path="/login" element={<LoginPage />} />
                <Route element={<AppLayout />}>
                  <Route index element={<Navigate to="/organizations" replace />} />
                  <Route path="/organizations" element={<OrganizationsPage />} />
                  <Route path="/organizations/:id" element={<OrganizationDetailPage />} />
                  <Route path="/providers" element={<ProvidersPage />} />
                  <Route path="/providers/:id" element={<ProviderDetailPage />} />
                  <Route path="/users" element={<UsersPage />} />
                  <Route path="/users/:id" element={<UserDetailPage />} />
                  <Route path="/api-keys" element={<ApiKeysPage />} />
                  <Route path="/openings" element={<OpeningsPage />} />
                  <Route path="/bookings" element={<BookingsPage />} />
                  <Route path="/refunds" element={<RefundsPage />} />
                </Route>
              </Routes>
            </BrowserRouter>
            <Toaster />
          </AuthProvider>
        </TooltipProvider>
      </QueryClientProvider>
    </ThemeProvider>
  );
}
