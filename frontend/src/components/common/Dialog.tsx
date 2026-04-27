import {
  Dialog as ShadcnDialog,
  DialogClose as ShadcnDialogClose,
  DialogContent as ShadcnDialogContent,
  DialogDescription as ShadcnDialogDescription,
  DialogFooter as ShadcnDialogFooter,
  DialogHeader as ShadcnDialogHeader,
  DialogTitle as ShadcnDialogTitle,
  DialogTrigger as ShadcnDialogTrigger,
} from '@/components/ui/dialog';

type DialogProps = React.ComponentProps<typeof ShadcnDialog>;
type DialogCloseProps = React.ComponentProps<typeof ShadcnDialogClose>;
type DialogContentProps = React.ComponentProps<typeof ShadcnDialogContent>;
type DialogDescriptionProps = React.ComponentProps<typeof ShadcnDialogDescription>;
type DialogFooterProps = React.ComponentProps<typeof ShadcnDialogFooter>;
type DialogHeaderProps = React.ComponentProps<typeof ShadcnDialogHeader>;
type DialogTitleProps = React.ComponentProps<typeof ShadcnDialogTitle>;
type DialogTriggerProps = React.ComponentProps<typeof ShadcnDialogTrigger>;

function Dialog(props: DialogProps) {
  return <ShadcnDialog {...props} />;
}
function DialogClose(props: DialogCloseProps) {
  return <ShadcnDialogClose {...props} />;
}
function DialogContent(props: DialogContentProps) {
  return <ShadcnDialogContent {...props} />;
}
function DialogDescription(props: DialogDescriptionProps) {
  return <ShadcnDialogDescription {...props} />;
}
function DialogFooter(props: DialogFooterProps) {
  return <ShadcnDialogFooter {...props} />;
}
function DialogHeader(props: DialogHeaderProps) {
  return <ShadcnDialogHeader {...props} />;
}
function DialogTitle(props: DialogTitleProps) {
  return <ShadcnDialogTitle {...props} />;
}
function DialogTrigger(props: DialogTriggerProps) {
  return <ShadcnDialogTrigger {...props} />;
}

export {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
};
