import { render } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { Toaster } from '../Toaster';

describe('Toaster', () => {
  it('renders toaster', () => {
    const { container } = render(<Toaster />);
    expect(container).toMatchSnapshot();
  });
});
