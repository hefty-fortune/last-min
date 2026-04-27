import {
  Card as ShadcnCard,
  CardHeader as ShadcnCardHeader,
  CardFooter as ShadcnCardFooter,
  CardTitle as ShadcnCardTitle,
  CardDescription as ShadcnCardDescription,
  CardContent as ShadcnCardContent,
} from '@/components/ui/card';

type CardProps = React.ComponentProps<typeof ShadcnCard>;
type CardHeaderProps = React.ComponentProps<typeof ShadcnCardHeader>;
type CardFooterProps = React.ComponentProps<typeof ShadcnCardFooter>;
type CardTitleProps = React.ComponentProps<typeof ShadcnCardTitle>;
type CardDescriptionProps = React.ComponentProps<typeof ShadcnCardDescription>;
type CardContentProps = React.ComponentProps<typeof ShadcnCardContent>;

function Card(props: CardProps) {
  return <ShadcnCard {...props} />;
}

function CardHeader(props: CardHeaderProps) {
  return <ShadcnCardHeader {...props} />;
}

function CardFooter(props: CardFooterProps) {
  return <ShadcnCardFooter {...props} />;
}

function CardTitle(props: CardTitleProps) {
  return <ShadcnCardTitle {...props} />;
}

function CardDescription(props: CardDescriptionProps) {
  return <ShadcnCardDescription {...props} />;
}

function CardContent(props: CardContentProps) {
  return <ShadcnCardContent {...props} />;
}

export { Card, CardHeader, CardFooter, CardTitle, CardDescription, CardContent };
export type {
  CardProps,
  CardHeaderProps,
  CardFooterProps,
  CardTitleProps,
  CardDescriptionProps,
  CardContentProps,
};
