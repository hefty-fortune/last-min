import {
  Sidebar as ShadcnSidebar,
  SidebarContent as ShadcnSidebarContent,
  SidebarFooter as ShadcnSidebarFooter,
  SidebarGroup as ShadcnSidebarGroup,
  SidebarGroupContent as ShadcnSidebarGroupContent,
  SidebarGroupLabel as ShadcnSidebarGroupLabel,
  SidebarHeader as ShadcnSidebarHeader,
  SidebarMenu as ShadcnSidebarMenu,
  SidebarMenuButton as ShadcnSidebarMenuButton,
  SidebarMenuItem as ShadcnSidebarMenuItem,
  SidebarProvider as ShadcnSidebarProvider,
  SidebarTrigger as ShadcnSidebarTrigger,
} from '@/components/ui/sidebar';

type SidebarProps = React.ComponentProps<typeof ShadcnSidebar>;
type SidebarContentProps = React.ComponentProps<typeof ShadcnSidebarContent>;
type SidebarFooterProps = React.ComponentProps<typeof ShadcnSidebarFooter>;
type SidebarGroupProps = React.ComponentProps<typeof ShadcnSidebarGroup>;
type SidebarGroupContentProps = React.ComponentProps<typeof ShadcnSidebarGroupContent>;
type SidebarGroupLabelProps = React.ComponentProps<typeof ShadcnSidebarGroupLabel>;
type SidebarHeaderProps = React.ComponentProps<typeof ShadcnSidebarHeader>;
type SidebarMenuProps = React.ComponentProps<typeof ShadcnSidebarMenu>;
type SidebarMenuButtonProps = React.ComponentProps<typeof ShadcnSidebarMenuButton>;
type SidebarMenuItemProps = React.ComponentProps<typeof ShadcnSidebarMenuItem>;
type SidebarProviderProps = React.ComponentProps<typeof ShadcnSidebarProvider>;
type SidebarTriggerProps = React.ComponentProps<typeof ShadcnSidebarTrigger>;

function Sidebar(props: SidebarProps) {
  return <ShadcnSidebar {...props} />;
}
function SidebarContent(props: SidebarContentProps) {
  return <ShadcnSidebarContent {...props} />;
}
function SidebarFooter(props: SidebarFooterProps) {
  return <ShadcnSidebarFooter {...props} />;
}
function SidebarGroup(props: SidebarGroupProps) {
  return <ShadcnSidebarGroup {...props} />;
}
function SidebarGroupContent(props: SidebarGroupContentProps) {
  return <ShadcnSidebarGroupContent {...props} />;
}
function SidebarGroupLabel(props: SidebarGroupLabelProps) {
  return <ShadcnSidebarGroupLabel {...props} />;
}
function SidebarHeader(props: SidebarHeaderProps) {
  return <ShadcnSidebarHeader {...props} />;
}
function SidebarMenu(props: SidebarMenuProps) {
  return <ShadcnSidebarMenu {...props} />;
}
function SidebarMenuButton(props: SidebarMenuButtonProps) {
  return <ShadcnSidebarMenuButton {...props} />;
}
function SidebarMenuItem(props: SidebarMenuItemProps) {
  return <ShadcnSidebarMenuItem {...props} />;
}
function SidebarProvider(props: SidebarProviderProps) {
  return <ShadcnSidebarProvider {...props} />;
}
function SidebarTrigger(props: SidebarTriggerProps) {
  return <ShadcnSidebarTrigger {...props} />;
}

export {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarTrigger,
};
