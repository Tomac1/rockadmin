import React from 'react';

const CSS = `
.ra-tree { width: 100%; border-collapse: collapse; font-size: var(--text-md); }
.ra-tree thead th { background: var(--color-neutral-50); color: var(--text-muted); font-weight: var(--font-semibold); font-size: var(--text-xs); text-transform: uppercase; letter-spacing: var(--tracking-wider); padding: var(--table-header-py) var(--table-cell-px); border-bottom: 1px solid var(--border-color); border-top: 1px solid var(--border-color); text-align: left; white-space: nowrap; }
.ra-tree tbody tr { border-bottom: 1px solid var(--border-color); transition: background var(--duration-fast) ease; }
.ra-tree tbody tr:hover { background: var(--color-neutral-50); }
.ra-tree td { padding: var(--table-cell-py) var(--table-cell-px); vertical-align: middle; }
.ra-tree-cell { display: flex; align-items: center; gap: var(--space-1-5); }
.ra-tree-indent { display: flex; align-items: center; flex-shrink: 0; }
.ra-tree-indent__line { width: 20px; height: 100%; position: relative; }
.ra-tree-toggle { width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer; border-radius: var(--radius-sm); flex-shrink: 0; color: var(--text-muted); transition: var(--transition-colors); border: none; background: transparent; padding: 0; }
.ra-tree-toggle:hover { background: var(--color-neutral-200); color: var(--text-primary); }
.ra-tree-toggle--leaf { cursor: default; opacity: 0; pointer-events: none; }
.ra-tree-icon { color: var(--text-muted); flex-shrink: 0; }
.ra-tree-icon--folder { color: var(--color-warning-500); }
.ra-tree-icon--file { color: var(--color-neutral-400); }
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-tree-css')) {
  const s = document.createElement('style'); s.id = 'ra-tree-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

const FolderIcon = ({ open }) => open
  ? <svg width="15" height="15" viewBox="0 0 16 16" fill="var(--color-warning-400)" stroke="var(--color-warning-600)" strokeWidth="1"><path d="M1 4a1 1 0 011-1h4l1.5 1.5H14a1 1 0 011 1V12a1 1 0 01-1 1H2a1 1 0 01-1-1V4z"/></svg>
  : <svg width="15" height="15" viewBox="0 0 16 16" fill="var(--color-warning-300)" stroke="var(--color-warning-500)" strokeWidth="1"><path d="M1 4a1 1 0 011-1h4l1.5 1.5H14a1 1 0 011 1V12a1 1 0 01-1 1H2a1 1 0 01-1-1V4z"/></svg>;

const FileIcon = () => <svg width="13" height="14" viewBox="0 0 13 14" fill="var(--color-neutral-200)" stroke="var(--color-neutral-400)" strokeWidth="1"><path d="M2 1h7l3 3v9a1 1 0 01-1 1H2a1 1 0 01-1-1V2a1 1 0 011-1z"/><polyline points="9,1 9,4 12,4" fill="none"/></svg>;

const ChevronRight = () => <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><polyline points="4,2 8,6 4,10"/></svg>;
const ChevronDown = () => <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><polyline points="2,4 6,8 10,4"/></svg>;

function TreeRows({ nodes, columns, depth, expandedIds, onToggle }) {
  return nodes.map(node => {
    const hasChildren = node.children && node.children.length > 0;
    const isExpanded = expandedIds.has(node.id);
    return (
      <React.Fragment key={node.id}>
        <tr>
          <td>
            <div className="ra-tree-cell" style={{ paddingLeft: depth * 20 }}>
              <button className={`ra-tree-toggle ${!hasChildren ? 'ra-tree-toggle--leaf' : ''}`} onClick={() => hasChildren && onToggle(node.id)}>
                {hasChildren ? (isExpanded ? <ChevronDown /> : <ChevronRight />) : <ChevronRight />}
              </button>
              <span className="ra-tree-icon">{hasChildren ? <FolderIcon open={isExpanded} /> : <FileIcon />}</span>
              <span style={{ fontWeight: hasChildren ? 'var(--font-medium)' : 'var(--font-normal)' }}>{node.label}</span>
            </div>
          </td>
          {columns.slice(1).map(col => (
            <td key={col.key}>{col.render ? col.render(node[col.key], node) : node[col.key]}</td>
          ))}
        </tr>
        {hasChildren && isExpanded && (
          <TreeRows nodes={node.children} columns={columns} depth={depth + 1} expandedIds={expandedIds} onToggle={onToggle} />
        )}
      </React.Fragment>
    );
  });
}

export function TreeView({ nodes = [], columns = [], defaultExpanded = [], className = '' }) {
  const [expandedIds, setExpandedIds] = React.useState(new Set(defaultExpanded));
  const toggle = (id) => setExpandedIds(prev => {
    const next = new Set(prev);
    next.has(id) ? next.delete(id) : next.add(id);
    return next;
  });
  return (
    <div className={['ra-card', className].join(' ')} style={{ overflow: 'hidden' }}>
      <div style={{ overflowX: 'auto' }}>
        <table className="ra-tree">
          <thead>
            <tr>{columns.map(col => <th key={col.key} style={{ width: col.width }}>{col.label}</th>)}</tr>
          </thead>
          <tbody>
            <TreeRows nodes={nodes} columns={columns} depth={0} expandedIds={expandedIds} onToggle={toggle} />
          </tbody>
        </table>
      </div>
    </div>
  );
}
