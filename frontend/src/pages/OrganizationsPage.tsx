import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  listOrganizations,
  createOrganization,
  type CreateOrganizationPayload,
} from '@/lib/api';
import {
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

const emptyForm: CreateOrganizationPayload = {
  legal_name: '',
  display_name: '',
  tax_id: '',
  contact_email: '',
  contact_phone: '',
};

export default function OrganizationsPage() {
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const queryClient = useQueryClient();

  const { data, isLoading } = useQuery({
    queryKey: ['organizations'],
    queryFn: () => listOrganizations().then((r) => r.data),
  });

  const mutation = useMutation({
    mutationFn: createOrganization,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['organizations'] });
      setOpen(false);
      setForm(emptyForm);
      toast.success('Organization created');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const set = (field: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [field]: e.target.value }));

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Organizations</h1>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger render={<Button />}>Create Organization</DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>New Organization</DialogTitle>
            </DialogHeader>
            <form
              className="space-y-4"
              onSubmit={(e) => {
                e.preventDefault();
                const payload = { ...form };
                if (!payload.tax_id) delete (payload as Record<string, unknown>).tax_id;
                mutation.mutate(payload);
              }}
            >
              <div className="space-y-2">
                <Label>Legal Name</Label>
                <Input value={form.legal_name} onChange={set('legal_name')} required />
              </div>
              <div className="space-y-2">
                <Label>Display Name</Label>
                <Input value={form.display_name} onChange={set('display_name')} required />
              </div>
              <div className="space-y-2">
                <Label>Tax ID (optional)</Label>
                <Input value={form.tax_id} onChange={set('tax_id')} />
              </div>
              <div className="space-y-2">
                <Label>Contact Email</Label>
                <Input type="email" value={form.contact_email} onChange={set('contact_email')} required />
              </div>
              <div className="space-y-2">
                <Label>Contact Phone</Label>
                <Input value={form.contact_phone} onChange={set('contact_phone')} required />
              </div>
              <Button type="submit" className="w-full" disabled={mutation.isPending}>
                {mutation.isPending ? 'Creating...' : 'Create'}
              </Button>
            </form>
          </DialogContent>
        </Dialog>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Organizations</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <p className="text-muted-foreground">Loading...</p>
          ) : !data?.length ? (
            <p className="text-muted-foreground">No organizations yet.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Display Name</TableHead>
                  <TableHead>Legal Name</TableHead>
                  <TableHead>Email</TableHead>
                  <TableHead>Phone</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {data.map((org) => (
                  <TableRow key={org.organization_id}>
                    <TableCell className="font-medium">{org.display_name}</TableCell>
                    <TableCell>{org.legal_name}</TableCell>
                    <TableCell>{org.contact_email}</TableCell>
                    <TableCell>{org.contact_phone}</TableCell>
                    <TableCell>
                      <Link to={`/organizations/${org.organization_id}`}>
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
