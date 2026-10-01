import React from 'react';
export interface TableColumn<T = any> {
  key: string; label: string; width?: string | number;
  sortable?: boolean; align?: 'left'|'center'|'right';
  render?: (value: any, row: T, index: number) => React.ReactNode;
}
export interface TablePagination {
  page: number; pages: number; total: number; info?: string;
  onChange: (page: number) => void;
}
export interface TableProps<T = any> {
  columns: TableColumn<T>[];
  rows: T[];
  selectable?: boolean;
  selectedIds?: (string|number)[];
  onSelectChange?: (ids: (string|number)[]) => void;
  sortKey?: string; sortDir?: 'asc'|'desc';
  onSortChange?: (key: string, dir: 'asc'|'desc') => void;
  density?: 'compact'|'md'|'relaxed';
  emptyTitle?: string; emptyDescription?: string;
  searchValue?: string; onSearchChange?: (v: string) => void; searchPlaceholder?: string;
  toolbar?: React.ReactNode; toolbarRight?: React.ReactNode;
  pagination?: TablePagination;
  viewToggle?: React.ReactNode;
  className?: string;
}
export function Table<T>(props: TableProps<T>): JSX.Element;
