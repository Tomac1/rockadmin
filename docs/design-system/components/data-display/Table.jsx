import React from 'react';

const CSS = `
.ra-table-wrap { width: 100%; overflow-x: auto; }
.ra-table { width: 100%; border-collapse: collapse; font-size: var(--text-md); }
/* Head */
.ra-table thead th {
  background: var(--color-neutral-50); color: var(--text-muted);
  font-weight: var(--font-semibold); font-size: var(--text-xs);
  text-transform: uppercase; letter-spacing: var(--tracking-wider);
  padding: var(--table-header-py) var(--table-cell-px);
  white-space: nowrap; text-align: left; user-select: none;
  border-bottom: 1px solid var(--border-color);
  border-top: 1px solid var(--border-color);
}
.ra-table thead th:first-child { border-radius: var(--radius-sm) 0 0 0; }
.ra-table thead th:last-child  { border-radius: 0 var(--radius-sm) 0 0; }
.ra-table thead th.ra-th--sortable { cursor: pointer; }
.ra-table thead th.ra-th--sortable:hover { color: var(--text-primary); background: var(--color-neutral-100); }
.ra-th__inner { display: flex; align-items: center; gap: var(--space-1-5); }
.ra-th__sort { display: flex; flex-direction: column; gap: 1px; opacity: 0.4; }
.ra-th__sort--asc .ra-th__sort-up, .ra-th__sort--desc .ra-th__sort-down { opacity: 1; color: var(--color-primary-600); }
/* Body */
.ra-table tbody tr { border-bottom: 1px solid var(--border-color); transition: background var(--duration-fast) ease; }
.ra-table tbody tr:last-child { border-bottom: none; }
.ra-table tbody tr:hover { background: var(--color-neutral-50); }
.ra-table tbody tr.ra-tr--selected { background: var(--color-primary-50); }
.ra-table tbody tr.ra-tr--selected:hover { background: var(--color-primary-100); }
.ra-table td { padding: var(--table-cell-py) var(--table-cell-px); color: var(--text-primary); vertical-align: middle; }
/* Checkbox col */
.ra-table .ra-col-check { width: 40px; padding-right: 0; }
/* Densities */
.ra-table--compact tbody td, .ra-table--compact thead th { padding-top: 5px; padding-bottom: 5px; }
.ra-table--relaxed tbody td { padding-top: 14px; padding-bottom: 14px; }
/* Empty state */
.ra-table-empty { padding: var(--space-16) var(--space-8); text-align: center; }
.ra-table-empty__icon { font-size: 40px; margin-bottom: var(--space-3); opacity: 0.25; }
.ra-table-empty__title { font-weight: var(--font-semibold); color: var(--text-primary); margin-bottom: var(--space-1); }
.ra-table-empty__desc { font-size: var(--text-sm); color: var(--text-muted); }
/* Pagination */
.ra-table-footer { display: flex; align-items: center; justify-content: space-between; padding: var(--space-3) var(--table-cell-px); border-top: 1px solid var(--border-color); background: var(--color-neutral-50); gap: var(--space-4); flex-wrap: wrap; }
.ra-table-footer__info { font-size: var(--text-sm); color: var(--text-muted); }
.ra-table-footer__pages { display: flex; align-items: center; gap: var(--space-1); }
.ra-page-btn { min-width: 30px; height: 28px; padding: 0 6px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-surface); font-size: var(--text-sm); cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); transition: var(--transition-colors); }
.ra-page-btn:hover:not(:disabled) { background: var(--color-neutral-100); border-color: var(--color-neutral-300); }
.ra-page-btn--active { background: var(--color-primary-600); border-color: var(--color-primary-600); color: white; }
.ra-page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
/* Toolbar */
.ra-table-toolbar { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-3) var(--table-cell-px); border-bottom: 1px solid var(--border-color); flex-wrap: wrap; }
.ra-table-toolbar__left  { display: flex; align-items: center; gap: var(--space-2); flex: 1; min-width: 0; }
.ra-table-toolbar__right { display: flex; align-items: center; gap: var(--space-2); flex-shrink: 0; }
.ra-table-search { position: relative; flex-shrink: 0; }
.ra-table-search input { height: 34px; padding: 0 var(--space-3) 0 32px; border: 1px solid var(--border-color-strong); border-radius: var(--radius-md); font-size: var(--text-md); font-family: var(--font-sans); outline: none; width: 220px; background: var(--bg-surface); color: var(--text-primary); transition: var(--transition-colors); }
.ra-table-search input:focus { border-color: var(--border-focus); box-shadow: var(--shadow-focus); }
.ra-table-search__icon { position: absolute; left: 9px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
/* View toggle */
.ra-view-toggle { display: flex; border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; }
.ra-view-btn { height: 34px; width: 34px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-muted); background: var(--bg-surface); transition: var(--transition-colors); border: none; }
.ra-view-btn:hover { background: var(--color-neutral-100); color: var(--text-primary); }
.ra-view-btn--active { background: var(--color-primary-600); color: white; }
/* Row action cell */
.ra-td-actions { display: flex; gap: var(--space-1); align-items: center; opacity: 0; transition: opacity var(--duration-fast) ease; }
.ra-table tbody tr:hover .ra-td-actions { opacity: 1; }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-table-css')) {
  const s = document.createElement('style'); s.id = 'ra-table-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

const SortIcon = ({ dir }) => (
  <span className={`ra-th__sort ${dir ? `ra-th__sort--${dir}` : ''}`}>
    <svg className="ra-th__sort-up" width="8" height="5" viewBox="0 0 8 5" fill="currentColor"><path d="M4 0L8 5H0L4 0z"/></svg>
    <svg className="ra-th__sort-down" width="8" height="5" viewBox="0 0 8 5" fill="currentColor"><path d="M4 5L0 0h8L4 5z"/></svg>
  </span>
);

const SearchIcon = () => (
  <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
    <circle cx="7" cy="7" r="5"/><line x1="11" y1="11" x2="15" y2="15"/>
  </svg>
);

export function Table({
  columns = [],
  rows = [],
  selectable = false,
  selectedIds = [],
  onSelectChange,
  sortKey,
  sortDir,
  onSortChange,
  density = 'md',
  emptyTitle = 'No records',
  emptyDescription = 'No data to display.',
  searchValue,
  onSearchChange,
  searchPlaceholder = 'Search...',
  toolbar,
  toolbarRight,
  pagination,
  viewToggle,
  className = '',
}) {
  const allSelected = rows.length > 0 && rows.every(r => selectedIds.includes(r.id ?? r._id));
  const someSelected = rows.some(r => selectedIds.includes(r.id ?? r._id));

  const toggleAll = () => {
    if (!onSelectChange) return;
    if (allSelected) onSelectChange([]);
    else onSelectChange(rows.map(r => r.id ?? r._id));
  };

  const toggleRow = (id) => {
    if (!onSelectChange) return;
    onSelectChange(selectedIds.includes(id) ? selectedIds.filter(x => x !== id) : [...selectedIds, id]);
  };

  const handleSort = (col) => {
    if (!col.sortable || !onSortChange) return;
    if (sortKey === col.key) onSortChange(col.key, sortDir === 'asc' ? 'desc' : 'asc');
    else onSortChange(col.key, 'asc');
  };

  const showToolbar = toolbar || toolbarRight || onSearchChange || viewToggle;

  return (
    <div className={['ra-card', className].join(' ')} style={{ overflow: 'hidden' }}>
      {showToolbar && (
        <div className="ra-table-toolbar">
          <div className="ra-table-toolbar__left">
            {onSearchChange && (
              <div className="ra-table-search">
                <span className="ra-table-search__icon"><SearchIcon /></span>
                <input placeholder={searchPlaceholder} value={searchValue || ''} onChange={e => onSearchChange(e.target.value)} />
              </div>
            )}
            {toolbar}
          </div>
          <div className="ra-table-toolbar__right">
            {toolbarRight}
            {viewToggle && <div className="ra-view-toggle">{viewToggle}</div>}
          </div>
        </div>
      )}
      <div className="ra-table-wrap">
        <table className={['ra-table', density !== 'md' ? `ra-table--${density}` : ''].filter(Boolean).join(' ')}>
          <thead>
            <tr>
              {selectable && (
                <th className="ra-col-check">
                  <input type="checkbox" checked={allSelected} ref={el => { if (el) el.indeterminate = someSelected && !allSelected; }}
                    onChange={toggleAll} style={{ cursor: 'pointer' }} />
                </th>
              )}
              {columns.map(col => (
                <th key={col.key} className={col.sortable ? 'ra-th--sortable' : ''} style={{ width: col.width }} onClick={() => handleSort(col)}>
                  <div className="ra-th__inner">
                    <span>{col.label}</span>
                    {col.sortable && <SortIcon dir={sortKey === col.key ? sortDir : null} />}
                  </div>
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr><td colSpan={columns.length + (selectable ? 1 : 0)}>
                <div className="ra-table-empty">
                  <div className="ra-table-empty__icon">
                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none" stroke="currentColor" strokeWidth="1.2"><rect x="6" y="6" width="28" height="28" rx="4"/><line x1="13" y1="14" x2="27" y2="14"/><line x1="13" y1="20" x2="22" y2="20"/><line x1="13" y1="26" x2="19" y2="26"/></svg>
                  </div>
                  <div className="ra-table-empty__title">{emptyTitle}</div>
                  <div className="ra-table-empty__desc">{emptyDescription}</div>
                </div>
              </td></tr>
            ) : rows.map((row, ri) => {
              const id = row.id ?? row._id ?? ri;
              const isSelected = selectedIds.includes(id);
              return (
                <tr key={id} className={isSelected ? 'ra-tr--selected' : ''}>
                  {selectable && (
                    <td className="ra-col-check">
                      <input type="checkbox" checked={isSelected} onChange={() => toggleRow(id)} style={{ cursor: 'pointer' }} />
                    </td>
                  )}
                  {columns.map(col => (
                    <td key={col.key} style={{ textAlign: col.align || 'left', width: col.width }}>
                      {col.render ? col.render(row[col.key], row, ri) : row[col.key]}
                    </td>
                  ))}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      {pagination && (
        <div className="ra-table-footer">
          <span className="ra-table-footer__info">{pagination.info}</span>
          <div className="ra-table-footer__pages">
            <button className="ra-page-btn" disabled={pagination.page <= 1} onClick={() => pagination.onChange(pagination.page - 1)}>
              <svg width="12" height="12" viewBox="0 0 12 12" stroke="currentColor" strokeWidth="2" strokeLinecap="round" fill="none"><polyline points="8,2 4,6 8,10"/></svg>
            </button>
            {Array.from({ length: Math.min(pagination.pages, 7) }, (_, i) => {
              const p = pagination.pages <= 7 ? i + 1 : i + Math.max(1, pagination.page - 3);
              if (p > pagination.pages) return null;
              return <button key={p} className={`ra-page-btn ${pagination.page === p ? 'ra-page-btn--active' : ''}`} onClick={() => pagination.onChange(p)}>{p}</button>;
            })}
            <button className="ra-page-btn" disabled={pagination.page >= pagination.pages} onClick={() => pagination.onChange(pagination.page + 1)}>
              <svg width="12" height="12" viewBox="0 0 12 12" stroke="currentColor" strokeWidth="2" strokeLinecap="round" fill="none"><polyline points="4,2 8,6 4,10"/></svg>
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
