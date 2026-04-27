import {
  Table as ShadcnTable,
  TableHeader as ShadcnTableHeader,
  TableBody as ShadcnTableBody,
  TableFooter as ShadcnTableFooter,
  TableHead as ShadcnTableHead,
  TableRow as ShadcnTableRow,
  TableCell as ShadcnTableCell,
  TableCaption as ShadcnTableCaption,
} from '@/components/ui/table';

type TableProps = React.ComponentProps<typeof ShadcnTable>;
type TableHeaderProps = React.ComponentProps<typeof ShadcnTableHeader>;
type TableBodyProps = React.ComponentProps<typeof ShadcnTableBody>;
type TableFooterProps = React.ComponentProps<typeof ShadcnTableFooter>;
type TableHeadProps = React.ComponentProps<typeof ShadcnTableHead>;
type TableRowProps = React.ComponentProps<typeof ShadcnTableRow>;
type TableCellProps = React.ComponentProps<typeof ShadcnTableCell>;
type TableCaptionProps = React.ComponentProps<typeof ShadcnTableCaption>;

function Table(props: TableProps) {
  return <ShadcnTable {...props} />;
}
function TableHeader(props: TableHeaderProps) {
  return <ShadcnTableHeader {...props} />;
}
function TableBody(props: TableBodyProps) {
  return <ShadcnTableBody {...props} />;
}
function TableFooter(props: TableFooterProps) {
  return <ShadcnTableFooter {...props} />;
}
function TableHead(props: TableHeadProps) {
  return <ShadcnTableHead {...props} />;
}
function TableRow(props: TableRowProps) {
  return <ShadcnTableRow {...props} />;
}
function TableCell(props: TableCellProps) {
  return <ShadcnTableCell {...props} />;
}
function TableCaption(props: TableCaptionProps) {
  return <ShadcnTableCaption {...props} />;
}

export { Table, TableHeader, TableBody, TableFooter, TableHead, TableRow, TableCell, TableCaption };
