import { render, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { ConfirmDeleteDialog } from '../ConfirmDeleteDialog';

describe('ConfirmDeleteDialog', () => {
  it('renders the trigger, opens the dialog, and calls onConfirm', async () => {
    const onConfirm = vi.fn();
    const { getByText, queryByText } = render(
      <ConfirmDeleteDialog
        title="Delete thing"
        description="Delete this thing? This cannot be undone."
        onConfirm={onConfirm}
      />,
    );

    expect(getByText('Delete')).toBeTruthy();
    expect(queryByText('Yes, delete')).toBeNull();

    fireEvent.click(getByText('Delete'));
    await waitFor(() => getByText('Yes, delete'));
    expect(getByText('Delete this thing? This cannot be undone.')).toBeTruthy();

    fireEvent.click(getByText('Yes, delete'));
    expect(onConfirm).toHaveBeenCalledTimes(1);
  });
});
