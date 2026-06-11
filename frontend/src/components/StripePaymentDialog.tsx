import { useState } from 'react';
import { loadStripe, type Stripe } from '@stripe/stripe-js';
import { Elements, PaymentElement, useStripe, useElements } from '@stripe/react-stripe-js';
import {
  Button,
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/common';
import { toast } from 'sonner';

const publishableKey = import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY ?? '';

let stripePromise: Promise<Stripe | null> | null = null;
function getStripe(): Promise<Stripe | null> {
  if (!stripePromise && publishableKey) {
    stripePromise = loadStripe(publishableKey);
  }
  return stripePromise ?? Promise.resolve(null);
}

export const stripeElementsAvailable = (): boolean => publishableKey !== '';

function CheckoutForm({ onDone }: { onDone: () => void }) {
  const stripe = useStripe();
  const elements = useElements();
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!stripe || !elements) return;
    setSubmitting(true);

    const { error } = await stripe.confirmPayment({
      elements,
      confirmParams: { return_url: window.location.href },
      redirect: 'if_required',
    });

    setSubmitting(false);
    if (error) {
      toast.error(error.message ?? 'Payment failed.');
      return;
    }
    toast.success('Payment submitted — confirmation arrives via Stripe shortly.');
    onDone();
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <PaymentElement />
      <Button type="submit" className="w-full" disabled={!stripe || submitting}>
        {submitting ? 'Processing...' : 'Pay now'}
      </Button>
    </form>
  );
}

export function StripePaymentDialog({
  clientSecret,
  open,
  onOpenChange,
  onDone,
}: {
  clientSecret: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onDone: () => void;
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Complete your payment</DialogTitle>
        </DialogHeader>
        <Elements stripe={getStripe()} options={{ clientSecret }}>
          <CheckoutForm onDone={onDone} />
        </Elements>
      </DialogContent>
    </Dialog>
  );
}
