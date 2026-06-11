import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getOrganization, listProviders, listUsers, createProvider, createUser } from '@/lib/api';
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  Input,
  Label,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/common';

export default function OrganizationDetailPage() {
  const { id } = useParams<{ id: string }>();
  const queryClient = useQueryClient();
  const [addProviderOpen, setAddProviderOpen] = useState(false);
  const [newProviderName, setNewProviderName] = useState('');
  const [addUserOpen, setAddUserOpen] = useState(false);
  const [newUser, setNewUser] = useState({ first_name: '', last_name: '', email: '', phone: '', provider_id: '' });

  const { data: org, isLoading } = useQuery({
    queryKey: ['organization', id],
    queryFn: () => getOrganization(id!).then((r) => r.data),
    enabled: !!id,
  });

  const { data: providers } = useQuery({
    queryKey: ['providers', id],
    queryFn: () => listProviders(id).then((r) => r.data),
    enabled: !!id,
  });

  const { data: allUsers } = useQuery({
    queryKey: ['users-for-org', id],
    queryFn: async () => {
      if (!providers?.length) return [];
      const results = await Promise.all(
        providers.map((p) => listUsers(p.provider_id).then((r) => r.data))
      );
      return results.flat();
    },
    enabled: !!providers?.length,
  });

  const addUser = useMutation({
    mutationFn: () =>
      createUser({ ...newUser, roles: ['provider_user'], provider_id: newUser.provider_id }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users-for-org', id] });
      setAddUserOpen(false);
      setNewUser({ first_name: '', last_name: '', email: '', phone: '', provider_id: '' });
    },
  });

  const addProvider = useMutation({
    mutationFn: (displayName: string) =>
      createProvider({ organization_id: id!, display_name: displayName, status: 'active' }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['providers', id] });
      setAddProviderOpen(false);
      setNewProviderName('');
    },
  });

  if (isLoading) return <p className="text-muted-foreground">Loading...</p>;
  if (!org) return <p className="text-muted-foreground">Organization not found.</p>;

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2">
        <Link to="/organizations">
          <Button variant="ghost" size="sm">&larr; Back</Button>
        </Link>
        <h1 className="text-2xl font-semibold">{org.display_name}</h1>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Organization details</CardTitle>
        </CardHeader>
        <CardContent>
          <dl className="grid grid-cols-2 gap-4 text-sm">
            <div><dt className="text-muted-foreground">Legal name</dt><dd>{org.legal_name}</dd></div>
            <div><dt className="text-muted-foreground">Display name</dt><dd>{org.display_name}</dd></div>
            <div><dt className="text-muted-foreground">Tax ID</dt><dd>{org.tax_id ?? '—'}</dd></div>
            <div><dt className="text-muted-foreground">Contact email</dt><dd>{org.contact_email}</dd></div>
            <div><dt className="text-muted-foreground">Contact phone</dt><dd>{org.contact_phone}</dd></div>
            <div><dt className="text-muted-foreground">ID</dt><dd className="font-mono text-xs">{org.organization_id}</dd></div>
          </dl>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Providers ({providers?.length ?? 0})</CardTitle>
          <Dialog open={addProviderOpen} onOpenChange={setAddProviderOpen}>
            <DialogTrigger render={<Button size="sm">Add provider</Button>} />
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Add provider</DialogTitle>
              </DialogHeader>
              <div className="space-y-4 py-2">
                <div className="space-y-2">
                  <Label htmlFor="provider-name">Display name</Label>
                  <Input
                    id="provider-name"
                    value={newProviderName}
                    onChange={(e) => setNewProviderName(e.target.value)}
                    placeholder="e.g. Main Clinic"
                  />
                </div>
              </div>
              <DialogFooter>
                <Button
                  onClick={() => addProvider.mutate(newProviderName)}
                  disabled={!newProviderName.trim() || addProvider.isPending}
                >
                  {addProvider.isPending ? 'Adding...' : 'Add provider'}
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </CardHeader>
        <CardContent>
          {!providers?.length ? (
            <p className="text-muted-foreground">No providers yet.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Display name</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {providers.map((p) => (
                  <TableRow key={p.provider_id}>
                    <TableCell className="font-medium">{p.display_name}</TableCell>
                    <TableCell>
                      <Badge variant={p.status === 'active' ? 'default' : 'secondary'}>{p.status}</Badge>
                    </TableCell>
                    <TableCell>
                      <Link to={`/providers/${p.provider_id}`}>
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

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Users ({allUsers?.length ?? 0})</CardTitle>
          <Dialog open={addUserOpen} onOpenChange={setAddUserOpen}>
            <DialogTrigger render={<Button size="sm">Add user</Button>} />
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Add user</DialogTitle>
              </DialogHeader>
              <div className="space-y-4 py-2">
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="user-first-name">First name</Label>
                    <Input
                      id="user-first-name"
                      value={newUser.first_name}
                      onChange={(e) => setNewUser((u) => ({ ...u, first_name: e.target.value }))}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="user-last-name">Last name</Label>
                    <Input
                      id="user-last-name"
                      value={newUser.last_name}
                      onChange={(e) => setNewUser((u) => ({ ...u, last_name: e.target.value }))}
                    />
                  </div>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="user-email">Email</Label>
                  <Input
                    id="user-email"
                    type="email"
                    value={newUser.email}
                    onChange={(e) => setNewUser((u) => ({ ...u, email: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="user-phone">Phone</Label>
                  <Input
                    id="user-phone"
                    value={newUser.phone}
                    onChange={(e) => setNewUser((u) => ({ ...u, phone: e.target.value }))}
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="user-provider">Provider</Label>
                  <select
                    id="user-provider"
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={newUser.provider_id}
                    onChange={(e) => setNewUser((u) => ({ ...u, provider_id: e.target.value }))}
                  >
                    <option value="">Select a provider</option>
                    {providers?.map((p) => (
                      <option key={p.provider_id} value={p.provider_id}>{p.display_name}</option>
                    ))}
                  </select>
                </div>
              </div>
              <DialogFooter>
                <Button
                  onClick={() => addUser.mutate()}
                  disabled={!newUser.first_name.trim() || !newUser.email.trim() || !newUser.provider_id || addUser.isPending}
                >
                  {addUser.isPending ? 'Adding...' : 'Add user'}
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </CardHeader>
        <CardContent>
          {!allUsers?.length ? (
            <p className="text-muted-foreground">No users yet.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Email</TableHead>
                  <TableHead>Roles</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {allUsers.map((u) => (
                  <TableRow key={u.user_id}>
                    <TableCell className="font-medium">{u.first_name} {u.last_name}</TableCell>
                    <TableCell>{u.email}</TableCell>
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
