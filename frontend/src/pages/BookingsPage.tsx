import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  listAdminBookings,
  initiatePayment,
  simulatePaymentSucceed,
  simulatePaymentFail,
  markProviderNoShow,
  markClientNoShow,
} from '@/lib/api';
import {
  Button,
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
} from '@/components/common';
import { toast } from 'sonner';

const BOOKING_STATES = ['', 'reserved', 'payment_pending', 'confirmed', 'payment_failed', 'provider_no_show', 'client_no_show'];

const stateVariant = (state: string) => {
  if (state === 'confirmed') return 'default';
  if (state === 'provider_no_show' || state === 'client_no_show' || state === 'payment_failed') return 'destructive';
  return 'secondary';
};

export default function BookingsPage() {
  const [filterState, setFilterState] = useState('');
  const queryClient = useQueryClient();

  const { data: bookings, isLoading } = useQuery({
    queryKey: ['admin-bookings', filterState],
    queryFn: () => listAdminBookings(filterState || undefined).then((r) => r.data),
  });

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['admin-bookings'] });
    queryClient.invalidateQueries({ queryKey: ['admin-refunds'] });
    queryClient.invalidateQueries({ queryKey: ['openings'] });
  };

  const payMutation = useMutation({
    mutationFn: (bookingId: string) => initiatePayment(bookingId),
    onSuccess: () => {
      refresh();
      toast.success('Payment initiated (stub gateway) — now simulate the outcome.');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const succeedMutation = useMutation({
    mutationFn: (paymentId: string) => simulatePaymentSucceed(paymentId),
    onSuccess: () => {
      refresh();
      toast.success('Payment captured — booking confirmed.');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const failMutation = useMutation({
    mutationFn: (paymentId: string) => simulatePaymentFail(paymentId),
    onSuccess: () => {
      refresh();
      toast.success('Payment failed — opening released back to public.');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const providerNoShowMutation = useMutation({
    mutationFn: (bookingId: string) => markProviderNoShow(bookingId),
    onSuccess: () => {
      refresh();
      toast.success('Provider no-show recorded — refund request created. See Refunds page.');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const clientNoShowMutation = useMutation({
    mutationFn: (bookingId: string) => markClientNoShow(bookingId),
    onSuccess: () => {
      refresh();
      toast.success('Client no-show recorded — service consumed, no refund.');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Bookings</h1>
        <div className="flex gap-2 items-center">
          <Label className="text-sm">State:</Label>
          <select
            className="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
            value={filterState}
            onChange={(e) => setFilterState(e.target.value)}
          >
            {BOOKING_STATES.map((s) => (
              <option key={s} value={s}>
                {s || 'All'}
              </option>
            ))}
          </select>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Bookings</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <p className="text-muted-foreground">Loading...</p>
          ) : !bookings?.length ? (
            <p className="text-muted-foreground">
              No bookings yet. Go to Openings, publish one, and click Book.
            </p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Booking</TableHead>
                  <TableHead>Amount</TableHead>
                  <TableHead>State</TableHead>
                  <TableHead>Payment</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {bookings.map((b) => (
                  <TableRow key={b.booking_id}>
                    <TableCell className="font-mono text-xs">{b.booking_id.slice(0, 8)}…</TableCell>
                    <TableCell>
                      {(b.amount.amount_minor / 100).toFixed(2)} {b.amount.currency}
                    </TableCell>
                    <TableCell>
                      <Badge variant={stateVariant(b.state)}>{b.state}</Badge>
                    </TableCell>
                    <TableCell>
                      {b.payment ? <Badge variant="outline">{b.payment.state}</Badge> : <span className="text-muted-foreground">—</span>}
                    </TableCell>
                    <TableCell className="text-right space-x-2">
                      {b.state === 'reserved' && !b.payment && (
                        <Button size="sm" onClick={() => payMutation.mutate(b.booking_id)} disabled={payMutation.isPending}>
                          Pay
                        </Button>
                      )}
                      {b.payment && b.payment.state === 'initiated' && (
                        <>
                          <Button size="sm" onClick={() => succeedMutation.mutate(b.payment!.payment_id)} disabled={succeedMutation.isPending}>
                            Simulate Success
                          </Button>
                          <Button size="sm" variant="outline" onClick={() => failMutation.mutate(b.payment!.payment_id)} disabled={failMutation.isPending}>
                            Simulate Failure
                          </Button>
                        </>
                      )}
                      {b.state === 'confirmed' && (
                        <>
                          <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => providerNoShowMutation.mutate(b.booking_id)}
                            disabled={providerNoShowMutation.isPending}
                          >
                            Provider No-Show
                          </Button>
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => clientNoShowMutation.mutate(b.booking_id)}
                            disabled={clientNoShowMutation.isPending}
                          >
                            Client No-Show
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
    </div>
  );
}
