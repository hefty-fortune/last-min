import { render } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { TooltipProvider } from '../Tooltip';

describe('TooltipProvider', () => {
  it('renders with children', () => {
    const { container } = render(
      <TooltipProvider>
        <span>child</span>
      </TooltipProvider>,
    );
    expect(container).toMatchSnapshot();
  });
});
