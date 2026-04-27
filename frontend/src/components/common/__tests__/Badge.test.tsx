import { render } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { Badge } from '../Badge';

describe('Badge', () => {
  it('renders default badge', () => {
    const { container } = render(<Badge>admin</Badge>);
    expect(container).toMatchSnapshot();
  });

  it('renders secondary variant', () => {
    const { container } = render(<Badge variant="secondary">tag</Badge>);
    expect(container).toMatchSnapshot();
  });

  it('renders outline variant', () => {
    const { container } = render(<Badge variant="outline">outline</Badge>);
    expect(container).toMatchSnapshot();
  });
});
