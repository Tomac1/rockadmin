import React from 'react';

const CSS = `
/* ===== TOP NAVBAR ===== */
.ra-topnav {
  height: var(--nav-height-primary); background: var(--nav-primary-bg);
  border-bottom: 1px solid var(--nav-primary-border);
  box-shadow: var(--shadow-navbar);
  display: flex; align-items: center;
  position: sticky; top: 0; z-index: var(--z-fixed);
  flex-shrink: 0; width: 100%;
}
/* Brand */
.ra-topnav__brand {
  display: flex; align-items: center; gap: var(--space-2);
  padding: 0 var(--space-4); height: 100%;
  text-decoration: none; flex-shrink: 0; user-select: none;
}
.ra-topnav__brand-mark {
  width: 26px; height: 26px; background: var(--color-primary-600);
  border-radius: 5px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ra-topnav__brand-name { font-weight: var(--font-bold); font-size: var(--text-lg); color: white; letter-spacing: var(--tracking-tight); line-height: 1; }
/* Project icon button — sits right after brand name */
.ra-topnav__proj-icon-btn {
  display: flex; align-items: center; gap: 6px;
  height: 34px; padding: 0 10px; border-radius: var(--radius-md);
  border: none; background: transparent; cursor: pointer;
  color: var(--nav-primary-text); transition: background var(--duration-fast) ease;
  margin-left: 6px; flex-shrink: 0; font-family: var(--font-sans);
  font-size: var(--text-sm); font-weight: var(--font-medium);
}
.ra-topnav__proj-icon-btn:hover { background: rgba(255,255,255,0.1); color: var(--nav-primary-text-hover); }
.ra-topnav__proj-icon-btn .ra-proj-caret { opacity: 0.45; }
/* Project name label (desktop only, hidden on mobile) */
.ra-topnav__proj-label { font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--nav-primary-text-hover); white-space: nowrap; }

/* Spacer */
.ra-topnav__spacer { flex: 1; }

/* Generic icon button */
.ra-topnav__iconbtn {
  width: 34px; height: 34px; border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: var(--nav-primary-text); background: transparent;
  border: none; position: relative; transition: var(--transition-colors); flex-shrink: 0;
}
.ra-topnav__iconbtn:hover { background: var(--nav-primary-hover); color: var(--nav-primary-text-hover); }

/* Notification badge */
.ra-topnav__badge {
  position: absolute; top: 4px; right: 4px;
  min-width: 15px; height: 15px; padding: 0 3px;
  background: var(--color-error-500); color: white;
  border-radius: var(--radius-full); font-size: 9px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  border: 1.5px solid var(--nav-primary-bg); line-height: 1;
}

/* Right cluster */
.ra-topnav__right {
  display: flex; align-items: center; gap: var(--space-1);
  padding: 0 var(--space-3); height: 100%;
  border-left: 1px solid var(--nav-primary-border); flex-shrink: 0;
}

/* Trigger button (text + icon, e.g. user menu) */
.ra-topnav__trigger {
  display: flex; align-items: center; gap: var(--space-1-5); padding: 0 var(--space-2);
  height: 34px; border-radius: var(--radius-md); cursor: pointer;
  color: var(--nav-primary-text); font-size: var(--text-sm); font-weight: var(--font-medium);
  transition: var(--transition-colors); border: none; background: transparent;
  font-family: var(--font-sans); flex-shrink: 0;
}
.ra-topnav__trigger:hover { background: var(--nav-primary-hover); color: var(--nav-primary-text-hover); }
.ra-topnav__trigger-caret { opacity: 0.45; }



/* Dropdown panel */
.ra-topnav-dd {
  position: absolute; top: calc(100% + 6px); right: 0;
  background: var(--bg-surface); border: 1px solid var(--border-color);
  border-radius: var(--radius-dropdown); box-shadow: var(--shadow-dropdown);
  min-width: 220px; z-index: var(--z-dropdown); overflow: hidden;
  animation: ra-dd-in 120ms var(--ease-out);
}
@keyframes ra-dd-in { from{opacity:0;transform:translateY(-4px)} to{opacity:1;transform:none} }
.ra-topnav-dd--notifications { min-width: 320px; max-width: 380px; }
/* Project dropdown opens to the RIGHT (brand is on the left) */
.ra-topnav-dd--projects { min-width: 180px; right: auto; left: 0; }
.ra-topnav-dd__header { padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; }
.ra-topnav-dd__header-title { font-weight: var(--font-semibold); font-size: var(--text-md); color: var(--text-primary); }
.ra-topnav-dd__mark-read { font-size: var(--text-xs); color: var(--text-link); cursor: pointer; }
.ra-topnav-dd__section { padding: var(--space-1) 0; }
.ra-topnav-dd__section + .ra-topnav-dd__section { border-top: 1px solid var(--border-color); }
.ra-topnav-dd__label { font-size: 10px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; padding: var(--space-2) var(--space-4) var(--space-1); }
.ra-topnav-dd__item { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) var(--space-4); cursor: pointer; color: var(--text-primary); font-size: var(--text-base); transition: background var(--duration-fast) ease; }
.ra-topnav-dd__item:hover { background: var(--bg-hover); }
.ra-topnav-dd__item--active { color: var(--color-primary-700); font-weight: var(--font-medium); background: var(--color-primary-50); }
.ra-topnav-dd__item--danger { color: var(--color-error-600); }
.ra-topnav-dd__item--danger:hover { background: var(--color-error-50); }
.ra-topnav-dd__item-icon { width: 16px; height: 16px; flex-shrink: 0; color: var(--text-muted); display: flex; align-items: center; }
.ra-topnav-dd__item-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.ra-topnav-dd__user { padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: var(--space-3); }
.ra-topnav-dd__user-name { font-weight: var(--font-semibold); font-size: var(--text-md); color: var(--text-primary); }
.ra-topnav-dd__user-email { font-size: var(--text-xs); color: var(--text-muted); }
/* Notifications */
.ra-notif-list { max-height: 320px; overflow-y: auto; }
.ra-notif-item { display: flex; gap: var(--space-3); padding: var(--space-3) var(--space-4); cursor: pointer; transition: background var(--duration-fast) ease; border-left: 3px solid transparent; }
.ra-notif-item:hover { background: var(--bg-hover); }
.ra-notif-item--unread { border-left-color: var(--color-primary-500); background: var(--color-primary-50); }
.ra-notif-item--unread:hover { background: var(--color-primary-100); }
.ra-notif-item__dot { width: 8px; height: 8px; border-radius: 50%; background: var(--color-primary-500); flex-shrink: 0; margin-top: 5px; }
.ra-notif-item__title { font-size: var(--text-base); color: var(--text-primary); font-weight: var(--font-medium); line-height: var(--leading-snug); }
.ra-notif-item__desc { font-size: var(--text-xs); color: var(--text-secondary); margin-top: 1px; }
.ra-notif-item__time { font-size: var(--text-xs); color: var(--text-muted); margin-top: 2px; }

/* Mobile hamburger — hidden on desktop */
.ra-topnav__hamburger { display: none; }

/* Mobile brand short name */
.ra-topnav__brand-name--short { display: none; }

@media (max-width: 767px) {
  .ra-topnav__hamburger { display: flex; }
  .ra-topnav__brand-name--full { display: none; }
  .ra-topnav__brand-name--short { display: block; }
  .ra-topnav__proj-label { display: none; }
  .ra-topnav__settings-label { display: none; }
  .ra-topnav__user-label { display: none; }
  .ra-topnav__trigger-caret { display: none; }
  .ra-topnav__right { border-left: none; padding: 0 var(--space-2); }
}
@media (min-width: 768px) {
  .ra-topnav__brand-name--short { display: none; }
}
`;

