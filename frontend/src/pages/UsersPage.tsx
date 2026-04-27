import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  listUsers,
  listProviders,
  createUser,
  type User,
  type CreateUserPayload,
} from '@/lib/api';
import {
  Button,
  Input,
  Label,
  Badge,
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

const ROLES = ['admin', 'super-admin', 'provider', 'client'];

export default function UsersPage() {
  const [open, setOpen] = useState(false);
  const [filterProvider, setFilterProvider] = useState('');
  const [detail, setDetail] = useState<User | null>(null);
  const [form, setForm] = useState<CreateUserPayload>({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    roles: [],
    provider_id: '',
  });
  const queryClient = useQueryClient();

  const { data: providers } = useQuery({
    queryKey: ['providers'],
    queryFn: () => listProviders().then((r) => r.data),
  });

  const { data, isLoading } = useQuery({
    queryKey: ['users', filterProvider],
    queryFn: () => listUsers(filterProvider || undefined).then((r) => r.data),
  });

  const mutation = useMutation({
    mutationFn: createUser,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users'] });
      setOpen(false);
      setForm({ first_name: '', last_name: '', email: '', phone: '', roles: [], provider_id: '' });
      toast.success('User created');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const toggleRole = (role: string) => {
    setForm((f) => ({
      ...f,
      roles: f.roles.includes(role)
        ? f.roles.filter((r) => r !== role)
        : [...f.roles, role],
    }));
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Users</h1>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger render={<Button />}>Create User</DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>New User</DialogTitle>
            </DialogHeader>
            <form
              className="space-y-4"
              onSubmit={(e) => {
                e.preventDefault();
                if (form.roles.length === 0) {
                  toast.error('At least one role is required');
                  return;
                }
                mutation.mutate(form);
              }}
            >
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>First Name</Label>
                  <Input
                    value={form.first_name}
                    onChange={(e) => setForm((f) => ({ ...f, first_name: e.target.value }))}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label>Last Name</Label>
                  <Input
                    value={form.last_name}
                    onChange={(e) => setForm((f) => ({ ...f, last_name: e.target.value }))}
                    required
                  />
                </div>
              </div>
              <div className="space-y-2">
                <Label>Email</Label>
                <Input
                  type="email"
                  value={form.email}
                  onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label>Phone</Label>
                <Input
                  value={form.phone}
                  onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label>Provider</Label>
                <select
                  className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                  value={form.provider_id}
                  onChange={(e) => setForm((f) => ({ ...f, provider_id: e.target.value }))}
                  required
                >
                  <option value="">Select provider...</option>
                  {providers?.map((p) => (
                    <option key={p.provider_id} value={p.provider_id}>
                      {p.display_name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="space-y-2">
                <Label>Roles</Label>
                <div className="flex flex-wrap gap-2">
                  {ROLES.map((role) => (
                    <Badge
                      key={role}
                      variant={form.roles.includes(role) ? 'default' : 'outline'}
                      className="cursor-pointer"
                      onClick={() => toggleRole(role)}
                    >
                      {role}
                    </Badge>
                  ))}
                </div>
              </div>
              <Button type="submit" className="w-full" disabled={mutation.isPending}>
                {mutation.isPending ? 'Creating...' : 'Create'}
              </Button>
            </form>
          </DialogContent>
        </Dialog>
      </div>

      <div className="flex gap-2 items-center">
        <Label className="text-sm">Filter by Provider:</Label>
        <select
          className="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
          value={filterProvider}
          onChange={(e) => setFilterProvider(e.target.value)}
        >
          <option value="">All</option>
          {providers?.map((p) => (
            <option key={p.provider_id} value={p.provider_id}>
              {p.display_name}
            </option>
          ))}
        </select>
      </div>

      <Dialog open={!!detail} onOpenChange={() => setDetail(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>User Details</DialogTitle>
          </DialogHeader>
          {detail && (
            <dl className="space-y-3 text-sm">
              <div><dt className="text-muted-foreground">User ID</dt><dd className="font-mono">{detail.user_id}</dd></div>
              <div><dt className="text-muted-foreground">Name</dt><dd>{detail.first_name} {detail.last_name}</dd></div>
              <div><dt className="text-muted-foreground">Email</dt><dd>{detail.email}</dd></div>
              <div><dt className="text-muted-foreground">Phone</dt><dd>{detail.phone}</dd></div>
              <div><dt className="text-muted-foreground">Provider ID</dt><dd className="font-mono">{detail.provider_id}</dd></div>
              <div>
                <dt className="text-muted-foreground">Roles</dt>
                <dd className="flex gap-1 mt-1">
                  {detail.roles.map((r) => <Badge key={r} variant="secondary">{r}</Badge>)}
                </dd>
              </div>
            </dl>
          )}
        </DialogContent>
      </Dialog>

      <Card>
        <CardHeader>
          <CardTitle>All Users</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <p className="text-muted-foreground">Loading...</p>
          ) : !data?.length ? (
            <p className="text-muted-foreground">No users yet.</p>
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
                {data.map((u) => (
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
                      <Button size="sm" variant="ghost" onClick={() => setDetail(u)}>
                        View
                      </Button>
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
