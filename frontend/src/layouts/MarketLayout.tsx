import { Link, Outlet, useLocation } from 'react-router-dom';
import { useTheme } from 'next-themes';
import { Sun, Moon } from 'lucide-react';
import { useAuth } from '@/lib/auth';
import { Button, Badge } from '@/components/common';

export default function MarketLayout() {
  const { pathname } = useLocation();
  const { theme, setTheme } = useTheme();
  const auth = useAuth();

  const me = auth.status === 'authenticated' ? auth.me : null;
  const isAdmin = me?.roles.some((r) => r === 'admin' || r === 'super-admin') ?? false;

  const navLink = (to: string, label: string) => (
    <Link
      to={to}
      className={`text-sm px-3 py-2 rounded-md transition-colors ${
        pathname === to ? 'bg-accent text-accent-foreground font-medium' : 'text-muted-foreground hover:text-foreground'
      }`}
    >
      {label}
    </Link>
  );

  return (
    <div className="min-h-screen bg-background text-foreground">
      <header className="border-b">
        <div className="mx-auto max-w-5xl px-4 h-14 flex items-center justify-between">
          <div className="flex items-center gap-6">
            <Link to="/market" className="font-semibold text-lg">
              U zadnji čas
            </Link>
            <nav className="flex items-center gap-1">
              {navLink('/market', 'Market')}
              {me && navLink('/my-bookings', 'My bookings')}
              {isAdmin && navLink('/organizations', 'Admin dashboard')}
            </nav>
          </div>
          <div className="flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
              aria-label="Toggle theme"
            >
              {theme === 'dark' ? <Sun className="size-4" /> : <Moon className="size-4" />}
            </Button>
            {me ? (
              <>
                <Badge variant="secondary" className="hidden sm:inline-flex">{me.actor_id.slice(0, 8)}…</Badge>
                <Button variant="outline" size="sm" onClick={() => auth.logout()}>
                  Sign out
                </Button>
              </>
            ) : (
              <Link to="/login">
                <Button size="sm">Sign in</Button>
              </Link>
            )}
          </div>
        </div>
      </header>
      <main className="mx-auto max-w-5xl px-4 py-8">
        <Outlet />
      </main>
    </div>
  );
}
