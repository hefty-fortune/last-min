import { useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { listPublicOpenings, createBooking, type PublicOpening } from '@/lib/api';
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

function formatPrice(o: PublicOpening): string {
  const p = o.price_snapshot;
  return p ? `${(p.amount_minor / 100).toFixed(2)} ${p.currency}` : '';
}

function formatWhen(iso: string): string {
  return new Date(iso).toLocaleString(undefined, {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export default function MarketPage() {
  const auth = useAuth();
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const { data: openings, isLoading } = useQuery({
    queryKey: ['public-openings'],
    queryFn: () => listPublicOpenings().then((r) => r.data),
  });

  const bookMutation = useMutation({
    mutationFn: (openingId: string) => createBooking(openingId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['public-openings'] });
      queryClient.invalidateQueries({ queryKey: ['my-bookings'] });
      toast.success('Reserved! Complete the payment in My bookings.');
      navigate('/my-bookings');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const handleBook = (openingId: string) => {
    if (auth.status !== 'authenticated') {
      toast.info('Sign in to book.');
      navigate('/login');
      return;
    }
    bookMutation.mutate(openingId);
  };

  // Booking is a client action; providers and admins browse the market
  // to see how their slots look to customers.
  const canBook = auth.status !== 'authenticated' || auth.me.roles.includes('client');

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Last-minute openings</h1>
        <p className="text-muted-foreground mt-1">
          {canBook
            ? 'Grab a freshly freed slot before someone else does.'
            : 'Browsing as a non-client account — this is how your slots appear to customers.'}
        </p>
      </div>

      {isLoading ? (
        <p className="text-muted-foreground">Loading openings...</p>
      ) : !openings?.length ? (
        <Card>
          <CardContent className="py-10 text-center text-muted-foreground">
            Nothing available right now — check back soon.
          </CardContent>
        </Card>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {openings.map((o) => (
            <Card key={o.opening_id} className="flex flex-col">
              <CardHeader>
                <CardTitle className="text-lg">{o.offering_name ?? 'Service'}</CardTitle>
                <p className="text-sm text-muted-foreground">
                  {o.provider_display_name ?? 'Provider'}
                </p>
              </CardHeader>
              <CardContent className="flex flex-col gap-3 grow">
                <div className="text-sm space-y-1">
                  <p>
                    <span className="text-muted-foreground">Starts:</span> {formatWhen(o.starts_at)}
                  </p>
                  {o.offering_duration_minutes != null && (
                    <p>
                      <span className="text-muted-foreground">Duration:</span> {o.offering_duration_minutes} min
                    </p>
                  )}
                </div>
                <div className="mt-auto flex items-center justify-between">
                  <Badge variant="secondary" className="text-base">
                    {formatPrice(o)}
                  </Badge>
                  {canBook && (
                    <Button onClick={() => handleBook(o.opening_id)} disabled={bookMutation.isPending}>
                      {bookMutation.isPending ? 'Booking...' : 'Book now'}
                    </Button>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