if (typeof document !== 'undefined' && !document.getElementById('ra-topnav-css')) {
  const s = document.createElement('style'); s.id = 'ra-topnav-css'; s.textContent = CSS;
  document.head.appendChild(s);
}

/* ── Icons ──────────────────────────────── */
// Brand mark — stylized R (v2)
const BrandMark = () => (
  <svg width="15" height="15" viewBox="0 0 14 14" fill="none" stroke="white" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M3 12V2h5a3 3 0 010 6H3"/>
    <path d="M8 8l3 4"/>
  </svg>
);

// Cog icon — 8-tooth gear
const CogIcon = () => (
  <svg width="16" height="16" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
    <circle cx="10" cy="10" r="3"/>
    <path d="M10 1v2.5M10 16.5V19M1 10h2.5M16.5 10H19M3.22 3.22l1.77 1.77M15.01 15.01l1.77 1.77M3.22 16.78l1.77-1.77M15.01 4.99l1.77-1.77"/>
  </svg>
);

// Project icon — colored 2x2 grid, color = active project color
const ProjectIcon = ({ color = 'rgba(255,255,255,0.45)', size = 14 }) => (
  <svg width={size} height={size} viewBox="0 0 14 14" fill={color}>
    <rect x="1" y="1" width="5" height="5" rx="1.2"/>
    <rect x="8" y="1" width="5" height="5" rx="1.2"/>
    <rect x="1" y="8" width="5" height="5" rx="1.2"/>
    <rect x="8" y="8" width="5" height="5" rx="1.2"/>
  </svg>
);

