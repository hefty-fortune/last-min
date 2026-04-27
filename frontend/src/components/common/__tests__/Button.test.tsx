import { render } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { Button } from '../Button';

describe('Button', () => {
  it('renders default button', () => {
    const { container } = render(<Button>Click me</Button>);
    expect(container).toMatchSnapshot();
  });

  it('renders disabled button', () => {
    const { container } = render(<Button disabled>Disabled</Button>);
    expect(container).toMatchSnapshot();
  });

  it('renders destructive variant', () => {
    const { container } = render(<Button variant="destructive">Delete</Button>);
    expect(container).toMatchSnapshot();
  });

  it('renders outline variant', () => {
    const { container } = render(<Button variant="outline">Outline</Button>);
    expect(container).toMatchSnapshot();
  });

  it('renders small size', () => {
    const { container } = render(<Button size="sm">Small</Button>);
    expect(container).toMatchSnapshot();
  });
});
