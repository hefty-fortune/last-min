import {
  createContext,
  useContext,
  useState,
  useEffect,
  type ReactNode,
} from 'react';
import { getMe, loginUser, logoutUser, type MeResponse } from './api';

type AuthState =
  | { status: 'loading' }
  | { status: 'unauthenticated' }
  | { status: 'authenticated'; me: MeResponse };

type AuthContextType = AuthState & {
  login: (email: string, password: string) => Promise<{ ok: boolean; error?: string }>;
  logout: () => void;
};

const AuthContext = createContext<AuthContextType | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AuthState>({ status: 'loading' });

  const bootstrap = async () => {
    const token = localStorage.getItem('session_token');
    if (!token) {
      setState({ status: 'unauthenticated' });
      return;
    }
    try {
      const { data } = await getMe();
      setState({ status: 'authenticated', me: data });
    } catch {
      localStorage.removeItem('session_token');
      setState({ status: 'unauthenticated' });
    }
  };

  useEffect(() => {
    bootstrap();
  }, []);

  const login = async (email: string, password: string) => {
    try {
      const { data } = await loginUser(email, password);
      localStorage.setItem('session_token', data.token);
      const { data: me } = await getMe();
      setState({ status: 'authenticated', me });
      return { ok: true };
    } catch (err: unknown) {
      localStorage.removeItem('session_token');
      setState({ status: 'unauthenticated' });
      const message = err instanceof Error ? err.message : 'Login failed';
      return { ok: false, error: message };
    }
  };

  const logout = async () => {
    try {
      await logoutUser();
    } catch {
      // ignore logout errors
    }
    localStorage.removeItem('session_token');
    setState({ status: 'unauthenticated' });
  };

  return (
    <AuthContext.Provider value={{ ...state, login, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
  return ctx;
}
