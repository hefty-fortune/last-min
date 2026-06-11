import { Navigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  listMyBookings,
  getBooking,
  initiatePayment,
  simulatePaymentSucceed,
  simulatePaymentFail,
  type MyBooking,
} from '@/lib/api';
import { useAuth } from '@/lib/auth';
import {
  Button,
  Badge,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/common';
import { toast } from 'sonner';

const ACTIVE_STATES = ['reserved', 'payment_pending'];

const stateVariant = (state: string) => {
  if (state === 'confirmed') return 'default';
  if (state === 'payment_failed' || state === 'provider_no_show' || state === 'client_no_show') return 'destructive';
  return 'secondary';
};

function BookingCard({ booking, canSimulate }: { booking: MyBooking; canSimulate: boolean }) {
  const queryClient = useQueryClient();
  const isActive = ACTIVE_STATES.includes(booking.state);

  const { data: detail } = useQuery({
    queryKey: ['booking', booking.booking_id],
    queryFn: () => getBooking(booking.booking_id).then((r) => r.data),
    enabled: isActive,
  });

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['my-bookings'] });
    queryClient.invalidateQueries({ queryKey: ['booking', booking.booking_id] });
  };

  const payMutation = useMutation({
    mutationFn: () => initiatePayment(booking.booking_id),
    onSuccess: () => {
      refresh();
      toast.success('Payment started.');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const succeedMutation = useMutation({
    mutationFn: (paymentId: string) => simulatePaymentSucceed(paymentId),
    onSuccess: () => {
      refresh();
      toast.success('Payment complete — booking confirmed. See you there!');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const failMutation = useMutation({
    mutationFn: (paymentId: string) => simulatePaymentFail(paymentId),
    onSuccess: () => {
      refresh();
      toast.success('Payment failed — the slot was released.');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const payment = detail?.payment ?? null;

  return (
    <Card>
      <CardContent className="py-4 flex flex-wrap items-center justify-between gap-3">
        <div className="space-y-1">
          <p className="font-mono text-xs text-muted-foreground">{booking.booking_id.slice(0, 8)}…</p>
          <p className="font-medium">
            {(booking.amount.amount_minor / 100).toFixed(2)} {booking.amount.currency}
          </p>
          {booking.state === 'reserved' && booking.expires_at && (
            <p className="text-xs text-muted-foreground">
              Reservation expires {new Date(booking.expires_at).toLocaleTimeString()}
            </p>
          )}
        </div>
        <div className="flex items-center gap-2">
          <Badge variant={stateVariant(booking.state)}>{booking.state}</Badge>
          {payment && <Badge variant="outline">payment: {payment.state}</Badge>}
          {isActive && !payment && (
            <Button size="sm" onClick={() => payMutation.mutate()} disabled={payMutation.isPending}>
              {payMutation.isPending ? 'Starting...' : 'Pay'}
            </Button>
          )}
          {isActive && payment?.state === 'initiated' && canSimulate && (
            <>
              <Button size="sm" onClick={() => succeedMutation.mutate(payment.payment_id)} disabled={succeedMutation.isPending}>
                Complete payment (simulated)
              </Button>
              <Button size="sm" variant="outline" onClick={() => failMutation.mutate(payment.payment_id)} disabled={failMutation.isPending}>
                Fail payment (simulated)
              </Button>
            </>
          )}
          {isActive && payment?.state === 'initiated' && !canSimulate && (
            <span className="text-xs text-muted-foreground">Waiting for payment confirmation…</span>
          )}
        </div>
      </CardContent>
    </Card>
  );
}

export default function MyBookingsPage() {
  const auth = useAuth();

  const { data: bookings, isLoading } = useQuery({
    queryKey: ['my-bookings'],
    queryFn: () => listMyBookings().then((r) => r.data),
    enabled: auth.status === 'authenticated',
  });

  if (auth.status === 'unauthenticated') {
    return <Navigate to="/login" replace />;
  }

  const canSimulate =
    auth.status === 'authenticated' && auth.me.roles.some((r) => r === 'admin' || r === 'super-admin');

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">My bookings</h1>
        <p className="text-muted-foreground mt-1">Reservations, payments, and history.</p>
      </div>

      {auth.status === 'loading' || isLoading ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : !bookings?.length ? (
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">No bookings yet</CardTitle>
          </CardHeader>
          <CardContent className="text-muted-foreground">
            Find a last-minute opening on the Market page and book it.
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-3">
          {bookings.map((b) => (
            <BookingCard key={b.booking_id} booking={b} canSimulate={canSimulate} />
          ))}
        </div>
      )}
    </div>
  );
}
