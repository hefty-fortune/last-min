import { Button as ShadcnButton, buttonVariants } from '@/components/ui/button';

type ButtonProps = React.ComponentProps<typeof ShadcnButton>;

function Button(props: ButtonProps) {
  return <ShadcnButton {...props} />;
}

export { Button, buttonVariants };
export type { ButtonProps };
