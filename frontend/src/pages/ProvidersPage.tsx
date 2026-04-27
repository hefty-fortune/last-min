import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  listProviders,
  listOrganizations,
  createProvider,
  type Provider,
  type CreateProviderPayload,
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

export default function ProvidersPage() {
  const [open, setOpen] = useState(false);
  const [filterOrg, setFilterOrg] = useState('');
  const [detail, setDetail] = useState<Provider | null>(null);
  const [form, setForm] = useState<CreateProviderPayload>({
    organization_id: '',
    display_name: '',
    status: 'active',
  });
  const queryClient = useQueryClient();

  const { data: orgs } = useQuery({
    queryKey: ['organizations'],
    queryFn: () => listOrganizations().then((r) => r.data),
  });

  const { data, isLoading } = useQuery({
    queryKey: ['providers', filterOrg],
    queryFn: () => listProviders(filterOrg || undefined).then((r) => r.data),
  });

  const mutation = useMutation({
    mutationFn: createProvider,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['providers'] });
      setOpen(false);
      setForm({ organization_id: '', display_name: '', status: 'active' });
      toast.success('Provider created');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Providers</h1>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger render={<Button />}>Create Provider</DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>New Provider</DialogTitle>
            </DialogHeader>
            <form
              className="space-y-4"
              onSubmit={(e) => {
                e.preventDefault();
                mutation.mutate(form);
              }}
            >
              <div className="space-y-2">
                <Label>Organization</Label>
                <select
                  className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                  value={form.organization_id}
                  onChange={(e) => setForm((f) => ({ ...f, organization_id: e.target.value }))}
                  required
                >
                  <option value="">Select organization...</option>
                  {orgs?.map((org) => (
                    <option key={org.organization_id} value={org.organization_id}>
                      {org.display_name}
                    </option>
                  ))}
                </select>
              </div>
              <div className="space-y-2">
                <Label>Display Name</Label>
                <Input
                  value={form.display_name}
                  onChange={(e) => setForm((f) => ({ ...f, display_name: e.target.value }))}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label>Status</Label>
                <select
                  className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                  value={form.status}
                  onChange={(e) => setForm((f) => ({ ...f, status: e.target.value }))}
                >
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
              <Button type="submit" className="w-full" disabled={mutation.isPending}>
                {mutation.isPending ? 'Creating...' : 'Create'}
              </Button>
            </form>
          </DialogContent>
        </Dialog>
      </div>

      <div className="flex gap-2 items-center">
        <Label className="text-sm">Filter by Org:</Label>
        <select
          className="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
          value={filterOrg}
          onChange={(e) => setFilterOrg(e.target.value)}
        >
          <option value="">All</option>
          {orgs?.map((org) => (
            <option key={org.organization_id} value={org.organization_id}>
              {org.display_name}
            </option>
          ))}
        </select>
      </div>

      <Dialog open={!!detail} onOpenChange={() => setDetail(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Provider Details</DialogTitle>
          </DialogHeader>
          {detail && (
            <dl className="space-y-3 text-sm">
              <div><dt className="text-muted-foreground">Provider ID</dt><dd className="font-mono">{detail.provider_id}</dd></div>
              <div><dt className="text-muted-foreground">Organization ID</dt><dd className="font-mono">{detail.organization_id}</dd></div>
              <div><dt className="text-muted-foreground">Display Name</dt><dd>{detail.display_name}</dd></div>
              <div><dt className="text-muted-foreground">Status</dt><dd><Badge variant={detail.status === 'active' ? 'default' : 'secondary'}>{detail.status}</Badge></dd></div>
            </dl>
          )}
        </DialogContent>
      </Dialog>

      <Card>
        <CardHeader>
          <CardTitle>All Providers</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <p className="text-muted-foreground">Loading...</p>
          ) : !data?.length ? (
            <p className="text-muted-foreground">No providers yet.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Display Name</TableHead>
                  <TableHead>Organization ID</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {data.map((p) => (
                  <TableRow key={p.provider_id}>
                    <TableCell className="font-medium">{p.display_name}</TableCell>
                    <TableCell className="font-mono text-xs">{p.organization_id}</TableCell>
                    <TableCell>
                      <Badge variant={p.status === 'active' ? 'default' : 'secondary'}>
                        {p.status}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Button size="sm" variant="ghost" onClick={() => setDetail(p)}>
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
