import { render } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '../Table';

describe('Table', () => {
  it('renders full table structure', () => {
    const { container } = render(
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Email</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow>
            <TableCell>Alice</TableCell>
            <TableCell>alice@test.com</TableCell>
          </TableRow>
        </TableBody>
      </Table>,
    );
    expect(container).toMatchSnapshot();
  });
});
