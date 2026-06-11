import { useState } from 'react';
import { Navigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  getMyProvider,
  linkProvider,
  listOfferings,
  createOffering,
  listOpenings,
  createOpening,
  publishOpening,
  cancelOpening,
  deleteOffering,
  deleteOpening,
  listProviderBookings,
  markClientNoShow,
  markProviderNoShow,
} from '@/lib/api';
import { useAuth } from '@/lib/auth';
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
import { ConfirmDeleteDialog } from '@/components/ConfirmDeleteDialog';
import { toast } from 'sonner';

const openingStatusVariant = (status: string) =>
  status === 'published' ? 'default' : status === 'booked' ? 'secondary' : 'outline';

const bookingStateVariant = (state: string) => {
  if (state === 'confirmed') return 'default';
  if (state === 'payment_failed' || state === 'provider_no_show' || state === 'client_no_show') return 'destructive';
  return 'secondary';
};

function plusHours(hours: number): string {
  const d = new Date(Date.now() + hours * 3600_000);
  d.setSeconds(0, 0);
  return d.toISOString().slice(0, 16);
}

const formatMoney = (m: { amount_minor: number; currency: string } | null | undefined) =>
  m ? `${(m.amount_minor / 100).toFixed(2)} ${m.currency}` : '—';

function OfferingsSection({ providerId }: { providerId: string }) {
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ name: '', duration_minutes: 30, price_eur: 25 });
  const queryClient = useQueryClient();

  const { data: offerings, isLoading } = useQuery({
    queryKey: ['offerings', providerId],
    queryFn: () => listOfferings(providerId).then((r) => r.data),
  });

  const createMutation = useMutation({
    mutationFn: () =>
      createOffering(providerId, {
        name: form.name,
        duration_minutes: Number(form.duration_minutes),
        base_price: { currency: 'EUR', amount_minor: Math.round(Number(form.price_eur) * 100) },
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['offerings', providerId] });
      setOpen(false);
      setForm({ name: '', duration_minutes: 30, price_eur: 25 });
      toast.success('Offering created');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const deleteMutation = useMutation({
    mutationFn: (offeringId: string) => deleteOffering(providerId, offeringId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['offerings', providerId] });
      toast.success('Offering deleted');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle>My offerings</CardTitle>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger render={<Button size="sm" />}>New offering</DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>New service offering</DialogTitle>
            </DialogHeader>
            <form
              className="space-y-4"
              onSubmit={(e) => {
                e.preventDefault();
                createMutation.mutate();
              }}
            >
              <div className="space-y-2">
                <Label>Name</Label>
                <Input value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} required />
              </div>
              <div className="space-y-2">
                <Label>Duration (minutes)</Label>
                <Input
                  type="number"
                  min={5}
                  value={form.duration_minutes}
                  onChange={(e) => setForm((f) => ({ ...f, duration_minutes: Number(e.target.value) }))}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label>Price (EUR)</Label>
                <Input
                  type="number"
                  min={0}
                  step="0.01"
                  value={form.price_eur}
                  onChange={(e) => setForm((f) => ({ ...f, price_eur: Number(e.target.value) }))}
                  required
                />
              </div>
              <Button type="submit" className="w-full" disabled={createMutation.isPending}>
                {createMutation.isPending ? 'Creating...' : 'Create offering'}
              </Button>
            </form>
          </DialogContent>
        </Dialog>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <p className="text-muted-foreground">Loading...</p>
        ) : !offerings?.length ? (
          <p className="text-muted-foreground">No offerings yet.</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Duration</TableHead>
                <TableHead>Price</TableHead>
                <TableHead>Status</TableHead>
                <TableHead />
              </TableRow>
            </TableHeader>
            <TableBody>
              {offerings.map((o) => (
                <TableRow key={o.offering_id}>
                  <TableCell className="font-medium">{o.name}</TableCell>
                  <TableCell>{o.duration_minutes} min</TableCell>
                  <TableCell>{formatMoney(o.base_price)}</TableCell>
                  <TableCell>
                    <Badge variant={o.status === 'active' ? 'default' : 'secondary'}>{o.status}</Badge>
                  </TableCell>
                  <TableCell className="text-right">
                    <ConfirmDeleteDialog
                      title="Delete offering"
                      description={`Delete offering '${o.name}'? This cannot be undone.`}
                      onConfirm={() => deleteMutation.mutate(o.offering_id)}
                      pending={deleteMutation.isPending}
                    />
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  );
}

function OpeningsSection({ providerId }: { providerId: string }) {
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ service_offering_id: '', starts_at: plusHours(2), ends_at: plusHours(3) });
  const queryClient = useQueryClient();

  const { data: offerings } = useQuery({
    queryKey: ['offerings', providerId],
    queryFn: () => listOfferings(providerId).then((r) => r.data),
  });

  const { data: openings, isLoading } = useQuery({
    queryKey: ['openings', providerId],
    queryFn: () => listOpenings(providerId).then((r) => r.data),
  });

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['openings', providerId] });
    queryClient.invalidateQueries({ queryKey: ['provider-bookings', providerId] });
  };

  const createMutation = useMutation({
    mutationFn: () =>
      createOpening(providerId, {
        service_offering_id: form.service_offering_id,
        starts_at: new Date(form.starts_at).toISOString(),
        ends_at: new Date(form.ends_at).toISOString(),
      }),
    onSuccess: () => {
      refresh();
      setOpen(false);
      toast.success('Opening created (draft)');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const publishMutation = useMutation({
    mutationFn: (openingId: string) => publishOpening(providerId, openingId),
    onSuccess: () => {
      refresh();
      toast.success('Opening published');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const cancelMutation = useMutation({
    mutationFn: (openingId: string) => cancelOpening(providerId, openingId),
    onSuccess: () => {
      refresh();
      toast.success('Opening cancelled');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const deleteMutation = useMutation({
    mutationFn: (openingId: string) => deleteOpening(providerId, openingId),
    onSuccess: () => {
      refresh();
      toast.success('Opening deleted');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle>My openings</CardTitle>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger render={<Button size="sm" />}>New opening</DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>New opening (draft)</DialogTitle>
            </DialogHeader>
            <form
              className="space-y-4"
              onSubmit={(e) => {
                e.preventDefault();
                createMutation.mutate();
              }}
            >
              <div className="space-y-2">
                <Label>Offering</Label>
                <select
                  className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                  value={form.service_offering_id}
                  onChange={(e) => setForm((f) => ({ ...f, service_offering_id: e.target.value }))}
                  required
                >
                  <option value="">Select offering...</option>
                  {offerings?.map((o) => (
                    <option key={o.offering_id} value={o.offering_id}>
                      {o.name} ({formatMoney(o.base_price)})
                    </option>
                  ))}
                </select>
              </div>
              <div className="space-y-2">
                <Label>Starts at</Label>
                <Input
                  type="datetime-local"
                  value={form.starts_at}
                  onChange={(e) => setForm((f) => ({ ...f, starts_at: e.target.value }))}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label>Ends at</Label>
                <Input
                  type="datetime-local"
                  value={form.ends_at}
                  onChange={(e) => setForm((f) => ({ ...f, ends_at: e.target.value }))}
                  required
                />
              </div>
              <Button type="submit" className="w-full" disabled={createMutation.isPending}>
                {createMutation.isPending ? 'Creating...' : 'Create draft'}
              </Button>
            </form>
          </DialogContent>
        </Dialog>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <p className="text-muted-foreground">Loading...</p>
        ) : !openings?.length ? (
          <p className="text-muted-foreground">No openings yet. Create an offering, then a draft opening.</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Starts</TableHead>
                <TableHead>Ends</TableHead>
                <TableHead>Price</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {openings.map((o) => (
                <TableRow key={o.opening_id}>
                  <TableCell>{new Date(o.starts_at).toLocaleString()}</TableCell>
                  <TableCell>{new Date(o.ends_at).toLocaleString()}</TableCell>
                  <TableCell>{formatMoney(o.price_snapshot)}</TableCell>
                  <TableCell>
                    <Badge variant={openingStatusVariant(o.status)}>{o.status}</Badge>
                  </TableCell>
                  <TableCell className="text-right space-x-2">
                    {o.status === 'draft' && (
                      <Button size="sm" onClick={() => publishMutation.mutate(o.opening_id)} disabled={publishMutation.isPending}>
                        Publish
                      </Button>
                    )}
                    {(o.status === 'draft' || o.status === 'published') && (
                      <Button size="sm" variant="outline" onClick={() => cancelMutation.mutate(o.opening_id)} disabled={cancelMutation.isPending}>
                        Cancel
                      </Button>
                    )}
                    {['draft', 'cancelled_by_provider', 'expired'].includes(o.status) && (
                      <ConfirmDeleteDialog
                        title="Delete opening"
                        description="Delete this opening? This cannot be undone."
                        onConfirm={() => deleteMutation.mutate(o.opening_id)}
                        pending={deleteMutation.isPending}
                      />
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  );
}

function BookingsSection({ providerId }: { providerId: string }) {
  const queryClient = useQueryClient();

  const { data: bookings, isLoading } = useQuery({
    queryKey: ['provider-bookings', providerId],
    queryFn: () => listProviderBookings(providerId).then((r) => r.data),
  });

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['provider-bookings', providerId] });
    queryClient.invalidateQueries({ queryKey: ['openings', providerId] });
  };

  const clientNoShowMutation = useMutation({
    mutationFn: (bookingId: string) => markClientNoShow(bookingId),
    onSuccess: () => {
      refresh();
      toast.success('Marked as client no-show');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const providerNoShowMutation = useMutation({
    mutationFn: (bookingId: string) => markProviderNoShow(bookingId),
    onSuccess: () => {
      refresh();
      toast.success('Marked as provider no-show');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle>My bookings</CardTitle>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <p className="text-muted-foreground">Loading...</p>
        ) : !bookings?.length ? (
          <p className="text-muted-foreground">No bookings yet.</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>When</TableHead>
                <TableHead>Offering</TableHead>
                <TableHead>Amount</TableHead>
                <TableHead>State</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {bookings.map((b) => (
                <TableRow key={b.booking_id}>
                  <TableCell>
                    {b.opening_starts_at ? new Date(b.opening_starts_at).toLocaleString() : '—'}
                  </TableCell>
                  <TableCell>{b.offering_name ?? 'Service'}</TableCell>
                  <TableCell>{formatMoney(b.amount)}</TableCell>
                  <TableCell>
                    <Badge variant={bookingStateVariant(b.state)}>{b.state}</Badge>
                  </TableCell>
                  <TableCell className="text-right space-x-2">
                    {b.state === 'confirmed' && (
                      <>
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => clientNoShowMutation.mutate(b.booking_id)}
                          disabled={clientNoShowMutation.isPending}
                        >
                          Client no-show
                        </Button>
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => providerNoShowMutation.mutate(b.booking_id)}
                          disabled={providerNoShowMutation.isPending}
                        >
                          Provider no-show
                        </Button>
                      </>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  );
}

export default function ProviderPortalPage() {
  const auth = useAuth();
  const queryClient = useQueryClient();

  const { data: provider, isLoading, error } = useQuery({
    queryKey: ['my-provider'],
    queryFn: () => getMyProvider().then((r) => r.data),
    enabled: auth.status === 'authenticated',
    retry: false,
  });

  const linkMutation = useMutation({
    mutationFn: linkProvider,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['my-provider'] });
      toast.success('Provider profile linked');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  if (auth.status === 'unauthenticated') {
    return <Navigate to="/login" replace />;
  }

  if (auth.status === 'loading' || isLoading) {
    return <p className="text-muted-foreground">Loading...</p>;
  }

  const notLinked = error && (error as { status?: number }).status === 404;

  if (notLinked) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Become a provider</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-muted-foreground">
            Link your account to an individual provider profile to publish openings.
          </p>
          <Button onClick={() => linkMutation.mutate()} disabled={linkMutation.isPending}>
            {linkMutation.isPending ? 'Linking...' : 'Become a provider'}
          </Button>
        </CardContent>
      </Card>
    );
  }

  if (error || !provider) {
    return <p className="text-muted-foreground">Something went wrong. Try again later.</p>;
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <h1 className="text-3xl font-semibold">Provider area</h1>
        <Badge variant={provider.status === 'active' ? 'default' : 'secondary'}>{provider.status}</Badge>
      </div>
      <p className="text-muted-foreground">{provider.display_name}</p>

      <OfferingsSection providerId={provider.provider_id} />
      <OpeningsSection providerId={provider.provider_id} />
      <BookingsSection providerId={provider.provider_id} />
    </div>
  );
}