const ProjCaret = () => <svg className="ra-proj-caret" width="9" height="9" viewBox="0 0 10 10" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><polyline points="2,3 5,7 8,3"/></svg>;

const BellIcon = () => <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"><path d="M10 2a6 6 0 00-6 6v3l-1.5 2.5h15L16 11V8a6 6 0 00-6-6z"/><path d="M8.5 17a1.5 1.5 0 003 0"/></svg>;
const ChevronDown = () => <svg width="11" height="11" viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><polyline points="2,4 6,8 10,4"/></svg>;
const HamburgerIcon = () => <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><line x1="2" y1="5" x2="18" y2="5"/><line x1="2" y1="10" x2="18" y2="10"/><line x1="2" y1="15" x2="18" y2="15"/></svg>;
const LogOutIcon = () => <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"><path d="M6 14H3a1 1 0 01-1-1V3a1 1 0 011-1h3"/><polyline points="11,11 14,8 11,5"/><line x1="14" y1="8" x2="6" y2="8"/></svg>;
const UserIcon = () => <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"><circle cx="8" cy="5" r="3"/><path d="M2 14c0-3.31 2.69-6 6-6s6 2.69 6 6"/></svg>;
const GridIcon = () => <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"><rect x="1" y="1" width="6" height="6" rx="1"/><rect x="9" y="1" width="6" height="6" rx="1"/><rect x="1" y="9" width="6" height="6" rx="1"/><rect x="9" y="9" width="6" height="6" rx="1"/></svg>;
const ShieldIcon = () => <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round"><path d="M8 2L3 4v4c0 3 2.5 5.5 5 6 2.5-.5 5-3 5-6V4L8 2z"/></svg>;

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

function AvatarInitials({ name, size = 24 }) {
  const colors = ['#2563eb','#7c3aed','#059669','#d97706'];
  let h = 0; for (let i = 0; i < (name||'').length; i++) h = (h * 31 + name.charCodeAt(i)) & 0xffffffff;
  const bg = colors[Math.abs(h) % colors.length];
  const parts = (name||'').trim().split(/\s+/);
  const init = parts.length === 1 ? parts[0][0] : (parts[0][0] + parts[parts.length-1][0]);
  return (
    <div style={{ width:size, height:size, borderRadius:'50%', background:bg, display:'flex', alignItems:'center', justifyContent:'center', fontSize:size*0.42, fontWeight:600, color:'white', flexShrink:0, fontFamily:'var(--font-sans)' }}>
      {init.toUpperCase()}
    </div>
  );
}

