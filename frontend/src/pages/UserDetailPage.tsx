import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getUser, updateUser, updateUserRoles, resetUserPassword } from '@/lib/api';
import {
  Badge,
  Button,
  Input,
  Label,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/common';
import { toast } from 'sonner';

const ALL_ROLES = ['admin', 'super-admin', 'org_admin', 'provider_manager', 'provider_staff', 'client'];

export default function UserDetailPage() {
  const { id } = useParams<{ id: string }>();
  const queryClient = useQueryClient();

  const { data: user, isLoading } = useQuery({
    queryKey: ['user', id],
    queryFn: () => getUser(id!).then((r) => r.data),
    enabled: !!id,
  });

  if (isLoading) return <p className="text-muted-foreground">Loading...</p>;
  if (!user) return <p className="text-muted-foreground">User not found.</p>;

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <Link to={`/providers/${user.provider_id}`}>
          <Button variant="ghost" size="sm">&larr; Back to Provider</Button>
        </Link>
        <h1 className="text-2xl font-semibold">{user.first_name} {user.last_name}</h1>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <EditDetailsCard user={user} userId={id!} queryClient={queryClient} />
        <RolesCard user={user} userId={id!} queryClient={queryClient} />
      </div>

      <ResetPasswordCard userId={id!} />
    </div>
  );
}

function EditDetailsCard({ user, userId, queryClient }: { user: { first_name: string; last_name: string; email: string; phone: string }; userId: string; queryClient: ReturnType<typeof useQueryClient> }) {
  const [form, setForm] = useState({
    first_name: user.first_name,
    last_name: user.last_name,
    email: user.email,
    phone: user.phone,
  });

  const mutation = useMutation({
    mutationFn: (fields: typeof form) => updateUser(userId, fields),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user', userId] });
      toast.success('User details updated');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle>Details</CardTitle>
      </CardHeader>
      <CardContent>
        <form
          className="space-y-4"
          onSubmit={(e) => {
            e.preventDefault();
            mutation.mutate(form);
          }}
        >
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>First name</Label>
              <Input value={form.first_name} onChange={(e) => setForm((f) => ({ ...f, first_name: e.target.value }))} required />
            </div>
            <div className="space-y-2">
              <Label>Last name</Label>
              <Input value={form.last_name} onChange={(e) => setForm((f) => ({ ...f, last_name: e.target.value }))} required />
            </div>
          </div>
          <div className="space-y-2">
            <Label>Email</Label>
            <Input type="email" value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} required />
          </div>
          <div className="space-y-2">
            <Label>Phone</Label>
            <Input value={form.phone} onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))} required />
          </div>
          <Button type="submit" disabled={mutation.isPending}>
            {mutation.isPending ? 'Saving...' : 'Save Changes'}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

function RolesCard({ user, userId, queryClient }: { user: { roles: string[] }; userId: string; queryClient: ReturnType<typeof useQueryClient> }) {
  const [roles, setRoles] = useState<string[]>(user.roles);

  const mutation = useMutation({
    mutationFn: (newRoles: string[]) => updateUserRoles(userId, newRoles),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['user', userId] });
      toast.success('Roles updated');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const toggleRole = (role: string) => {
    setRoles((prev) => prev.includes(role) ? prev.filter((r) => r !== role) : [...prev, role]);
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>Roles</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap gap-2">
          {ALL_ROLES.map((role) => (
            <Badge
              key={role}
              variant={roles.includes(role) ? 'default' : 'outline'}
              className="cursor-pointer"
              onClick={() => toggleRole(role)}
            >
              {role}
            </Badge>
          ))}
        </div>
        <Button
          onClick={() => {
            if (roles.length === 0) { toast.error('At least one role required'); return; }
            mutation.mutate(roles);
          }}
          disabled={mutation.isPending}
        >
          {mutation.isPending ? 'Saving...' : 'Update Roles'}
        </Button>
      </CardContent>
    </Card>
  );
}

function ResetPasswordCard({ userId }: { userId: string }) {
  const [open, setOpen] = useState(false);
  const [password, setPassword] = useState('');

  const mutation = useMutation({
    mutationFn: (pw: string) => resetUserPassword(userId, pw),
    onSuccess: () => {
      setOpen(false);
      setPassword('');
      toast.success('Password has been reset');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle>Security</CardTitle>
      </CardHeader>
      <CardContent>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger render={<Button variant="destructive" />}>Reset password</DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Reset user password</DialogTitle>
            </DialogHeader>
            <form
              className="space-y-4"
              onSubmit={(e) => {
                e.preventDefault();
                if (password.length < 6) { toast.error('Password must be at least 6 characters'); return; }
                mutation.mutate(password);
              }}
            >
              <div className="space-y-2">
                <Label>New password</Label>
                <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="Enter new password" required />
              </div>
              <Button type="submit" variant="destructive" className="w-full" disabled={mutation.isPending}>
                {mutation.isPending ? 'Resetting...' : 'Confirm Reset'}
              </Button>
            </form>
          </DialogContent>
        </Dialog>
      </CardContent>
    </Card>
  );
}
