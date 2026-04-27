import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { listApiKeys, createApiKey, revokeApiKey, type ApiKeyCreated } from '@/lib/api';
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

export default function ApiKeysPage() {
  const [open, setOpen] = useState(false);
  const [created, setCreated] = useState<ApiKeyCreated | null>(null);
  const [clientId, setClientId] = useState('');
  const [name, setName] = useState('');
  const queryClient = useQueryClient();

  const { data, isLoading } = useQuery({
    queryKey: ['api-keys'],
    queryFn: () => listApiKeys().then((r) => r.data),
  });

  const createMutation = useMutation({
    mutationFn: createApiKey,
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['api-keys'] });
      setCreated(res.data);
      setOpen(false);
      setClientId('');
      setName('');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const revokeMutation = useMutation({
    mutationFn: revokeApiKey,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['api-keys'] });
      toast.success('API key revoked');
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">API Keys</h1>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger render={<Button />}>Create API Key</DialogTrigger>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>New API Key</DialogTitle>
            </DialogHeader>
            <form
              className="space-y-4"
              onSubmit={(e) => {
                e.preventDefault();
                createMutation.mutate({
                  client_id: clientId,
                  ...(name ? { name } : {}),
                });
              }}
            >
              <div className="space-y-2">
                <Label>Client ID (UUID)</Label>
                <Input
                  value={clientId}
                  onChange={(e) => setClientId(e.target.value)}
                  placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label>Name (optional)</Label>
                <Input
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="My API key"
                />
              </div>
              <Button type="submit" className="w-full" disabled={createMutation.isPending}>
                {createMutation.isPending ? 'Creating...' : 'Create'}
              </Button>
            </form>
          </DialogContent>
        </Dialog>
      </div>

      {created && (
        <Dialog open={!!created} onOpenChange={() => setCreated(null)}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>API Key Created</DialogTitle>
            </DialogHeader>
            <div className="space-y-3">
              <p className="text-sm text-muted-foreground">
                Copy this key now. It will not be shown again.
              </p>
              <div className="p-3 bg-muted rounded-md font-mono text-sm break-all select-all">
                {created.api_key}
              </div>
              <div className="text-sm">
                <span className="text-muted-foreground">Name: </span>
                {created.name}
              </div>
              <Button
                className="w-full"
                onClick={() => {
                  navigator.clipboard.writeText(created.api_key);
                  toast.success('Copied to clipboard');
                }}
              >
                Copy Key
              </Button>
            </div>
          </DialogContent>
        </Dialog>
      )}

      <Card>
        <CardHeader>
          <CardTitle>All API Keys</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <p className="text-muted-foreground">Loading...</p>
          ) : !data?.length ? (
            <p className="text-muted-foreground">No API keys yet.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>API Key ID</TableHead>
                  <TableHead>Client ID</TableHead>
                  <TableHead />
                </TableRow>
              </TableHeader>
              <TableBody>
                {data.map((key) => (
                  <TableRow key={key.api_key_id}>
                    <TableCell className="font-medium">{key.name}</TableCell>
                    <TableCell className="font-mono text-xs">{key.api_key_id}</TableCell>
                    <TableCell className="font-mono text-xs">{key.client_id}</TableCell>
                    <TableCell>
                      <Button
                        size="sm"
                        variant="destructive"
                        disabled={revokeMutation.isPending}
                        onClick={() => revokeMutation.mutate(key.api_key_id)}
                      >
                        Revoke
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