export function TopNavbar({
  brandName = 'Rock Admin',
  brandShortName,
  projects = [],
  activeProject,
  onProjectChange,
  notifications = [],
  onMarkAllRead,
  user = {},
  onSettingsClick,
  onProfileClick,
  onLogout,
  settingsItems = [],
  /** Called when hamburger is pressed on mobile — use to open SecondNavbar offcanvas */
  onMobileMenuOpen,
  className = '',
}) {
  const notifDd   = useDropdown();
  const projDd    = useDropdown();
  const settingsDd = useDropdown();
  const userDd    = useDropdown();

  const unreadCount = notifications.filter(n => n.unread).length;
  const activeProj  = projects.find(p => p.id === activeProject);
  const shortBrand  = brandShortName || (brandName||'').split(/\s+/).map(w => w[0]).join('').toUpperCase();

  const defaultSettings = settingsItems.length ? settingsItems : [
    { icon: <GridIcon />,  label: 'All Projects',   onClick: onSettingsClick },
    { icon: <UserIcon />,  label: 'User Management', onClick: onSettingsClick },
    { icon: <ShieldIcon />,label: 'Permissions',     onClick: onSettingsClick },
  ];

  return (
    <header className={`ra-topnav ${className}`}>
      {/* Mobile hamburger — triggers SecondNavbar offcanvas */}
      <button className="ra-topnav__iconbtn ra-topnav__hamburger" onClick={onMobileMenuOpen} title="Menu"
        style={{marginLeft:'var(--space-2)'}}>
        <HamburgerIcon />
      </button>

      {/* Brand + inline project switcher */}
      <div className="ra-topnav__brand">
        <div className="ra-topnav__brand-mark"><BrandMark /></div>
        <span className="ra-topnav__brand-name ra-topnav__brand-name--full">{brandName}</span>
        <span className="ra-topnav__brand-name ra-topnav__brand-name--short">{shortBrand}</span>
        {projects.length > 0 && (
          <div style={{position:'relative'}} ref={projDd.ref}>
            <button className="ra-topnav__proj-icon-btn" onClick={() => projDd.setOpen(v=>!v)} title={activeProj?.name||'Switch project'}>
              <ProjectIcon color={activeProj?.color||'rgba(255,255,255,0.35)'} />
              <span className="ra-topnav__proj-label">{activeProj?.name}</span>
              <ProjCaret />
            </button>
            {projDd.open && (
              <div className="ra-topnav-dd ra-topnav-dd--projects">
                <div className="ra-topnav-dd__section">
                  <div className="ra-topnav-dd__label">Switch Project</div>
                  {projects.map(p => (
                    <div key={p.id}
                      className={`ra-topnav-dd__item ${activeProject===p.id?'ra-topnav-dd__item--active':''}`}
                      onClick={() => { onProjectChange&&onProjectChange(p.id); projDd.setOpen(false); }}>
                      <ProjectIcon color={p.color||'var(--text-muted)'} size={13} />
                      <span>{p.name}</span>
                      {activeProject===p.id && <span style={{marginLeft:'auto',fontSize:11,color:'var(--color-primary-500)'}}>✓</span>}
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}
      </div>

      <div className="ra-topnav__spacer" />

      {/* Notifications */}
      <div style={{position:'relative'}} ref={notifDd.ref}>
        <button className="ra-topnav__iconbtn" onClick={() => notifDd.setOpen(v => !v)} title="Notifications">
          <BellIcon />
          {unreadCount > 0 && <span className="ra-topnav__badge">{unreadCount > 9 ? '9+' : unreadCount}</span>}
        </button>
        {notifDd.open && (
          <div className="ra-topnav-dd ra-topnav-dd--notifications">
            <div className="ra-topnav-dd__header">
              <span className="ra-topnav-dd__header-title">Notifications</span>
              {unreadCount > 0 && <span className="ra-topnav-dd__mark-read" onClick={onMarkAllRead}>Mark all read</span>}
            </div>
            <div className="ra-notif-list">
              {notifications.length === 0
                ? <div style={{padding:'var(--space-6)',textAlign:'center',color:'var(--text-muted)',fontSize:'var(--text-sm)'}}>No notifications</div>
                : notifications.map((n, i) => (
                  <div key={i} className={`ra-notif-item ${n.unread?'ra-notif-item--unread':''}`}>
                    {n.unread && <span className="ra-notif-item__dot" />}
                    <div>
                      <div className="ra-notif-item__title">{n.title}</div>
                      {n.description && <div className="ra-notif-item__desc">{n.description}</div>}
                      <div className="ra-notif-item__time">{n.time}</div>
                    </div>
                  </div>
                ))}
            </div>
          </div>
        )}
      </div>

      {/* Right cluster */}
      <div className="ra-topnav__right">
        {/* Settings */}

        {/* Settings */}
        <div style={{position:'relative'}} ref={settingsDd.ref}>
          <button className="ra-topnav__trigger" onClick={() => settingsDd.setOpen(v => !v)}>
            <CogIcon />
            <span className="ra-topnav__settings-label">Settings</span>
            <span className="ra-topnav__trigger-caret"><ChevronDown /></span>
          </button>
          {settingsDd.open && (
            <div className="ra-topnav-dd">
              <div className="ra-topnav-dd__section">
                <div className="ra-topnav-dd__label">System</div>
                {defaultSettings.map((item, i) => (
                  <div key={i} className="ra-topnav-dd__item" onClick={() => { item.onClick && item.onClick(); settingsDd.setOpen(false); }}>
                    <span className="ra-topnav-dd__item-icon">{item.icon}</span><span>{item.label}</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* User */}
        <div style={{position:'relative'}} ref={userDd.ref}>
          <button className="ra-topnav__trigger" onClick={() => userDd.setOpen(v => !v)}>
            {user.avatar
              ? <img style={{width:24,height:24,borderRadius:'50%',objectFit:'cover'}} src={user.avatar} alt={user.name} />
              : <AvatarInitials name={user.name||'User'} size={24} />}
            <span className="ra-topnav__user-label">{user.name||'Account'}</span>
            <span className="ra-topnav__trigger-caret"><ChevronDown /></span>
          </button>
          {userDd.open && (
            <div className="ra-topnav-dd">
              <div className="ra-topnav-dd__user">
                <AvatarInitials name={user.name||'User'} size={36} />
                <div>
                  <div className="ra-topnav-dd__user-name">{user.name||'User'}</div>
                  <div className="ra-topnav-dd__user-email">{user.email}</div>
                </div>
              </div>
              <div className="ra-topnav-dd__section">
                <div className="ra-topnav-dd__item" onClick={() => { onProfileClick && onProfileClick(); userDd.setOpen(false); }}>
                  <span className="ra-topnav-dd__item-icon"><UserIcon /></span><span>Profile</span>
                </div>
                <div className="ra-topnav-dd__item" onClick={() => { onSettingsClick && onSettingsClick(); userDd.setOpen(false); }}>
                  <span className="ra-topnav-dd__item-icon"><CogIcon /></span><span>Preferences</span>
                </div>
              </div>
              <div className="ra-topnav-dd__section">
                <div className="ra-topnav-dd__item ra-topnav-dd__item--danger" onClick={() => { onLogout && onLogout(); userDd.setOpen(false); }}>
                  <span className="ra-topnav-dd__item-icon"><LogOutIcon /></span><span>Sign out</span>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
