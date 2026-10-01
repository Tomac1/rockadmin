import React from 'react';

const CSS = `
/* ===== SECOND NAVBAR ===== */
.ra-secnav {
  height: var(--nav-height-secondary); background: var(--nav-secondary-bg);
  border-bottom: 1px solid var(--nav-secondary-border);
  display: flex; align-items: stretch; gap: 0;
  position: sticky; top: var(--nav-height-primary); z-index: calc(var(--z-fixed) - 1);
  flex-shrink: 0; width: 100%; overflow: visible;
}
.ra-secnav__inner { display: flex; align-items: stretch; padding: 0 var(--space-3); gap: 0; flex: 1; overflow: visible; /* dropdowns must escape */ }
.ra-secnav__right { display: flex; align-items: center; padding: 0 var(--space-3); gap: var(--space-2); flex-shrink: 0; border-left: 1px solid var(--nav-secondary-border); }

/* Nav item */
.ra-snav-item { position: relative; display: flex; align-items: stretch; }
.ra-snav-btn {
  display: flex; align-items: center; gap: var(--space-1-5); padding: 0 var(--space-3); height: 100%;
  font-family: var(--font-sans); font-size: var(--text-sm); font-weight: var(--font-medium);
  color: var(--nav-secondary-text); background: transparent; border: none; cursor: pointer;
  position: relative; white-space: nowrap; transition: color var(--duration-fast) ease, background var(--duration-fast) ease;
  border-bottom: 2px solid transparent; margin-bottom: -1px;
}
.ra-snav-btn:hover { color: var(--nav-secondary-text-hover); background: var(--nav-secondary-hover); }
.ra-snav-btn--active { color: var(--nav-secondary-text-active); border-bottom-color: var(--nav-secondary-active-bar); }
.ra-snav-btn--has-dd { padding-right: var(--space-2); }
.ra-snav-caret { opacity: 0.5; margin-left: 2px; transition: transform var(--duration-fast) ease; }
.ra-snav-btn--dd-open .ra-snav-caret { transform: rotate(180deg); }
.ra-snav-badge {
  min-width: 16px; height: 16px; padding: 0 4px;
  background: var(--color-error-500); color: white;
  border-radius: var(--radius-full); font-size: 9px; font-weight: 700;
  display: inline-flex; align-items: center; justify-content: center; line-height: 1; flex-shrink: 0;
  animation: ra-snav-pulse 2s ease-in-out infinite;
}
@keyframes ra-snav-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.7;transform:scale(0.9)} }

/* Desktop dropdown */
.ra-snav-dd {
  position: absolute; top: calc(100% + 2px); left: 0;
  background: var(--bg-surface); border: 1px solid var(--border-color);
  border-radius: var(--radius-dropdown); box-shadow: var(--shadow-dropdown);
  min-width: 200px; z-index: var(--z-dropdown); overflow: hidden;
  animation: ra-dd-in 120ms var(--ease-out);
}
.ra-snav-dd__item {
  display: flex; align-items: center; justify-content: space-between;
  padding: var(--space-2) var(--space-4); font-size: var(--text-base);
  color: var(--text-primary); cursor: pointer; transition: background var(--duration-fast) ease; gap: var(--space-2);
}
.ra-snav-dd__item:hover { background: var(--bg-hover); }
.ra-snav-dd__item--active { color: var(--color-primary-700); font-weight: var(--font-medium); background: var(--color-primary-50); }
.ra-snav-dd__item-left { display: flex; align-items: center; gap: var(--space-2-5); min-width: 0; }
.ra-snav-dd__item-icon { width: 15px; height: 15px; color: var(--text-muted); flex-shrink: 0; }
.ra-snav-dd__item-badge { min-width: 16px; height: 16px; padding: 0 4px; background: var(--color-error-500); color: white; border-radius: var(--radius-full); font-size: 9px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; line-height: 1; flex-shrink: 0; animation: ra-snav-pulse 2s ease-in-out infinite; }
.ra-snav-dd__section { padding: var(--space-1) 0; }
.ra-snav-dd__section + .ra-snav-dd__section { border-top: 1px solid var(--border-color); }
.ra-snav-dd__label { font-size: 10px; font-weight: var(--font-semibold); color: var(--text-muted); text-transform: uppercase; letter-spacing: var(--tracking-wider); padding: var(--space-2) var(--space-4) var(--space-1); }

/* ===== MOBILE ===== */
.ra-secnav__mobile-bar { display: none; align-items: center; gap: var(--space-2-5); flex: 1; padding: 0 var(--space-4); }
.ra-secnav__mobile-btn {
  display: flex; align-items: center; gap: var(--space-2); height: 30px; padding: 0 10px;
  border-radius: var(--radius-md); border: 1px solid rgba(255,255,255,0.15);
  color: var(--nav-secondary-text-hover); background: rgba(255,255,255,0.07);
  font-family: var(--font-sans); font-size: var(--text-sm); font-weight: var(--font-medium);
  cursor: pointer;
}
.ra-secnav__mobile-btn:hover { background: rgba(255,255,255,0.12); color: white; }

/* Left offcanvas */
.ra-secnav-canvas__overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: calc(var(--z-modal) + 5); animation: ra-fade-in 200ms ease; }
@keyframes ra-fade-in { from { opacity: 0; } to { opacity: 1; } }
.ra-secnav-canvas {
  position: fixed; top: 0; left: 0; height: 100%;
  width: min(90vw, 340px);
  background: var(--color-navy-800);
  z-index: calc(var(--z-modal) + 6);
  display: flex; flex-direction: column;
  box-shadow: var(--shadow-2xl);
  animation: ra-slide-from-left 260ms cubic-bezier(0,0,0.2,1);
}
@keyframes ra-slide-from-left { from { transform: translateX(-100%); } to { transform: translateX(0); } }
.ra-secnav-canvas__header {
  display: flex; align-items: center; justify-content: space-between;
  padding: var(--space-4) var(--space-5);
  border-bottom: 1px solid rgba(255,255,255,0.08);
  flex-shrink: 0;
}
.ra-secnav-canvas__title { font-weight: var(--font-semibold); font-size: var(--text-lg); color: white; }
.ra-secnav-canvas__close {
  width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: rgba(255,255,255,0.5); border-radius: var(--radius-md);
  border: none; background: transparent; flex-shrink: 0;
}
.ra-secnav-canvas__close:hover { background: rgba(255,255,255,0.1); color: white; }
.ra-secnav-canvas__body { overflow-y: auto; flex: 1; padding: var(--space-2) 0; }
/* Canvas items */
.ra-secnav-canvas__item {
  display: flex; align-items: center; gap: var(--space-3);
  padding: 11px var(--space-5); color: rgba(255,255,255,0.65);
  font-size: var(--text-md); cursor: pointer; transition: background 100ms;
  border: none; background: transparent; width: 100%; text-align: left;
  font-family: var(--font-sans); border-left: 3px solid transparent;
}
.ra-secnav-canvas__item:hover { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.9); }
.ra-secnav-canvas__item--active { color: white; background: rgba(37,99,235,0.25); border-left-color: var(--color-primary-500); }
.ra-secnav-canvas__item--sub { padding-left: calc(var(--space-5) + 28px); font-size: var(--text-base); color: rgba(255,255,255,0.5); padding-top: 8px; padding-bottom: 8px; }
.ra-secnav-canvas__item--sub:hover { color: rgba(255,255,255,0.8); }
.ra-secnav-canvas__item--sub.ra-secnav-canvas__item--active { color: white; background: rgba(37,99,235,0.2); }
.ra-secnav-canvas__badge { min-width: 16px; height: 16px; padding: 0 4px; background: var(--color-error-500); color: white; border-radius: var(--radius-full); font-size: 9px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; margin-left: auto; flex-shrink: 0; animation: ra-snav-pulse 2s ease-in-out infinite; }
.ra-secnav-canvas__expand { margin-left: auto; color: rgba(255,255,255,.35); transition: transform 150ms; line-height: 1; }
.ra-secnav-canvas__expand--open { transform: rotate(90deg); }
.ra-secnav-canvas__section-divider { height: 1px; background: rgba(255,255,255,0.07); margin: var(--space-1) 0; }

@media (max-width: 767px) {
  .ra-secnav { display: none; }
}
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-secnav-css')) {
  const s = document.createElement('style'); s.id = 'ra-secnav-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

const ChevronDown = () => <svg className="ra-snav-caret" width="11" height="11" viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><polyline points="2,4 6,8 10,4"/></svg>;
const ChevronRight = () => <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><polyline points="4,2 8,6 4,10"/></svg>;
const CloseIcon = () => <svg width="16" height="16" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" fill="none"><line x1="3" y1="3" x2="13" y2="13"/><line x1="13" y1="3" x2="3" y2="13"/></svg>;

function useDropdown() {
  const [open, setOpen] = React.useState(false);
  const ref = React.useRef(null);
  React.useEffect(() => {
    const h = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', h);
    return () => document.removeEventListener('mousedown', h);
  }, []);
  return { open, setOpen, ref };
}

function NavItemSimple({ item, isActive, onClick }) {
  return (
    <div className="ra-snav-item">
      <button className={`ra-snav-btn ${isActive ? 'ra-snav-btn--active' : ''}`} onClick={() => onClick && onClick(item.id)}>
        {item.icon && <span style={{display:'flex',alignItems:'center'}}>{item.icon}</span>}
        <span>{item.label}</span>
        {item.badge ? <span className="ra-snav-badge">{item.badge > 99 ? '99+' : item.badge}</span> : null}
      </button>
    </div>
  );
}

function NavItemDropdown({ item, isActive, activeSubId, onClick }) {
  const dd = useDropdown();
  const allChildren = item.sections ? item.sections.flatMap(s => s.items) : (item.children || []);
  const totalBadge = allChildren.reduce((s, c) => s + (c.badge || 0), 0) + (item.badge || 0);
  const hasActiveSub = allChildren.some(c => c.id === activeSubId);

  return (
    <div className="ra-snav-item" ref={dd.ref}>
      <button className={`ra-snav-btn ra-snav-btn--has-dd ${isActive || hasActiveSub ? 'ra-snav-btn--active' : ''} ${dd.open ? 'ra-snav-btn--dd-open' : ''}`}
        onClick={() => dd.setOpen(v => !v)}>
        {item.icon && <span style={{display:'flex',alignItems:'center'}}>{item.icon}</span>}
        <span>{item.label}</span>
        {totalBadge > 0 && <span className="ra-snav-badge">{totalBadge > 99 ? '99+' : totalBadge}</span>}
        <ChevronDown />
      </button>
      {dd.open && (
        <div className="ra-snav-dd">
          {(item.sections || [{ items: item.children || [] }]).map((sec, si) => (
            <div key={si} className="ra-snav-dd__section">
              {sec.label && <div className="ra-snav-dd__label">{sec.label}</div>}
              {sec.items.map(sub => (
                <div key={sub.id} className={`ra-snav-dd__item ${activeSubId===sub.id?'ra-snav-dd__item--active':''}`}
                  onClick={() => { onClick && onClick(sub.id); dd.setOpen(false); }}>
                  <div className="ra-snav-dd__item-left">
                    {sub.icon && <span className="ra-snav-dd__item-icon">{sub.icon}</span>}
                    <span>{sub.label}</span>
                  </div>
                  {sub.badge ? <span className="ra-snav-dd__item-badge">{sub.badge > 99 ? '99+' : sub.badge}</span> : null}
                </div>
              ))}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

/* ---- Mobile offcanvas ---- */
function MobileCanvas({ items, activeId, onNavigate, projectLabel, onClose }) {
  const [expanded, setExpanded] = React.useState(new Set());

  const toggle = (id) => setExpanded(prev => {
    const next = new Set(prev);
    next.has(id) ? next.delete(id) : next.add(id);
    return next;
  });

  const handleNav = (id) => { onNavigate && onNavigate(id); onClose(); };

  const allChildren = (item) => item.sections
    ? item.sections.flatMap(s => s.items)
    : (item.children || []);

  return (
    <>
      <div className="ra-secnav-canvas__overlay" onClick={onClose} />
      <div className="ra-secnav-canvas">
        <div className="ra-secnav-canvas__header">
          <span className="ra-secnav-canvas__title">{projectLabel || 'Navigation'}</span>
          <button className="ra-secnav-canvas__close" onClick={onClose}><CloseIcon /></button>
        </div>
        <div className="ra-secnav-canvas__body">
          {items.map((item, idx) => {
            const children = allChildren(item);
            const hasChildren = children.length > 0;
            const isOpen = expanded.has(item.id);
            const totalBadge = children.reduce((s, c) => s + (c.badge || 0), 0) + (item.badge || 0);
            const isItemActive = activeId === item.id || children.some(c => c.id === activeId);

            return (
              <React.Fragment key={item.id}>
                {idx > 0 && !hasChildren && <div className="ra-secnav-canvas__section-divider" />}
                <button
                  className={`ra-secnav-canvas__item ${isItemActive && !hasChildren ? 'ra-secnav-canvas__item--active' : ''}`}
                  onClick={() => hasChildren ? toggle(item.id) : handleNav(item.id)}>
                  {item.icon && <span style={{display:'flex',alignItems:'center',opacity:.65,flexShrink:0}}>{item.icon}</span>}
                  <span style={{flex:1,textAlign:'left'}}>{item.label}</span>
                  {totalBadge > 0 && !isOpen && <span className="ra-secnav-canvas__badge">{totalBadge > 99 ? '99+' : totalBadge}</span>}
                  {hasChildren && (
                    <span className={`ra-secnav-canvas__expand ${isOpen ? 'ra-secnav-canvas__expand--open' : ''}`}>
                      <ChevronRight />
                    </span>
                  )}
                </button>
                {hasChildren && isOpen && children.map(sub => (
                  <button key={sub.id}
                    className={`ra-secnav-canvas__item ra-secnav-canvas__item--sub ${activeId===sub.id?'ra-secnav-canvas__item--active':''}`}
                    onClick={() => handleNav(sub.id)}>
                    {sub.icon && <span style={{display:'flex',alignItems:'center',opacity:.5,flexShrink:0}}>{sub.icon}</span>}
                    <span style={{flex:1,textAlign:'left'}}>{sub.label}</span>
                    {sub.badge ? <span className="ra-secnav-canvas__badge">{sub.badge > 99 ? '99+' : sub.badge}</span> : null}
                  </button>
                ))}
              </React.Fragment>
            );
          })}
        </div>
      </div>
    </>
  );
}

export function SecondNavbar({ items = [], activeId, onNavigate, rightContent, projectLabel, mobileOpen = false, onMobileClose, className = '' }) {

  // Find label for current active item for mobile bar
  const findLabel = (id) => {
    for (const item of items) {
      if (item.id === id) return item.label;
      const children = item.sections ? item.sections.flatMap(s => s.items) : (item.children || []);
      const sub = children.find(c => c.id === id);
      if (sub) return sub.label;
    }
    return 'Menu';
  };

  return (
    <>
      <nav className={`ra-secnav ${className}`}>
        {/* Desktop nav items */}
        <div className="ra-secnav__inner">
          {items.map(item => {
            const hasDD = (item.children && item.children.length > 0) || (item.sections && item.sections.length > 0);
            return hasDD
              ? <NavItemDropdown key={item.id} item={item} isActive={activeId===item.id} activeSubId={activeId} onClick={onNavigate} />
              : <NavItemSimple key={item.id} item={item} isActive={activeId===item.id} onClick={onNavigate} />;
          })}
        </div>

        {/* Desktop right slot */}
        {rightContent && <div className="ra-secnav__right">{rightContent}</div>}
      </nav>

      {/* Mobile offcanvas — controlled externally via mobileOpen/onMobileClose */}
      {mobileOpen && (
        <MobileCanvas
          items={items}
          activeId={activeId}
          onNavigate={onNavigate}
          projectLabel={projectLabel || 'Navigation'}
          onClose={onMobileClose}
        />
      )}
    </>
  );
}
