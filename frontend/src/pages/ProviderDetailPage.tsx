import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getProvider, listUsers, createUser, type CreateUserPayload } from '@/lib/api';
import {
  Badge,
  Button,
  Input,
  Label,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/common';
import { toast } from 'sonner';

const ROLES = ['admin', 'org_admin', 'provider_manager', 'provider_staff', 'client'];

export default function ProviderDetailPage() {
  const { id } = useParams<{ id: string }>();
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState<CreateUserPayload>({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    roles: ['provider_staff'],
    provider_id: id ?? '',
  });

  const { data: provider, isLoading } = useQuery({
    queryKey: ['provider', id],
    queryFn: () => getProvider(id!).then((r) => r.data),
    enabled: !!id,
  });

  const { data: users } = useQuery({
    queryKey: ['users', id],
    queryFn: () => listUsers(id).then((r) => r.data),
    enabled: !!id,
  });

  const mutation = useMutation({
    mutationFn: createUser,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users', id] });
      setOpen(false);
      setForm({ first_name: '', last_name: '', email: '', phone: '', roles: ['provider_staff'], provider_id: id ?? '' });
      toast.success('User created');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const toggleRole = (role: string) => {
    setForm((f) => ({
      ...f,
      roles: f.roles.includes(role) ? f.roles.filter((r) => r !== role) : [...f.roles, role],
    }));
  };

  if (isLoading) return <p className="text-muted-foreground">Loading...</p>;
  if (!provider) return <p className="text-muted-foreground">Provider not found.</p>;

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <Link to={`/organizations/${provider.organization_id}`}>
          <Button variant="ghost" size="sm">&larr; Back to Organization</Button>
        </Link>
        <h1 className="text-2xl font-semibold">{provider.display_name}</h1>
        <Badge variant={provider.status === 'active' ? 'default' : 'secondary'}>{provider.status}</Badge>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Provider details</CardTitle>
        </CardHeader>
        <CardContent>
          <dl className="grid grid-cols-2 gap-4 text-sm">
            <div><dt className="text-muted-foreground">Display name</dt><dd>{provider.display_name}</dd></div>
            <div><dt className="text-muted-foreground">Status</dt><dd>{provider.status}</dd></div>
            <div><dt className="text-muted-foreground">Organization ID</dt><dd className="font-mono text-xs">{provider.organization_id}</dd></div>
            <div><dt className="text-muted-foreground">Provider ID</dt><dd className="font-mono text-xs">{provider.provider_id}</dd></div>
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Users ({users?.length ?? 0})</CardTitle>
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={<Button size="sm" />}>Add user</DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>New user for {provider.display_name}</DialogTitle>
              </DialogHeader>
              <form
                className="space-y-4"
                onSubmit={(e) => {
                  e.preventDefault();
                  if (form.roles.length === 0) { toast.error('At least one role is required'); return; }
                  mutation.mutate({ ...form, provider_id: id! });
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
                <div className="space-y-2">
                  <Label>Roles</Label>
                  <div className="flex flex-wrap gap-2">
                    {ROLES.map((role) => (
                      <Badge key={role} variant={form.roles.includes(role) ? 'default' : 'outline'} className="cursor-pointer" onClick={() => toggleRole(role)}>
                        {role}
                      </Badge>
                    ))}
                  </div>
                </div>
                <Button type="submit" className="w-full" disabled={mutation.isPending}>
                  {mutation.isPending ? 'Creating...' : 'Create user'}
                </Button>
              </form>
            </DialogContent>
          </Dialog>
        </CardHeader>
        <CardContent>
          {!users?.length ? (
            <p className="text-muted-foreground">No users assigned to this provider.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Email</TableHead>
                  <TableHead>Phone</TableHead>
                  <TableHead>Roles</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {users.map((u) => (
                  <TableRow key={u.user_id}>
                    <TableCell className="font-medium">{u.first_name} {u.last_name}</TableCell>
                    <TableCell>{u.email}</TableCell>
                    <TableCell>{u.phone}</TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        {u.roles.map((r) => <Badge key={r} variant="secondary" className="text-xs">{r}</Badge>)}
                      </div>
                    </TableCell>
                    <TableCell>
                      <Link to={`/users/${u.user_id}`}>
                        <Button size="sm" variant="ghost">View</Button>
                      </Link>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
