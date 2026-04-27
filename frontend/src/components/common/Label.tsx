import { Label as ShadcnLabel } from '@/components/ui/label';

type LabelProps = React.ComponentProps<typeof ShadcnLabel>;

function Label(props: LabelProps) {
  return <ShadcnLabel {...props} />;
}

export { Label };
export type { LabelProps };
