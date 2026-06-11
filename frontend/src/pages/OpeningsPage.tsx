import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  listProviders,
  listOfferings,
  createOffering,
  listOpenings,
  createOpening,
  publishOpening,
  cancelOpening,
  createBooking,
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

const statusVariant = (status: string) =>
  status === 'published' ? 'default' : status === 'booked' ? 'secondary' : 'outline';

function plusHours(hours: number): string {
  const d = new Date(Date.now() + hours * 3600_000);
  d.setSeconds(0, 0);
  return d.toISOString().slice(0, 16);
}

export default function OpeningsPage() {
  const [providerId, setProviderId] = useState('');
  const [offeringOpen, setOfferingOpen] = useState(false);
  const [openingOpen, setOpeningOpen] = useState(false);
  const [offeringForm, setOfferingForm] = useState({ name: '', duration_minutes: 30, amount_minor: 2500 });
  const [openingForm, setOpeningForm] = useState({ service_offering_id: '', starts_at: plusHours(2), ends_at: plusHours(3) });
  const queryClient = useQueryClient();

  const { data: providers } = useQuery({
    queryKey: ['providers'],
    queryFn: () => listProviders().then((r) => r.data),
  });

  const { data: offerings } = useQuery({
    queryKey: ['offerings', providerId],
    queryFn: () => listOfferings(providerId).then((r) => r.data),
    enabled: !!providerId,
  });

  const { data: openings, isLoading } = useQuery({
    queryKey: ['openings', providerId],
    queryFn: () => listOpenings(providerId).then((r) => r.data),
    enabled: !!providerId,
  });

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['openings'] });
    queryClient.invalidateQueries({ queryKey: ['offerings'] });
    queryClient.invalidateQueries({ queryKey: ['admin-bookings'] });
  };

  const offeringMutation = useMutation({
    mutationFn: () =>
      createOffering(providerId, {
        name: offeringForm.name,
        duration_minutes: Number(offeringForm.duration_minutes),
        base_price: { currency: 'EUR', amount_minor: Number(offeringForm.amount_minor) },
      }),
    onSuccess: () => {
      refresh();
      setOfferingOpen(false);
      toast.success('Offering created');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const openingMutation = useMutation({
    mutationFn: () =>
      createOpening(providerId, {
        service_offering_id: openingForm.service_offering_id,
        starts_at: new Date(openingForm.starts_at).toISOString(),
        ends_at: new Date(openingForm.ends_at).toISOString(),
      }),
    onSuccess: () => {
      refresh();
      setOpeningOpen(false);
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

  const bookMutation = useMutation({
    mutationFn: (openingId: string) => createBooking(openingId),
    onSuccess: (r) => {
      refresh();
      toast.success(`Booked! Booking ${r.data.booking_id.slice(0, 8)}… is reserved — continue on the Bookings page.`);
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Openings</h1>
        <div className="flex gap-2">
          <Dialog open={offeringOpen} onOpenChange={setOfferingOpen}>
            <DialogTrigger render={<Button variant="outline" disabled={!providerId} />}>New Offering</DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>New Service Offering</DialogTitle>
              </DialogHeader>
              <form
                className="space-y-4"
                onSubmit={(e) => {
                  e.preventDefault();
                  offeringMutation.mutate();
                }}
              >
                <div className="space-y-2">
                  <Label>Name</Label>
                  <Input value={offeringForm.name} onChange={(e) => setOfferingForm((f) => ({ ...f, name: e.target.value }))} required />
                </div>
                <div className="space-y-2">
                  <Label>Duration (minutes)</Label>
                  <Input
                    type="number"
                    min={5}
                    value={offeringForm.duration_minutes}
                    onChange={(e) => setOfferingForm((f) => ({ ...f, duration_minutes: Number(e.target.value) }))}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label>Price (EUR cents)</Label>
                  <Input
                    type="number"
                    min={0}
                    value={offeringForm.amount_minor}
                    onChange={(e) => setOfferingForm((f) => ({ ...f, amount_minor: Number(e.target.value) }))}
                    required
                  />
                </div>
                <Button type="submit" className="w-full" disabled={offeringMutation.isPending}>
                  {offeringMutation.isPending ? 'Creating...' : 'Create Offering'}
                </Button>
              </form>
            </DialogContent>
          </Dialog>
          <Dialog open={openingOpen} onOpenChange={setOpeningOpen}>
            <DialogTrigger render={<Button disabled={!providerId} />}>New Opening</DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>New Opening (draft)</DialogTitle>
              </DialogHeader>
              <form
                className="space-y-4"
                onSubmit={(e) => {
                  e.preventDefault();
                  openingMutation.mutate();
                }}
              >
                <div className="space-y-2">
                  <Label>Offering</Label>
                  <select
                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                    value={openingForm.service_offering_id}
                    onChange={(e) => setOpeningForm((f) => ({ ...f, service_offering_id: e.target.value }))}
                    required
                  >
                    <option value="">Select offering...</option>
                    {offerings?.map((o) => (
                      <option key={o.offering_id} value={o.offering_id}>
                        {o.name} ({(o.base_price.amount_minor / 100).toFixed(2)} {o.base_price.currency})
                      </option>
                    ))}
                  </select>
                </div>
                <div className="space-y-2">
                  <Label>Starts At</Label>
                  <Input
                    type="datetime-local"
                    value={openingForm.starts_at}
                    onChange={(e) => setOpeningForm((f) => ({ ...f, starts_at: e.target.value }))}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label>Ends At</Label>
                  <Input
                    type="datetime-local"
                    value={openingForm.ends_at}
                    onChange={(e) => setOpeningForm((f) => ({ ...f, ends_at: e.target.value }))}
                    required
                  />
                </div>
                <Button type="submit" className="w-full" disabled={openingMutation.isPending}>
                  {openingMutation.isPending ? 'Creating...' : 'Create Draft'}
                </Button>
              </form>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      <div className="flex gap-2 items-center">
        <Label className="text-sm">Provider:</Label>
        <select
          className="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
          value={providerId}
          onChange={(e) => setProviderId(e.target.value)}
        >
          <option value="">Select provider...</option>
          {providers?.map((p) => (
            <option key={p.provider_id} value={p.provider_id}>
              {p.display_name || p.provider_id.slice(0, 8)}
            </option>
          ))}
        </select>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Openings</CardTitle>
        </CardHeader>
        <CardContent>
          {!providerId ? (
            <p className="text-muted-foreground">Select a provider to manage its openings.</p>
          ) : isLoading ? (
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
                    <TableCell>
                      {o.price_snapshot ? `${(o.price_snapshot.amount_minor / 100).toFixed(2)} ${o.price_snapshot.currency}` : '—'}
                    </TableCell>
                    <TableCell>
                      <Badge variant={statusVariant(o.status)}>{o.status}</Badge>
                    </TableCell>
                    <TableCell className="text-right space-x-2">
                      {o.status === 'draft' && (
                        <Button size="sm" onClick={() => publishMutation.mutate(o.opening_id)} disabled={publishMutation.isPending}>
                          Publish
                        </Button>
                      )}
                      {o.status === 'published' && (
                        <Button size="sm" onClick={() => bookMutation.mutate(o.opening_id)} disabled={bookMutation.isPending}>
                          Book
                        </Button>
                      )}
                      {(o.status === 'draft' || o.status === 'published') && (
                        <Button size="sm" variant="outline" onClick={() => cancelMutation.mutate(o.opening_id)} disabled={cancelMutation.isPending}>
                          Cancel
                        </Button>
                      )}
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
