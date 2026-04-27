import { Badge as ShadcnBadge, badgeVariants } from '@/components/ui/badge';

type BadgeProps = React.ComponentProps<typeof ShadcnBadge>;

function Badge(props: BadgeProps) {
  return <ShadcnBadge {...props} />;
}

export { Badge, badgeVariants };
export type { BadgeProps };
