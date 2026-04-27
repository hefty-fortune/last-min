import { render } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '../Dialog';

describe('Dialog', () => {
  it('renders open dialog', () => {
    const { container } = render(
      <Dialog open>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Test Dialog</DialogTitle>
          </DialogHeader>
          <p>Dialog body</p>
        </DialogContent>
      </Dialog>,
    );
    expect(container).toMatchSnapshot();
  });

  it('renders closed dialog', () => {
    const { container } = render(
      <Dialog open={false}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Hidden</DialogTitle>
          </DialogHeader>
        </DialogContent>
      </Dialog>,
    );
    expect(container).toMatchSnapshot();
  });
});
