import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { listAdminRefunds, approveRefund } from '@/lib/api';
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

const REFUND_STATES = ['', 'requested', 'pending', 'succeeded', 'failed'];

const stateVariant = (state: string) => {
  if (state === 'requested') return 'destructive';
  if (state === 'pending') return 'default';
  return 'secondary';
};

export default function RefundsPage() {
  const [filterState, setFilterState] = useState('');
  const queryClient = useQueryClient();

  const { data: refunds, isLoading } = useQuery({
    queryKey: ['admin-refunds', filterState],
    queryFn: () => listAdminRefunds(filterState || undefined).then((r) => r.data),
  });

  const approveMutation = useMutation({
    mutationFn: (refundId: string) => approveRefund(refundId, 'Approved via dashboard'),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['admin-refunds'] });
      toast.success('Refund approved — queued for gateway execution (extension point).');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Refunds</h1>
        <div className="flex gap-2 items-center">
          <Label className="text-sm">State:</Label>
          <select
            className="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm"
            value={filterState}
            onChange={(e) => setFilterState(e.target.value)}
          >
            {REFUND_STATES.map((s) => (
              <option key={s} value={s}>
                {s || 'All'}
              </option>
            ))}
          </select>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All refunds</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <p className="text-muted-foreground">Loading...</p>
          ) : !refunds?.length ? (
            <p className="text-muted-foreground">
              No refunds yet. A provider no-show on a confirmed booking creates one automatically.
            </p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Refund</TableHead>
                  <TableHead>Booking</TableHead>
                  <TableHead>Amount</TableHead>
                  <TableHead>Reason</TableHead>
                  <TableHead>State</TableHead>
                  <TableHead>Decided by</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {refunds.map((r) => (
                  <TableRow key={r.refund_id}>
                    <TableCell className="font-mono text-xs">{r.refund_id.slice(0, 8)}…</TableCell>
                    <TableCell className="font-mono text-xs">{r.booking_id.slice(0, 8)}…</TableCell>
                    <TableCell>
                      {(r.amount.amount_minor / 100).toFixed(2)} {r.amount.currency}
                    </TableCell>
                    <TableCell>{r.reason}</TableCell>
                    <TableCell>
                      <Badge variant={stateVariant(r.state)}>{r.state}</Badge>
                    </TableCell>
                    <TableCell className="text-xs text-muted-foreground">{r.decided_by_actor_id ?? '—'}</TableCell>
                    <TableCell className="text-right">
                      {r.state === 'requested' && (
                        <Button size="sm" onClick={() => approveMutation.mutate(r.refund_id)} disabled={approveMutation.isPending}>
                          Approve
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
