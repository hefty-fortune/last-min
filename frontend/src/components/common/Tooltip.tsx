import {
  Tooltip as ShadcnTooltip,
  TooltipTrigger as ShadcnTooltipTrigger,
  TooltipContent as ShadcnTooltipContent,
  TooltipProvider as ShadcnTooltipProvider,
} from '@/components/ui/tooltip';

type TooltipProps = React.ComponentProps<typeof ShadcnTooltip>;
type TooltipTriggerProps = React.ComponentProps<typeof ShadcnTooltipTrigger>;
type TooltipContentProps = React.ComponentProps<typeof ShadcnTooltipContent>;
type TooltipProviderProps = React.ComponentProps<typeof ShadcnTooltipProvider>;

function Tooltip(props: TooltipProps) {
  return <ShadcnTooltip {...props} />;
}
function TooltipTrigger(props: TooltipTriggerProps) {
  return <ShadcnTooltipTrigger {...props} />;
}
function TooltipContent(props: TooltipContentProps) {
  return <ShadcnTooltipContent {...props} />;
}
function TooltipProvider(props: TooltipProviderProps) {
  return <ShadcnTooltipProvider {...props} />;
}

export { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider };
