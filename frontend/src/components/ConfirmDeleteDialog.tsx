import { useState } from 'react';
import {
  Button,
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/common';

type ConfirmDeleteDialogProps = {
  title: string;
  description: string;
  onConfirm: () => void;
  pending?: boolean;
  triggerLabel?: string;
  triggerVariant?: 'destructive' | 'ghost' | 'outline';
  triggerSize?: 'sm' | 'default';
};

export function ConfirmDeleteDialog({
  title,
  description,
  onConfirm,
  pending = false,
  triggerLabel = 'Delete',
  triggerVariant = 'destructive',
  triggerSize = 'sm',
}: ConfirmDeleteDialogProps) {
  const [open, setOpen] = useState(false);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger render={<Button variant={triggerVariant} size={triggerSize} />}>
        {triggerLabel}
      </DialogTrigger>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>
        <p className="text-sm text-muted-foreground">{description}</p>
        <div className="flex justify-end gap-2">
          <Button variant="outline" onClick={() => setOpen(false)}>
            Cancel
          </Button>
          <Button
            variant="destructive"
            disabled={pending}
            onClick={() => {
              onConfirm();
              setOpen(false);
            }}
          >
            {pending ? 'Deleting...' : 'Yes, delete'}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
