import { render } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { Label } from '../Label';

describe('Label', () => {
  it('renders label with text', () => {
    const { container } = render(<Label>Username</Label>);
    expect(container).toMatchSnapshot();
  });

  it('renders label with htmlFor', () => {
    const { container } = render(<Label htmlFor="name">Name</Label>);
    expect(container).toMatchSnapshot();
  });
});
