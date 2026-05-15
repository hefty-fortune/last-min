import { Toaster as ShadcnToaster } from '@/components/ui/sonner';

type ToasterProps = React.ComponentProps<typeof ShadcnToaster>;

function Toaster(props: ToasterProps) {
  return <ShadcnToaster {...props} />;
}

export { Toaster };
export type { ToasterProps };
