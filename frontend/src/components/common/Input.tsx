import { Input as ShadcnInput } from '@/components/ui/input';

type InputProps = React.ComponentProps<typeof ShadcnInput>;

function Input(props: InputProps) {
  return <ShadcnInput {...props} />;
}

export { Input };
export type { InputProps };
