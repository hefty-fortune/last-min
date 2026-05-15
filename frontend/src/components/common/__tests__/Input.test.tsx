import { render } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { Input } from '../Input';

describe('Input', () => {
  it('renders default input', () => {
    const { container } = render(<Input placeholder="Enter text" />);
    expect(container).toMatchSnapshot();
  });

  it('renders email input', () => {
    const { container } = render(<Input type="email" placeholder="email" />);
    expect(container).toMatchSnapshot();
  });

  it('renders password input', () => {
    const { container } = render(<Input type="password" placeholder="password" />);
    expect(container).toMatchSnapshot();
  });
});
