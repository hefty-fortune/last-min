import { render } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '../Card';

describe('Card', () => {
  it('renders full card composition', () => {
    const { container } = render(
      <Card>
        <CardHeader>
          <CardTitle>Title</CardTitle>
          <CardDescription>Description</CardDescription>
        </CardHeader>
        <CardContent>Body content</CardContent>
        <CardFooter>Footer</CardFooter>
      </Card>,
    );
    expect(container).toMatchSnapshot();
  });

  it('renders card with custom className', () => {
    const { container } = render(<Card className="w-full">Content</Card>);
    expect(container).toMatchSnapshot();
  });
});
