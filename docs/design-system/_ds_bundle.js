/* @ds-bundle: {"format":4,"namespace":"RockAdminDesignSystem_fced52","components":[{"name":"Button","sourcePath":"components/buttons/Button.jsx"},{"name":"IconButton","sourcePath":"components/buttons/IconButton.jsx"},{"name":"BarChart","sourcePath":"components/charts/BarChart.jsx"},{"name":"DonutChart","sourcePath":"components/charts/DonutChart.jsx"},{"name":"LineChart","sourcePath":"components/charts/LineChart.jsx"},{"name":"StatCard","sourcePath":"components/charts/StatCard.jsx"},{"name":"Avatar","sourcePath":"components/data-display/Avatar.jsx"},{"name":"AvatarGroup","sourcePath":"components/data-display/Avatar.jsx"},{"name":"Badge","sourcePath":"components/data-display/Badge.jsx"},{"name":"Table","sourcePath":"components/data-display/Table.jsx"},{"name":"TreeView","sourcePath":"components/data-display/TreeView.jsx"},{"name":"Alert","sourcePath":"components/feedback/Alert.jsx"},{"name":"ConfirmModal","sourcePath":"components/feedback/ConfirmModal.jsx"},{"name":"Modal","sourcePath":"components/feedback/Modal.jsx"},{"name":"Offcanvas","sourcePath":"components/feedback/Offcanvas.jsx"},{"name":"ToastProvider","sourcePath":"components/feedback/Toast.jsx"},{"name":"Toast","sourcePath":"components/feedback/Toast.jsx"},{"name":"Checkbox","sourcePath":"components/forms/Checkbox.jsx"},{"name":"FormField","sourcePath":"components/forms/FormField.jsx"},{"name":"Input","sourcePath":"components/forms/Input.jsx"},{"name":"Radio","sourcePath":"components/forms/Radio.jsx"},{"name":"RadioGroup","sourcePath":"components/forms/Radio.jsx"},{"name":"SearchSelect","sourcePath":"components/forms/SearchSelect.jsx"},{"name":"Select","sourcePath":"components/forms/Select.jsx"},{"name":"Switch","sourcePath":"components/forms/Switch.jsx"},{"name":"Textarea","sourcePath":"components/forms/Textarea.jsx"},{"name":"Card","sourcePath":"components/layout/Card.jsx"},{"name":"Footer","sourcePath":"components/layout/Footer.jsx"},{"name":"InpageSidebar","sourcePath":"components/layout/InpageSidebar.jsx"},{"name":"InpageLayout","sourcePath":"components/layout/InpageSidebar.jsx"},{"name":"PageHeader","sourcePath":"components/layout/PageHeader.jsx"},{"name":"Breadcrumb","sourcePath":"components/navigation/Breadcrumb.jsx"},{"name":"Dropdown","sourcePath":"components/navigation/Dropdown.jsx"},{"name":"SecondNavbar","sourcePath":"components/navigation/SecondNavbar.jsx"},{"name":"TopNavbar","sourcePath":"components/navigation/TopNavbar.jsx"}],"sourceHashes":{"components/buttons/Button.jsx":"947e3537f6c3","components/buttons/IconButton.jsx":"0530feb9c70a","components/charts/BarChart.jsx":"2fbeb513e616","components/charts/DonutChart.jsx":"55f38eed74b7","components/charts/LineChart.jsx":"561c620fb18b","components/charts/StatCard.jsx":"b1a3b79e1925","components/data-display/Avatar.jsx":"f2d21941af6c","components/data-display/Badge.jsx":"55b3aee4945c","components/data-display/Table.jsx":"29a1d748f0d5","components/data-display/TreeView.jsx":"939860716f8f","components/feedback/Alert.jsx":"e97f59b17b33","components/feedback/ConfirmModal.jsx":"5e3e18a3a105","components/feedback/Modal.jsx":"70afc9850d16","components/feedback/Offcanvas.jsx":"ef7a4427954f","components/feedback/Toast.jsx":"89794a38d753","components/forms/Checkbox.jsx":"93837b27686a","components/forms/FormField.jsx":"ca7bf6d58012","components/forms/Input.jsx":"2bcd96fbc72e","components/forms/Radio.jsx":"142550d0f707","components/forms/SearchSelect.jsx":"b814ad71628d","components/forms/Select.jsx":"64f4ac29181a","components/forms/Switch.jsx":"8d6cea5e9a84","components/forms/Textarea.jsx":"11c5fb4aaa58","components/layout/Card.jsx":"fb2682973d29","components/layout/Footer.jsx":"e003445872d7","components/layout/InpageSidebar.jsx":"50f92d6e135e","components/layout/PageHeader.jsx":"cac41f30332b","components/navigation/Breadcrumb.jsx":"35dd0c9b00ba","components/navigation/Dropdown.jsx":"466328a6ce95","components/navigation/SecondNavbar.jsx":"6c68ddb117dd","components/navigation/TopNavbar.jsx":"78e956d93336","ui_kits/rock_admin/screens/DashboardEcom.jsx":"11d65f4a1230","ui_kits/rock_admin/screens/FormAdd.jsx":"7ad50e07fabd","ui_kits/rock_admin/screens/Login.jsx":"df45178f688c","ui_kits/rock_admin/screens/Monitoring.jsx":"91f87471c20d","ui_kits/rock_admin/screens/ProductList.jsx":"a4caf63fedc8","ui_kits/rock_admin/screens/Settings.jsx":"fc28e6781b34"},"inlinedExternals":[],"unexposedExports":[{"name":"useToast","sourcePath":"components/feedback/Toast.jsx"}]} */

(() => {

const __ds_ns = (window.RockAdminDesignSystem_fced52 = window.RockAdminDesignSystem_fced52 || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/buttons/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const CSS = `
.ra-btn {
  display: inline-flex; align-items: center; justify-content: center;
  gap: var(--space-2); font-family: var(--font-sans); font-weight: var(--font-medium);
  border-radius: var(--radius-btn); border: 1px solid transparent;
  cursor: pointer; transition: var(--transition-colors);
  text-decoration: none; white-space: nowrap; outline: none;
  position: relative; user-select: none; line-height: 1; flex-shrink: 0;
  letter-spacing: var(--tracking-tight);
}
.ra-btn:focus-visible { box-shadow: var(--shadow-focus); }
.ra-btn:disabled { cursor: not-allowed; opacity: 0.48; pointer-events: none; }
.ra-btn--sm  { height: 28px; padding: 0 10px; font-size: var(--text-xs); gap: var(--space-1-5); }
.ra-btn--md  { height: 32px; padding: 0 14px; font-size: var(--text-base); }
.ra-btn--lg  { height: 38px; padding: 0 18px; font-size: var(--text-md); }
.ra-btn--xl  { height: 46px; padding: 0 24px; font-size: var(--text-lg); }
.ra-btn--full { width: 100%; }
/* Primary */
.ra-btn--primary { background: var(--color-primary-600); color: #fff; border-color: var(--color-primary-700); }
.ra-btn--primary:hover:not(:disabled) { background: var(--color-primary-700); border-color: var(--color-primary-800); }
.ra-btn--primary:active:not(:disabled) { background: var(--color-primary-800); }
/* Secondary */
.ra-btn--secondary { background: var(--bg-surface); color: var(--text-primary); border-color: var(--border-color-strong); box-shadow: var(--shadow-xs); }
.ra-btn--secondary:hover:not(:disabled) { background: var(--color-neutral-50); border-color: var(--color-neutral-300); }
/* Ghost */
.ra-btn--ghost { background: transparent; color: var(--text-secondary); }
.ra-btn--ghost:hover:not(:disabled) { background: var(--color-neutral-100); color: var(--text-primary); }
/* Danger */
.ra-btn--danger { background: var(--color-error-600); color: #fff; border-color: var(--color-error-700); }
.ra-btn--danger:hover:not(:disabled) { background: var(--color-error-700); }
/* Danger Ghost */
.ra-btn--danger-ghost { background: transparent; color: var(--color-error-600); }
.ra-btn--danger-ghost:hover:not(:disabled) { background: var(--color-error-50); color: var(--color-error-700); }
/* Outline */
.ra-btn--outline { background: transparent; color: var(--color-primary-600); border-color: var(--color-primary-400); }
.ra-btn--outline:hover:not(:disabled) { background: var(--color-primary-50); border-color: var(--color-primary-600); }
/* Success */
.ra-btn--success { background: var(--color-success-600); color: #fff; border-color: var(--color-success-700); }
.ra-btn--success:hover:not(:disabled) { background: var(--color-success-700); }
/* Warning */
.ra-btn--warning { background: var(--color-warning-500); color: #fff; border-color: var(--color-warning-600); }
.ra-btn--warning:hover:not(:disabled) { background: var(--color-warning-600); }
/* Nav variant (used inside dark navbars) */
.ra-btn--nav { background: transparent; color: var(--nav-primary-text); border-color: transparent; font-size: var(--text-sm); }
.ra-btn--nav:hover:not(:disabled) { background: var(--nav-primary-hover); color: var(--nav-primary-text-hover); }
.ra-btn--nav.ra-btn--active { background: var(--nav-primary-active); color: var(--nav-primary-text-active); }
/* Spinner */
.ra-btn__spinner {
  width: 13px; height: 13px; border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.25); border-top-color: currentColor;
  animation: ra-spin 0.6s linear infinite; flex-shrink: 0;
}
.ra-btn--secondary .ra-btn__spinner,
.ra-btn--ghost .ra-btn__spinner,
.ra-btn--outline .ra-btn__spinner { border-color: rgba(0,0,0,0.12); border-top-color: currentColor; }
@keyframes ra-spin { to { transform: rotate(360deg); } }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-btn-css')) {
  const s = document.createElement('style');
  s.id = 'ra-btn-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function Button({
  children,
  variant = 'primary',
  size = 'md',
  disabled = false,
  loading = false,
  leftIcon = null,
  rightIcon = null,
  fullWidth = false,
  active = false,
  onClick,
  type = 'button',
  className = '',
  style,
  ...props
}) {
  const cls = ['ra-btn', `ra-btn--${size}`, `ra-btn--${variant}`, fullWidth ? 'ra-btn--full' : '', active ? 'ra-btn--active' : '', className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("button", _extends({
    type: type,
    className: cls,
    disabled: disabled || loading,
    onClick: onClick,
    style: style
  }, props), loading ? /*#__PURE__*/React.createElement("span", {
    className: "ra-btn__spinner"
  }) : leftIcon, /*#__PURE__*/React.createElement("span", null, children), !loading && rightIcon);
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/buttons/Button.jsx", error: String((e && e.message) || e) }); }

// components/buttons/IconButton.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const CSS = `
.ra-icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  border: 1px solid transparent; border-radius: var(--radius-md);
  cursor: pointer; transition: var(--transition-colors);
  outline: none; flex-shrink: 0; color: var(--text-secondary);
  background: transparent; padding: 0;
}
.ra-icon-btn:focus-visible { box-shadow: var(--shadow-focus); }
.ra-icon-btn:disabled { cursor: not-allowed; opacity: 0.45; pointer-events: none; }
.ra-icon-btn--xs { width: 22px; height: 22px; border-radius: var(--radius-sm); }
.ra-icon-btn--sm { width: 28px; height: 28px; }
.ra-icon-btn--md { width: 32px; height: 32px; }
.ra-icon-btn--lg { width: 38px; height: 38px; }
.ra-icon-btn--default:hover:not(:disabled) { background: var(--color-neutral-100); color: var(--text-primary); }
.ra-icon-btn--bordered { border-color: var(--border-color-strong); background: var(--bg-surface); box-shadow: var(--shadow-xs); }
.ra-icon-btn--bordered:hover:not(:disabled) { background: var(--color-neutral-50); }
.ra-icon-btn--danger:hover:not(:disabled) { background: var(--color-error-50); color: var(--color-error-600); }
.ra-icon-btn--nav { color: var(--nav-primary-text); border-radius: var(--radius-md); }
.ra-icon-btn--nav:hover:not(:disabled) { background: var(--nav-primary-hover); color: var(--nav-primary-text-hover); }
.ra-icon-btn--active { background: var(--nav-primary-active); color: var(--nav-primary-text-active); }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-icon-btn-css')) {
  const s = document.createElement('style');
  s.id = 'ra-icon-btn-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function IconButton({
  icon,
  size = 'md',
  variant = 'default',
  disabled = false,
  active = false,
  onClick,
  title,
  className = '',
  ...props
}) {
  const cls = ['ra-icon-btn', `ra-icon-btn--${size}`, `ra-icon-btn--${variant}`, active ? 'ra-icon-btn--active' : '', className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("button", _extends({
    type: "button",
    className: cls,
    disabled: disabled,
    onClick: onClick,
    title: title
  }, props), icon);
}
Object.assign(__ds_scope, { IconButton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/buttons/IconButton.jsx", error: String((e && e.message) || e) }); }

// components/charts/BarChart.jsx
try { (() => {
const PALETTE = ['var(--color-primary-500)', 'var(--color-indigo-500)', 'var(--color-teal-500)', 'var(--color-orange-500)'];
function BarChart({
  title,
  subtitle,
  labels = [],
  series = [],
  height = 220,
  horizontal = false,
  stacked = false,
  className = ''
}) {
  const [tooltip, setTooltip] = React.useState(null);
  const padL = 44,
    padR = 16,
    padT = 8,
    padB = 36;
  const svgRef = React.useRef(null);
  const [w, setW] = React.useState(600);
  React.useEffect(() => {
    const ob = new ResizeObserver(e => setW(e[0].contentRect.width));
    if (svgRef.current) ob.observe(svgRef.current);
    return () => ob.disconnect();
  }, []);
  const n = labels.length;
  const cw = w - padL - padR;
  const ch = height - padT - padB;
  const maxV = Math.max(...series.flatMap(s => stacked ? series.reduce((acc, s) => {
    acc.forEach((v, i) => {});
    return acc;
  }, Array(n).fill(0)) : s.data)) * 1.1 || 10;
  const groupW = cw / (n || 1);
  const barW = Math.max(4, Math.min(32, groupW / (stacked ? 1 : series.length) - 4));
  const fmt = v => v >= 1000 ? (v / 1000).toFixed(1) + 'k' : Math.round(v);
  const ticks = 5;
  return /*#__PURE__*/React.createElement("div", {
    className: ['ra-chart', className].join(' ')
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-chart__header"
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    className: "ra-chart__title"
  }, title), subtitle && /*#__PURE__*/React.createElement("div", {
    className: "ra-chart__subtitle"
  }, subtitle)), /*#__PURE__*/React.createElement("div", {
    className: "ra-chart__legend"
  }, series.map((s, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    className: "ra-chart__legend-item"
  }, /*#__PURE__*/React.createElement("span", {
    className: "ra-chart__legend-dot",
    style: {
      background: s.color || PALETTE[i % PALETTE.length]
    }
  }), s.name)))), /*#__PURE__*/React.createElement("div", {
    ref: svgRef,
    style: {
      position: 'relative'
    }
  }, /*#__PURE__*/React.createElement("svg", {
    width: "100%",
    height: height,
    viewBox: `0 0 ${w} ${height}`
  }, Array.from({
    length: ticks
  }, (_, i) => {
    const v = maxV * i / (ticks - 1);
    const y = padT + ch - v / maxV * ch;
    return /*#__PURE__*/React.createElement("g", {
      key: i
    }, /*#__PURE__*/React.createElement("line", {
      x1: padL,
      y1: y,
      x2: w - padR,
      y2: y,
      stroke: "var(--color-neutral-200)",
      strokeWidth: "1",
      strokeDasharray: i === 0 ? '' : '3,3'
    }), /*#__PURE__*/React.createElement("text", {
      x: padL - 6,
      y: y + 4,
      textAnchor: "end",
      fontSize: "11",
      fill: "var(--color-neutral-400)"
    }, fmt(v)));
  }), labels.map((l, li) => {
    const cx = padL + (li + 0.5) * groupW;
    return /*#__PURE__*/React.createElement("text", {
      key: li,
      x: cx,
      y: height - 8,
      textAnchor: "middle",
      fontSize: "11",
      fill: "var(--color-neutral-400)"
    }, l);
  }), labels.map((l, li) => {
    const cx = padL + li * groupW;
    return series.map((s, si) => {
      const color = s.color || PALETTE[si % PALETTE.length];
      const v = s.data[li] || 0;
      const bh = v / maxV * ch;
      const bx = cx + (stacked ? (groupW - barW) / 2 : si * (barW + 2) + (groupW - series.length * (barW + 2)) / 2);
      const by = padT + ch - bh;
      return /*#__PURE__*/React.createElement("rect", {
        key: `${li}-${si}`,
        x: bx,
        y: by,
        width: barW,
        height: Math.max(bh, 0),
        rx: "2",
        fill: color,
        opacity: "0.9",
        style: {
          cursor: 'pointer',
          transition: 'opacity 150ms'
        },
        onMouseEnter: () => setTooltip({
          x: bx + barW / 2,
          y: by,
          label: l,
          value: v,
          name: s.name
        }),
        onMouseLeave: () => setTooltip(null)
      });
    });
  })), tooltip && /*#__PURE__*/React.createElement("div", {
    className: "ra-chart-tooltip",
    style: {
      left: tooltip.x,
      top: tooltip.y
    }
  }, /*#__PURE__*/React.createElement("strong", null, tooltip.label), " \xB7 ", tooltip.name, ": ", fmt(tooltip.value))));
}
Object.assign(__ds_scope, { BarChart });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/charts/BarChart.jsx", error: String((e && e.message) || e) }); }

// components/charts/DonutChart.jsx
try { (() => {
const PALETTE = ['var(--color-primary-500)', 'var(--color-indigo-500)', 'var(--color-teal-500)', 'var(--color-orange-500)', 'var(--color-pink-500)', 'var(--color-success-500)'];
function DonutChart({
  title,
  subtitle,
  data = [],
  size = 180,
  className = ''
}) {
  const [hovered, setHovered] = React.useState(null);
  const total = data.reduce((s, d) => s + d.value, 0) || 1;
  const r = size / 2 - 10;
  const ri = r * 0.58;
  const cx = size / 2;
  const cy = size / 2;
  let angle = -Math.PI / 2;
  const segments = data.map((d, i) => {
    const ratio = d.value / total;
    const sweep = ratio * 2 * Math.PI;
    const x1 = cx + r * Math.cos(angle);
    const y1 = cy + r * Math.sin(angle);
    angle += sweep;
    const x2 = cx + r * Math.cos(angle);
    const y2 = cy + r * Math.sin(angle);
    const ix1 = cx + ri * Math.cos(angle - sweep);
    const iy1 = cy + ri * Math.sin(angle - sweep);
    const ix2 = cx + ri * Math.cos(angle);
    const iy2 = cy + ri * Math.sin(angle);
    const large = sweep > Math.PI ? 1 : 0;
    return {
      ...d,
      path: `M${x1},${y1} A${r},${r} 0 ${large},1 ${x2},${y2} L${ix2},${iy2} A${ri},${ri} 0 ${large},0 ${ix1},${iy1} Z`,
      color: d.color || PALETTE[i % PALETTE.length],
      percent: Math.round(ratio * 100)
    };
  });
  const active = hovered !== null ? segments[hovered] : null;
  return /*#__PURE__*/React.createElement("div", {
    className: ['ra-chart', className].join(' ')
  }, (title || subtitle) && /*#__PURE__*/React.createElement("div", {
    className: "ra-chart__header",
    style: {
      marginBottom: 'var(--space-3)'
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    className: "ra-chart__title"
  }, title), subtitle && /*#__PURE__*/React.createElement("div", {
    className: "ra-chart__subtitle"
  }, subtitle))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-6)'
    }
  }, /*#__PURE__*/React.createElement("svg", {
    width: size,
    height: size,
    viewBox: `0 0 ${size} ${size}`,
    style: {
      flexShrink: 0
    }
  }, segments.map((seg, i) => /*#__PURE__*/React.createElement("path", {
    key: i,
    d: seg.path,
    fill: seg.color,
    opacity: hovered === null || hovered === i ? 1 : 0.45,
    style: {
      cursor: 'pointer',
      transition: 'opacity 150ms',
      transform: hovered === i ? `translate(${(cx - cx) * 0}px)` : ''
    },
    onMouseEnter: () => setHovered(i),
    onMouseLeave: () => setHovered(null)
  })), /*#__PURE__*/React.createElement("circle", {
    cx: cx,
    cy: cy,
    r: ri - 2,
    fill: "var(--bg-surface)"
  }), /*#__PURE__*/React.createElement("text", {
    x: cx,
    y: cy - 6,
    textAnchor: "middle",
    fontSize: "22",
    fontWeight: "600",
    fill: "var(--text-primary)",
    fontFamily: "var(--font-sans)"
  }, active ? active.percent + '%' : total >= 1000 ? (total / 1000).toFixed(1) + 'k' : total), /*#__PURE__*/React.createElement("text", {
    x: cx,
    y: cy + 14,
    textAnchor: "middle",
    fontSize: "11",
    fill: "var(--text-muted)",
    fontFamily: "var(--font-sans)"
  }, active ? active.label : 'Total')), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-2)',
      flex: 1
    }
  }, segments.map((seg, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 'var(--space-2)',
      cursor: 'pointer',
      opacity: hovered === null || hovered === i ? 1 : 0.5,
      transition: 'opacity 150ms'
    },
    onMouseEnter: () => setHovered(i),
    onMouseLeave: () => setHovered(null)
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 10,
      height: 10,
      borderRadius: 2,
      background: seg.color,
      flexShrink: 0
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--text-secondary)',
      flex: 1
    }
  }, seg.label), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-sm)',
      fontWeight: 'var(--font-semibold)',
      color: 'var(--text-primary)'
    }
  }, seg.percent, "%"))))));
}
Object.assign(__ds_scope, { DonutChart });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/charts/DonutChart.jsx", error: String((e && e.message) || e) }); }

// components/charts/LineChart.jsx
try { (() => {
const CSS = `
.ra-chart { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-card); padding: var(--space-5); box-shadow: var(--shadow-card); }
.ra-chart__header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--space-4); }
.ra-chart__title { font-weight: var(--font-semibold); font-size: var(--text-lg); color: var(--text-primary); }
.ra-chart__subtitle { font-size: var(--text-sm); color: var(--text-muted); margin-top: 2px; }
.ra-chart__legend { display: flex; gap: var(--space-4); flex-wrap: wrap; }
.ra-chart__legend-item { display: flex; align-items: center; gap: var(--space-1-5); font-size: var(--text-sm); color: var(--text-secondary); }
.ra-chart__legend-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.ra-chart-tooltip { position: absolute; background: var(--color-neutral-900); color: white; font-size: var(--text-xs); padding: 4px 8px; border-radius: var(--radius-md); pointer-events: none; white-space: nowrap; box-shadow: var(--shadow-lg); transform: translate(-50%, -110%); z-index: 10; }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-chart-css')) {
  const s = document.createElement('style');
  s.id = 'ra-chart-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
const PALETTE = ['var(--color-primary-500)', 'var(--color-indigo-500)', 'var(--color-teal-500)', 'var(--color-orange-500)', 'var(--color-pink-500)'];
function LineChart({
  title,
  subtitle,
  labels = [],
  series = [],
  height = 220,
  className = ''
}) {
  const [tooltip, setTooltip] = React.useState(null);
  const padL = 40,
    padR = 16,
    padT = 8,
    padB = 36;
  const svgRef = React.useRef(null);
  const allVals = series.flatMap(s => s.data);
  const minV = Math.min(0, ...allVals);
  const maxV = Math.max(...allVals) * 1.1 || 10;
  const range = maxV - minV || 1;
  const n = labels.length;
  const [w, setW] = React.useState(600);
  React.useEffect(() => {
    const ob = new ResizeObserver(e => setW(e[0].contentRect.width));
    if (svgRef.current) ob.observe(svgRef.current);
    return () => ob.disconnect();
  }, []);
  const cw = w - padL - padR;
  const ch = height - padT - padB;
  const xp = i => padL + i / (n - 1 || 1) * cw;
  const yp = v => padT + ch - (v - minV) / range * ch;
  const ticks = 5;
  const yTicks = Array.from({
    length: ticks
  }, (_, i) => minV + range * i / (ticks - 1));
  const fmt = v => v >= 1000 ? (v / 1000).toFixed(1) + 'k' : Math.round(v);
  return /*#__PURE__*/React.createElement("div", {
    className: ['ra-chart', className].join(' ')
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-chart__header"
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    className: "ra-chart__title"
  }, title), subtitle && /*#__PURE__*/React.createElement("div", {
    className: "ra-chart__subtitle"
  }, subtitle)), /*#__PURE__*/React.createElement("div", {
    className: "ra-chart__legend"
  }, series.map((s, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    className: "ra-chart__legend-item"
  }, /*#__PURE__*/React.createElement("span", {
    className: "ra-chart__legend-dot",
    style: {
      background: s.color || PALETTE[i % PALETTE.length]
    }
  }), s.name)))), /*#__PURE__*/React.createElement("div", {
    ref: svgRef,
    style: {
      position: 'relative'
    }
  }, /*#__PURE__*/React.createElement("svg", {
    width: "100%",
    height: height,
    viewBox: `0 0 ${w} ${height}`
  }, yTicks.map((v, i) => /*#__PURE__*/React.createElement("g", {
    key: i
  }, /*#__PURE__*/React.createElement("line", {
    x1: padL,
    y1: yp(v),
    x2: w - padR,
    y2: yp(v),
    stroke: "var(--color-neutral-200)",
    strokeWidth: "1",
    strokeDasharray: i === 0 ? '' : '3,3'
  }), /*#__PURE__*/React.createElement("text", {
    x: padL - 6,
    y: yp(v) + 4,
    textAnchor: "end",
    fontSize: "11",
    fill: "var(--color-neutral-400)"
  }, fmt(v)))), labels.map((l, i) => /*#__PURE__*/React.createElement("text", {
    key: i,
    x: xp(i),
    y: height - 8,
    textAnchor: "middle",
    fontSize: "11",
    fill: "var(--color-neutral-400)"
  }, l)), series.map((s, si) => {
    const color = s.color || PALETTE[si % PALETTE.length];
    const pts = s.data.map((v, i) => `${xp(i)},${yp(v)}`).join(' ');
    const area = `${padL},${yp(0)} ${pts} ${xp(s.data.length - 1)},${yp(0)}`;
    return /*#__PURE__*/React.createElement("g", {
      key: si
    }, /*#__PURE__*/React.createElement("defs", null, /*#__PURE__*/React.createElement("linearGradient", {
      id: `lg${si}`,
      x1: "0",
      y1: "0",
      x2: "0",
      y2: "1"
    }, /*#__PURE__*/React.createElement("stop", {
      offset: "0%",
      stopColor: color,
      stopOpacity: "0.18"
    }), /*#__PURE__*/React.createElement("stop", {
      offset: "100%",
      stopColor: color,
      stopOpacity: "0"
    }))), /*#__PURE__*/React.createElement("polygon", {
      points: area,
      fill: `url(#lg${si})`
    }), /*#__PURE__*/React.createElement("polyline", {
      points: pts,
      fill: "none",
      stroke: color,
      strokeWidth: "2",
      strokeLinejoin: "round",
      strokeLinecap: "round"
    }), s.data.map((v, i) => /*#__PURE__*/React.createElement("circle", {
      key: i,
      cx: xp(i),
      cy: yp(v),
      r: "3",
      fill: color,
      stroke: "white",
      strokeWidth: "1.5",
      style: {
        cursor: 'pointer'
      },
      onMouseEnter: () => setTooltip({
        x: xp(i),
        y: yp(v),
        label: labels[i],
        value: v,
        name: s.name,
        color
      }),
      onMouseLeave: () => setTooltip(null)
    })));
  })), tooltip && /*#__PURE__*/React.createElement("div", {
    className: "ra-chart-tooltip",
    style: {
      left: tooltip.x,
      top: tooltip.y
    }
  }, /*#__PURE__*/React.createElement("strong", null, tooltip.label), " \xB7 ", tooltip.name, ": ", fmt(tooltip.value))));
}
Object.assign(__ds_scope, { LineChart });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/charts/LineChart.jsx", error: String((e && e.message) || e) }); }

// components/charts/StatCard.jsx
try { (() => {
const CSS = `
.ra-stat { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-card); padding: var(--space-5); box-shadow: var(--shadow-card); position: relative; overflow: hidden; }
.ra-stat__eyebrow { font-size: var(--text-xs); font-weight: var(--font-semibold); color: var(--text-muted); text-transform: uppercase; letter-spacing: var(--tracking-wider); margin-bottom: var(--space-2); display: flex; align-items: center; gap: var(--space-2); }
.ra-stat__value { font-size: var(--text-4xl); font-weight: var(--font-semibold); color: var(--text-primary); letter-spacing: var(--tracking-tight); line-height: 1; }
.ra-stat__sub { font-size: var(--text-sm); color: var(--text-muted); margin-top: var(--space-1); }
.ra-stat__trend { display: inline-flex; align-items: center; gap: 3px; font-size: var(--text-sm); font-weight: var(--font-medium); padding: 1px 7px; border-radius: var(--radius-full); margin-top: var(--space-2-5); }
.ra-stat__trend--up   { background: var(--color-success-50); color: var(--color-success-700); }
.ra-stat__trend--down { background: var(--color-error-50);   color: var(--color-error-700); }
.ra-stat__trend--flat { background: var(--color-neutral-100); color: var(--text-muted); }
.ra-stat__icon { position: absolute; right: var(--space-5); top: var(--space-5); width: 42px; height: 42px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; opacity: 0.15; }
.ra-stat__sparkline { margin-top: var(--space-3); }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-stat-css')) {
  const s = document.createElement('style');
  s.id = 'ra-stat-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function Sparkline({
  data,
  color = 'var(--color-primary-500)',
  height = 40
}) {
  if (!data || data.length < 2) return null;
  const min = Math.min(...data);
  const max = Math.max(...data);
  const range = max - min || 1;
  const w = 120;
  const h = height;
  const pts = data.map((v, i) => `${i / (data.length - 1) * w},${h - (v - min) / range * (h - 4) - 2}`).join(' ');
  const area = `0,${h} ${pts} ${w},${h}`;
  return /*#__PURE__*/React.createElement("svg", {
    width: w,
    height: h,
    viewBox: `0 0 ${w} ${h}`,
    style: {
      overflow: 'visible'
    }
  }, /*#__PURE__*/React.createElement("defs", null, /*#__PURE__*/React.createElement("linearGradient", {
    id: "sg",
    x1: "0",
    y1: "0",
    x2: "0",
    y2: "1"
  }, /*#__PURE__*/React.createElement("stop", {
    offset: "0%",
    stopColor: color,
    stopOpacity: "0.2"
  }), /*#__PURE__*/React.createElement("stop", {
    offset: "100%",
    stopColor: color,
    stopOpacity: "0"
  }))), /*#__PURE__*/React.createElement("polygon", {
    points: area,
    fill: "url(#sg)"
  }), /*#__PURE__*/React.createElement("polyline", {
    points: pts,
    fill: "none",
    stroke: color,
    strokeWidth: "1.5",
    strokeLinejoin: "round",
    strokeLinecap: "round"
  }));
}
function StatCard({
  label,
  value,
  subLabel,
  trend,
  trendLabel,
  icon,
  iconColor = 'var(--color-primary-600)',
  sparklineData,
  iconBg,
  className = '',
  style
}) {
  const trendDir = trend > 0 ? 'up' : trend < 0 ? 'down' : 'flat';
  return /*#__PURE__*/React.createElement("div", {
    className: ['ra-stat', className].join(' '),
    style: style
  }, icon && /*#__PURE__*/React.createElement("div", {
    className: "ra-stat__icon",
    style: {
      background: iconBg || iconColor,
      opacity: 1
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      opacity: 0.8,
      color: 'white'
    }
  }, icon)), /*#__PURE__*/React.createElement("div", {
    className: "ra-stat__eyebrow"
  }, label), /*#__PURE__*/React.createElement("div", {
    className: "ra-stat__value"
  }, value), subLabel && /*#__PURE__*/React.createElement("div", {
    className: "ra-stat__sub"
  }, subLabel), trend !== undefined && /*#__PURE__*/React.createElement("div", {
    className: `ra-stat__trend ra-stat__trend--${trendDir}`
  }, trendDir === 'up' && '↑', trendDir === 'down' && '↓', Math.abs(trend), "% ", trendLabel), sparklineData && /*#__PURE__*/React.createElement("div", {
    className: "ra-stat__sparkline"
  }, /*#__PURE__*/React.createElement(Sparkline, {
    data: sparklineData,
    color: iconColor
  })));
}
Object.assign(__ds_scope, { StatCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/charts/StatCard.jsx", error: String((e && e.message) || e) }); }

// components/data-display/Avatar.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const CSS = `
.ra-avatar { border-radius: 50%; object-fit: cover; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-weight: var(--font-semibold); color: white; background: var(--color-primary-600); overflow: hidden; }
.ra-avatar--xs  { width: 20px;  height: 20px;  font-size: 9px; }
.ra-avatar--sm  { width: 28px;  height: 28px;  font-size: 11px; }
.ra-avatar--md  { width: 36px;  height: 36px;  font-size: 13px; }
.ra-avatar--lg  { width: 48px;  height: 48px;  font-size: 16px; }
.ra-avatar--xl  { width: 64px;  height: 64px;  font-size: 20px; }
.ra-avatar--2xl { width: 80px;  height: 80px;  font-size: 24px; }
.ra-avatar--sq  { border-radius: var(--radius-md); }
.ra-avatar-group { display: flex; }
.ra-avatar-group .ra-avatar { border: 2px solid var(--bg-surface); margin-left: -8px; }
.ra-avatar-group .ra-avatar:first-child { margin-left: 0; }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-avatar-css')) {
  const s = document.createElement('style');
  s.id = 'ra-avatar-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
const COLORS = ['#2563eb', '#7c3aed', '#db2777', '#059669', '#d97706', '#dc2626', '#0284c7', '#4f46e5'];
function getColor(name) {
  let h = 0;
  for (let i = 0; i < (name || '').length; i++) h = h * 31 + name.charCodeAt(i) & 0xffffffff;
  return COLORS[Math.abs(h) % COLORS.length];
}
function initials(name) {
  if (!name) return '?';
  const parts = name.trim().split(/\s+/);
  return parts.length === 1 ? parts[0][0].toUpperCase() : (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}
function Avatar({
  src,
  name,
  size = 'md',
  square = false,
  className = '',
  style
}) {
  const cls = ['ra-avatar', `ra-avatar--${size}`, square ? 'ra-avatar--sq' : '', className].filter(Boolean).join(' ');
  if (src) return /*#__PURE__*/React.createElement("img", {
    className: cls,
    src: src,
    alt: name || '',
    style: style
  });
  return /*#__PURE__*/React.createElement("div", {
    className: cls,
    style: {
      background: getColor(name),
      ...style
    }
  }, initials(name));
}
function AvatarGroup({
  avatars = [],
  max = 4,
  size = 'sm'
}) {
  const visible = avatars.slice(0, max);
  const remaining = avatars.length - max;
  return /*#__PURE__*/React.createElement("div", {
    className: "ra-avatar-group"
  }, visible.map((a, i) => /*#__PURE__*/React.createElement(Avatar, _extends({
    key: i
  }, a, {
    size: size
  }))), remaining > 0 && /*#__PURE__*/React.createElement(Avatar, {
    name: `+${remaining}`,
    size: size,
    style: {
      background: 'var(--color-neutral-400)'
    }
  }));
}
Object.assign(__ds_scope, { Avatar, AvatarGroup });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data-display/Avatar.jsx", error: String((e && e.message) || e) }); }

// components/data-display/Badge.jsx
try { (() => {
const CSS = `
.ra-badge {
  display: inline-flex; align-items: center; gap: 4px;
  font-family: var(--font-sans); font-weight: var(--font-medium);
  border-radius: var(--radius-badge); white-space: nowrap; line-height: 1;
  border: 1px solid transparent;
}
.ra-badge--xs { font-size: 10px; padding: 1px 5px; }
.ra-badge--sm { font-size: var(--text-xs); padding: 2px 7px; }
.ra-badge--md { font-size: var(--text-sm); padding: 3px 9px; }
.ra-badge--lg { font-size: var(--text-base); padding: 4px 11px; }
.ra-badge--dot::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
/* Color variants */
.ra-badge--gray    { background: var(--color-neutral-100); color: var(--color-neutral-700); border-color: var(--color-neutral-200); }
.ra-badge--blue    { background: var(--color-primary-50);  color: var(--color-primary-700); border-color: var(--color-primary-200); }
.ra-badge--green   { background: var(--color-success-50);  color: var(--color-success-700); border-color: var(--color-success-200); }
.ra-badge--yellow  { background: var(--color-warning-50);  color: var(--color-warning-700); border-color: var(--color-warning-200); }
.ra-badge--red     { background: var(--color-error-50);    color: var(--color-error-700);   border-color: var(--color-error-200);   }
.ra-badge--indigo  { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
.ra-badge--purple  { background: #faf5ff; color: #7c3aed; border-color: #e9d5ff; }
.ra-badge--pink    { background: #fdf2f8; color: #be185d; border-color: #fbcfe8; }
.ra-badge--teal    { background: #f0fdfa; color: #0f766e; border-color: #99f6e4; }
.ra-badge--orange  { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
/* Solid variants */
.ra-badge--solid-blue   { background: var(--color-primary-600); color: white; border-color: transparent; }
.ra-badge--solid-green  { background: var(--color-success-600); color: white; border-color: transparent; }
.ra-badge--solid-red    { background: var(--color-error-600);   color: white; border-color: transparent; }
.ra-badge--solid-gray   { background: var(--color-neutral-600); color: white; border-color: transparent; }
/* Pulse dot for nav badges */
.ra-badge--pulse { animation: ra-pulse 2s cubic-bezier(0.4,0,0.6,1) infinite; }
@keyframes ra-pulse { 0%,100%{opacity:1} 50%{opacity:.5} }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-badge-css')) {
  const s = document.createElement('style');
  s.id = 'ra-badge-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function Badge({
  children,
  color = 'gray',
  size = 'sm',
  solid = false,
  dot = false,
  pulse = false,
  className = '',
  style
}) {
  const variant = solid ? `solid-${color}` : color;
  const cls = ['ra-badge', `ra-badge--${size}`, `ra-badge--${variant}`, dot ? 'ra-badge--dot' : '', pulse ? 'ra-badge--pulse' : '', className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("span", {
    className: cls,
    style: style
  }, children);
}
Object.assign(__ds_scope, { Badge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data-display/Badge.jsx", error: String((e && e.message) || e) }); }

// components/data-display/Table.jsx
try { (() => {
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
  const s = document.createElement('style');
  s.id = 'ra-table-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
const SortIcon = ({
  dir
}) => /*#__PURE__*/React.createElement("span", {
  className: `ra-th__sort ${dir ? `ra-th__sort--${dir}` : ''}`
}, /*#__PURE__*/React.createElement("svg", {
  className: "ra-th__sort-up",
  width: "8",
  height: "5",
  viewBox: "0 0 8 5",
  fill: "currentColor"
}, /*#__PURE__*/React.createElement("path", {
  d: "M4 0L8 5H0L4 0z"
})), /*#__PURE__*/React.createElement("svg", {
  className: "ra-th__sort-down",
  width: "8",
  height: "5",
  viewBox: "0 0 8 5",
  fill: "currentColor"
}, /*#__PURE__*/React.createElement("path", {
  d: "M4 5L0 0h8L4 5z"
})));
const SearchIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "13",
  height: "13",
  viewBox: "0 0 16 16",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("circle", {
  cx: "7",
  cy: "7",
  r: "5"
}), /*#__PURE__*/React.createElement("line", {
  x1: "11",
  y1: "11",
  x2: "15",
  y2: "15"
}));
function Table({
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
  className = ''
}) {
  const allSelected = rows.length > 0 && rows.every(r => selectedIds.includes(r.id ?? r._id));
  const someSelected = rows.some(r => selectedIds.includes(r.id ?? r._id));
  const toggleAll = () => {
    if (!onSelectChange) return;
    if (allSelected) onSelectChange([]);else onSelectChange(rows.map(r => r.id ?? r._id));
  };
  const toggleRow = id => {
    if (!onSelectChange) return;
    onSelectChange(selectedIds.includes(id) ? selectedIds.filter(x => x !== id) : [...selectedIds, id]);
  };
  const handleSort = col => {
    if (!col.sortable || !onSortChange) return;
    if (sortKey === col.key) onSortChange(col.key, sortDir === 'asc' ? 'desc' : 'asc');else onSortChange(col.key, 'asc');
  };
  const showToolbar = toolbar || toolbarRight || onSearchChange || viewToggle;
  return /*#__PURE__*/React.createElement("div", {
    className: ['ra-card', className].join(' '),
    style: {
      overflow: 'hidden'
    }
  }, showToolbar && /*#__PURE__*/React.createElement("div", {
    className: "ra-table-toolbar"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-table-toolbar__left"
  }, onSearchChange && /*#__PURE__*/React.createElement("div", {
    className: "ra-table-search"
  }, /*#__PURE__*/React.createElement("span", {
    className: "ra-table-search__icon"
  }, /*#__PURE__*/React.createElement(SearchIcon, null)), /*#__PURE__*/React.createElement("input", {
    placeholder: searchPlaceholder,
    value: searchValue || '',
    onChange: e => onSearchChange(e.target.value)
  })), toolbar), /*#__PURE__*/React.createElement("div", {
    className: "ra-table-toolbar__right"
  }, toolbarRight, viewToggle && /*#__PURE__*/React.createElement("div", {
    className: "ra-view-toggle"
  }, viewToggle))), /*#__PURE__*/React.createElement("div", {
    className: "ra-table-wrap"
  }, /*#__PURE__*/React.createElement("table", {
    className: ['ra-table', density !== 'md' ? `ra-table--${density}` : ''].filter(Boolean).join(' ')
  }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, selectable && /*#__PURE__*/React.createElement("th", {
    className: "ra-col-check"
  }, /*#__PURE__*/React.createElement("input", {
    type: "checkbox",
    checked: allSelected,
    ref: el => {
      if (el) el.indeterminate = someSelected && !allSelected;
    },
    onChange: toggleAll,
    style: {
      cursor: 'pointer'
    }
  })), columns.map(col => /*#__PURE__*/React.createElement("th", {
    key: col.key,
    className: col.sortable ? 'ra-th--sortable' : '',
    style: {
      width: col.width
    },
    onClick: () => handleSort(col)
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-th__inner"
  }, /*#__PURE__*/React.createElement("span", null, col.label), col.sortable && /*#__PURE__*/React.createElement(SortIcon, {
    dir: sortKey === col.key ? sortDir : null
  })))))), /*#__PURE__*/React.createElement("tbody", null, rows.length === 0 ? /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", {
    colSpan: columns.length + (selectable ? 1 : 0)
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-table-empty"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-table-empty__icon"
  }, /*#__PURE__*/React.createElement("svg", {
    width: "40",
    height: "40",
    viewBox: "0 0 40 40",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "1.2"
  }, /*#__PURE__*/React.createElement("rect", {
    x: "6",
    y: "6",
    width: "28",
    height: "28",
    rx: "4"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "13",
    y1: "14",
    x2: "27",
    y2: "14"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "13",
    y1: "20",
    x2: "22",
    y2: "20"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "13",
    y1: "26",
    x2: "19",
    y2: "26"
  }))), /*#__PURE__*/React.createElement("div", {
    className: "ra-table-empty__title"
  }, emptyTitle), /*#__PURE__*/React.createElement("div", {
    className: "ra-table-empty__desc"
  }, emptyDescription)))) : rows.map((row, ri) => {
    const id = row.id ?? row._id ?? ri;
    const isSelected = selectedIds.includes(id);
    return /*#__PURE__*/React.createElement("tr", {
      key: id,
      className: isSelected ? 'ra-tr--selected' : ''
    }, selectable && /*#__PURE__*/React.createElement("td", {
      className: "ra-col-check"
    }, /*#__PURE__*/React.createElement("input", {
      type: "checkbox",
      checked: isSelected,
      onChange: () => toggleRow(id),
      style: {
        cursor: 'pointer'
      }
    })), columns.map(col => /*#__PURE__*/React.createElement("td", {
      key: col.key,
      style: {
        textAlign: col.align || 'left',
        width: col.width
      }
    }, col.render ? col.render(row[col.key], row, ri) : row[col.key])));
  })))), pagination && /*#__PURE__*/React.createElement("div", {
    className: "ra-table-footer"
  }, /*#__PURE__*/React.createElement("span", {
    className: "ra-table-footer__info"
  }, pagination.info), /*#__PURE__*/React.createElement("div", {
    className: "ra-table-footer__pages"
  }, /*#__PURE__*/React.createElement("button", {
    className: "ra-page-btn",
    disabled: pagination.page <= 1,
    onClick: () => pagination.onChange(pagination.page - 1)
  }, /*#__PURE__*/React.createElement("svg", {
    width: "12",
    height: "12",
    viewBox: "0 0 12 12",
    stroke: "currentColor",
    strokeWidth: "2",
    strokeLinecap: "round",
    fill: "none"
  }, /*#__PURE__*/React.createElement("polyline", {
    points: "8,2 4,6 8,10"
  }))), Array.from({
    length: Math.min(pagination.pages, 7)
  }, (_, i) => {
    const p = pagination.pages <= 7 ? i + 1 : i + Math.max(1, pagination.page - 3);
    if (p > pagination.pages) return null;
    return /*#__PURE__*/React.createElement("button", {
      key: p,
      className: `ra-page-btn ${pagination.page === p ? 'ra-page-btn--active' : ''}`,
      onClick: () => pagination.onChange(p)
    }, p);
  }), /*#__PURE__*/React.createElement("button", {
    className: "ra-page-btn",
    disabled: pagination.page >= pagination.pages,
    onClick: () => pagination.onChange(pagination.page + 1)
  }, /*#__PURE__*/React.createElement("svg", {
    width: "12",
    height: "12",
    viewBox: "0 0 12 12",
    stroke: "currentColor",
    strokeWidth: "2",
    strokeLinecap: "round",
    fill: "none"
  }, /*#__PURE__*/React.createElement("polyline", {
    points: "4,2 8,6 4,10"
  }))))));
}
Object.assign(__ds_scope, { Table });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data-display/Table.jsx", error: String((e && e.message) || e) }); }

// components/data-display/TreeView.jsx
try { (() => {
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
  const s = document.createElement('style');
  s.id = 'ra-tree-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
const FolderIcon = ({
  open
}) => open ? /*#__PURE__*/React.createElement("svg", {
  width: "15",
  height: "15",
  viewBox: "0 0 16 16",
  fill: "var(--color-warning-400)",
  stroke: "var(--color-warning-600)",
  strokeWidth: "1"
}, /*#__PURE__*/React.createElement("path", {
  d: "M1 4a1 1 0 011-1h4l1.5 1.5H14a1 1 0 011 1V12a1 1 0 01-1 1H2a1 1 0 01-1-1V4z"
})) : /*#__PURE__*/React.createElement("svg", {
  width: "15",
  height: "15",
  viewBox: "0 0 16 16",
  fill: "var(--color-warning-300)",
  stroke: "var(--color-warning-500)",
  strokeWidth: "1"
}, /*#__PURE__*/React.createElement("path", {
  d: "M1 4a1 1 0 011-1h4l1.5 1.5H14a1 1 0 011 1V12a1 1 0 01-1 1H2a1 1 0 01-1-1V4z"
}));
const FileIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "13",
  height: "14",
  viewBox: "0 0 13 14",
  fill: "var(--color-neutral-200)",
  stroke: "var(--color-neutral-400)",
  strokeWidth: "1"
}, /*#__PURE__*/React.createElement("path", {
  d: "M2 1h7l3 3v9a1 1 0 01-1 1H2a1 1 0 01-1-1V2a1 1 0 011-1z"
}), /*#__PURE__*/React.createElement("polyline", {
  points: "9,1 9,4 12,4",
  fill: "none"
}));
const ChevronRight = () => /*#__PURE__*/React.createElement("svg", {
  width: "12",
  height: "12",
  viewBox: "0 0 12 12",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("polyline", {
  points: "4,2 8,6 4,10"
}));
const ChevronDown = () => /*#__PURE__*/React.createElement("svg", {
  width: "12",
  height: "12",
  viewBox: "0 0 12 12",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("polyline", {
  points: "2,4 6,8 10,4"
}));
function TreeRows({
  nodes,
  columns,
  depth,
  expandedIds,
  onToggle
}) {
  return nodes.map(node => {
    const hasChildren = node.children && node.children.length > 0;
    const isExpanded = expandedIds.has(node.id);
    return /*#__PURE__*/React.createElement(React.Fragment, {
      key: node.id
    }, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("div", {
      className: "ra-tree-cell",
      style: {
        paddingLeft: depth * 20
      }
    }, /*#__PURE__*/React.createElement("button", {
      className: `ra-tree-toggle ${!hasChildren ? 'ra-tree-toggle--leaf' : ''}`,
      onClick: () => hasChildren && onToggle(node.id)
    }, hasChildren ? isExpanded ? /*#__PURE__*/React.createElement(ChevronDown, null) : /*#__PURE__*/React.createElement(ChevronRight, null) : /*#__PURE__*/React.createElement(ChevronRight, null)), /*#__PURE__*/React.createElement("span", {
      className: "ra-tree-icon"
    }, hasChildren ? /*#__PURE__*/React.createElement(FolderIcon, {
      open: isExpanded
    }) : /*#__PURE__*/React.createElement(FileIcon, null)), /*#__PURE__*/React.createElement("span", {
      style: {
        fontWeight: hasChildren ? 'var(--font-medium)' : 'var(--font-normal)'
      }
    }, node.label))), columns.slice(1).map(col => /*#__PURE__*/React.createElement("td", {
      key: col.key
    }, col.render ? col.render(node[col.key], node) : node[col.key]))), hasChildren && isExpanded && /*#__PURE__*/React.createElement(TreeRows, {
      nodes: node.children,
      columns: columns,
      depth: depth + 1,
      expandedIds: expandedIds,
      onToggle: onToggle
    }));
  });
}
function TreeView({
  nodes = [],
  columns = [],
  defaultExpanded = [],
  className = ''
}) {
  const [expandedIds, setExpandedIds] = React.useState(new Set(defaultExpanded));
  const toggle = id => setExpandedIds(prev => {
    const next = new Set(prev);
    next.has(id) ? next.delete(id) : next.add(id);
    return next;
  });
  return /*#__PURE__*/React.createElement("div", {
    className: ['ra-card', className].join(' '),
    style: {
      overflow: 'hidden'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      overflowX: 'auto'
    }
  }, /*#__PURE__*/React.createElement("table", {
    className: "ra-tree"
  }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, columns.map(col => /*#__PURE__*/React.createElement("th", {
    key: col.key,
    style: {
      width: col.width
    }
  }, col.label)))), /*#__PURE__*/React.createElement("tbody", null, /*#__PURE__*/React.createElement(TreeRows, {
    nodes: nodes,
    columns: columns,
    depth: 0,
    expandedIds: expandedIds,
    onToggle: toggle
  })))));
}
Object.assign(__ds_scope, { TreeView });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data-display/TreeView.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Alert.jsx
try { (() => {
const CSS = `
.ra-alert { display: flex; gap: var(--space-3); padding: var(--space-3) var(--space-4); border-radius: var(--radius-lg); border: 1px solid; }
.ra-alert--info    { background: var(--color-info-50);    border-color: #bae6fd; color: var(--color-info-700); }
.ra-alert--success { background: var(--color-success-50); border-color: var(--color-success-200); color: var(--color-success-700); }
.ra-alert--warning { background: var(--color-warning-50); border-color: var(--color-warning-200); color: var(--color-warning-700); }
.ra-alert--error   { background: var(--color-error-50);   border-color: var(--color-error-200);   color: var(--color-error-700); }
.ra-alert__icon { flex-shrink: 0; margin-top: 1px; }
.ra-alert__body { flex: 1; min-width: 0; }
.ra-alert__title { font-weight: var(--font-semibold); font-size: var(--text-md); margin-bottom: 2px; }
.ra-alert__desc { font-size: var(--text-base); line-height: var(--leading-snug); opacity: 0.9; }
.ra-alert__close { flex-shrink: 0; cursor: pointer; opacity: 0.6; padding: 2px; margin-left: auto; }
.ra-alert__close:hover { opacity: 1; }
.ra-alert--sm { padding: var(--space-2) var(--space-3); gap: var(--space-2); }
.ra-alert--sm .ra-alert__title { font-size: var(--text-base); }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-alert-css')) {
  const s = document.createElement('style');
  s.id = 'ra-alert-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
const ICONS = {
  info: /*#__PURE__*/React.createElement("svg", {
    width: "16",
    height: "16",
    viewBox: "0 0 16 16",
    fill: "currentColor"
  }, /*#__PURE__*/React.createElement("circle", {
    cx: "8",
    cy: "8",
    r: "8",
    opacity: ".15"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M7.25 7h1.5v5h-1.5V7zm0-2.5h1.5v1.5h-1.5V4.5z"
  })),
  success: /*#__PURE__*/React.createElement("svg", {
    width: "16",
    height: "16",
    viewBox: "0 0 16 16",
    fill: "currentColor"
  }, /*#__PURE__*/React.createElement("circle", {
    cx: "8",
    cy: "8",
    r: "8",
    opacity: ".15"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M5 8l2 2 4-4",
    stroke: "currentColor",
    strokeWidth: "1.5",
    strokeLinecap: "round",
    strokeLinejoin: "round",
    fill: "none"
  })),
  warning: /*#__PURE__*/React.createElement("svg", {
    width: "16",
    height: "16",
    viewBox: "0 0 16 16",
    fill: "currentColor"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M8 1L1 14h14L8 1z",
    opacity: ".15"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M7.25 6.5h1.5v4h-1.5V6.5zm0 5h1.5v1.5h-1.5V11.5z"
  })),
  error: /*#__PURE__*/React.createElement("svg", {
    width: "16",
    height: "16",
    viewBox: "0 0 16 16",
    fill: "currentColor"
  }, /*#__PURE__*/React.createElement("circle", {
    cx: "8",
    cy: "8",
    r: "8",
    opacity: ".15"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M5.5 5.5l5 5M10.5 5.5l-5 5",
    stroke: "currentColor",
    strokeWidth: "1.5",
    strokeLinecap: "round",
    fill: "none"
  }))
};
function Alert({
  type = 'info',
  title,
  children,
  dismissible = false,
  onDismiss,
  size = 'md',
  className = ''
}) {
  const [visible, setVisible] = React.useState(true);
  if (!visible) return null;
  const dismiss = () => {
    setVisible(false);
    onDismiss && onDismiss();
  };
  return /*#__PURE__*/React.createElement("div", {
    className: ['ra-alert', `ra-alert--${type}`, size === 'sm' ? 'ra-alert--sm' : '', className].filter(Boolean).join(' '),
    role: "alert"
  }, /*#__PURE__*/React.createElement("span", {
    className: "ra-alert__icon"
  }, ICONS[type]), /*#__PURE__*/React.createElement("div", {
    className: "ra-alert__body"
  }, title && /*#__PURE__*/React.createElement("div", {
    className: "ra-alert__title"
  }, title), children && /*#__PURE__*/React.createElement("div", {
    className: "ra-alert__desc"
  }, children)), dismissible && /*#__PURE__*/React.createElement("span", {
    className: "ra-alert__close",
    onClick: dismiss,
    role: "button",
    "aria-label": "Dismiss"
  }, /*#__PURE__*/React.createElement("svg", {
    width: "14",
    height: "14",
    viewBox: "0 0 14 14",
    stroke: "currentColor",
    strokeWidth: "2",
    strokeLinecap: "round",
    fill: "none"
  }, /*#__PURE__*/React.createElement("line", {
    x1: "2",
    y1: "2",
    x2: "12",
    y2: "12"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "12",
    y1: "2",
    x2: "2",
    y2: "12"
  }))));
}
Object.assign(__ds_scope, { Alert });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Alert.jsx", error: String((e && e.message) || e) }); }

// components/feedback/ConfirmModal.jsx
try { (() => {
function ConfirmModal({
  open,
  onClose,
  onConfirm,
  title = 'Are you sure?',
  message,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  variant = 'danger',
  loading = false
}) {
  if (!open) return null;
  const {
    Modal
  } = window.RockAdmin || {};
  const {
    Button
  } = window.RockAdmin || {};
  return /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'fixed',
      inset: 0,
      background: 'rgba(0,0,0,0.45)',
      backdropFilter: 'blur(2px)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      zIndex: 'var(--z-modal)',
      padding: 'var(--space-4)'
    },
    onClick: e => e.target === e.currentTarget && onClose && onClose()
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      borderRadius: 'var(--radius-modal)',
      boxShadow: 'var(--shadow-modal)',
      width: '100%',
      maxWidth: '420px',
      padding: 'var(--space-6)',
      display: 'flex',
      flexDirection: 'column',
      gap: 'var(--space-4)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-3)',
      alignItems: 'flex-start'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 40,
      height: 40,
      borderRadius: '50%',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      flexShrink: 0,
      background: variant === 'danger' ? 'var(--color-error-50)' : 'var(--color-warning-50)'
    }
  }, /*#__PURE__*/React.createElement("svg", {
    width: "20",
    height: "20",
    viewBox: "0 0 20 20",
    fill: "none",
    stroke: variant === 'danger' ? 'var(--color-error-600)' : 'var(--color-warning-600)',
    strokeWidth: "1.5",
    strokeLinecap: "round",
    strokeLinejoin: "round"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M10 4v6M10 14.5v.5",
    strokeWidth: "2"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "10",
    cy: "10",
    r: "8.5"
  }))), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 'var(--font-semibold)',
      fontSize: 'var(--text-lg)',
      marginBottom: 'var(--space-1)',
      color: 'var(--text-primary)'
    }
  }, title), message && /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-md)',
      color: 'var(--text-secondary)',
      lineHeight: 'var(--leading-snug)'
    }
  }, message))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 'var(--space-3)',
      justifyContent: 'flex-end'
    }
  }, /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: onClose,
    style: {
      height: 34,
      padding: '0 14px',
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color-strong)',
      borderRadius: 'var(--radius-btn)',
      cursor: 'pointer',
      fontFamily: 'var(--font-sans)',
      fontSize: 'var(--text-base)',
      color: 'var(--text-primary)',
      fontWeight: 'var(--font-medium)',
      boxShadow: 'var(--shadow-xs)'
    }
  }, cancelLabel), /*#__PURE__*/React.createElement("button", {
    type: "button",
    onClick: onConfirm,
    disabled: loading,
    style: {
      height: 34,
      padding: '0 14px',
      background: variant === 'danger' ? 'var(--color-error-600)' : 'var(--color-warning-500)',
      border: 'none',
      borderRadius: 'var(--radius-btn)',
      cursor: 'pointer',
      color: 'white',
      fontFamily: 'var(--font-sans)',
      fontSize: 'var(--text-base)',
      fontWeight: 'var(--font-medium)',
      opacity: loading ? 0.6 : 1
    }
  }, loading ? 'Processing...' : confirmLabel))));
}
Object.assign(__ds_scope, { ConfirmModal });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/ConfirmModal.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Modal.jsx
try { (() => {
const CSS = `
.ra-modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45); backdrop-filter: blur(2px);
  display: flex; align-items: center; justify-content: center; padding: var(--space-4);
  z-index: var(--z-modal-backdrop); animation: ra-fade-in var(--duration-normal) var(--ease-out);
}
@keyframes ra-fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes ra-slide-up { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
.ra-modal {
  background: var(--bg-surface); border-radius: var(--radius-modal);
  box-shadow: var(--shadow-modal); width: 100%; position: relative;
  animation: ra-slide-up var(--duration-slow) var(--ease-out);
  display: flex; flex-direction: column; max-height: calc(100vh - 80px);
}
.ra-modal--sm  { max-width: 420px; }
.ra-modal--md  { max-width: 580px; }
.ra-modal--lg  { max-width: 780px; }
.ra-modal--xl  { max-width: 1000px; }
.ra-modal__header { display: flex; align-items: center; justify-content: space-between; padding: var(--space-5) var(--space-6) var(--space-4); border-bottom: 1px solid var(--border-color); flex-shrink: 0; }
.ra-modal__title { font-size: var(--text-xl); font-weight: var(--font-semibold); color: var(--text-primary); }
.ra-modal__close { cursor: pointer; color: var(--text-muted); padding: 4px; border-radius: var(--radius-sm); transition: var(--transition-colors); }
.ra-modal__close:hover { background: var(--color-neutral-100); color: var(--text-primary); }
.ra-modal__body { padding: var(--space-5) var(--space-6); overflow-y: auto; flex: 1; }
.ra-modal__footer { display: flex; align-items: center; justify-content: flex-end; gap: var(--space-3); padding: var(--space-4) var(--space-6) var(--space-5); border-top: 1px solid var(--border-color); flex-shrink: 0; }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-modal-css')) {
  const s = document.createElement('style');
  s.id = 'ra-modal-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function Modal({
  open,
  onClose,
  title,
  children,
  footer,
  size = 'md',
  className = ''
}) {
  React.useEffect(() => {
    if (open) {
      document.body.style.overflow = 'hidden';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [open]);
  if (!open) return null;
  return React.createPortal(/*#__PURE__*/React.createElement("div", {
    className: "ra-modal-overlay",
    onClick: e => e.target === e.currentTarget && onClose && onClose()
  }, /*#__PURE__*/React.createElement("div", {
    className: ['ra-modal', `ra-modal--${size}`, className].filter(Boolean).join(' '),
    role: "dialog",
    "aria-modal": "true"
  }, title !== undefined && /*#__PURE__*/React.createElement("div", {
    className: "ra-modal__header"
  }, /*#__PURE__*/React.createElement("h2", {
    className: "ra-modal__title"
  }, title), onClose && /*#__PURE__*/React.createElement("span", {
    className: "ra-modal__close",
    onClick: onClose,
    role: "button"
  }, /*#__PURE__*/React.createElement("svg", {
    width: "16",
    height: "16",
    viewBox: "0 0 16 16",
    stroke: "currentColor",
    strokeWidth: "2.5",
    strokeLinecap: "round",
    fill: "none"
  }, /*#__PURE__*/React.createElement("line", {
    x1: "3",
    y1: "3",
    x2: "13",
    y2: "13"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "13",
    y1: "3",
    x2: "3",
    y2: "13"
  })))), /*#__PURE__*/React.createElement("div", {
    className: "ra-modal__body"
  }, children), footer && /*#__PURE__*/React.createElement("div", {
    className: "ra-modal__footer"
  }, footer))), document.body);
}
Object.assign(__ds_scope, { Modal });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Modal.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Offcanvas.jsx
try { (() => {
const CSS = `
.ra-offcanvas-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(1px); z-index: var(--z-modal-backdrop); animation: ra-fade-in 150ms ease; }
.ra-offcanvas { position: fixed; top: 0; height: 100%; background: var(--bg-surface); box-shadow: var(--shadow-2xl); z-index: var(--z-modal); display: flex; flex-direction: column; transition: transform var(--duration-slow) var(--ease-out); }
.ra-offcanvas--right { right: 0; border-left: 1px solid var(--border-color); width: 420px; }
.ra-offcanvas--left  { left: 0;  border-right: 1px solid var(--border-color); width: 320px; }
.ra-offcanvas--sm { width: 320px; }
.ra-offcanvas--lg { width: 600px; }
.ra-offcanvas__header { display: flex; align-items: center; justify-content: space-between; padding: var(--space-5) var(--space-6) var(--space-4); border-bottom: 1px solid var(--border-color); flex-shrink: 0; }
.ra-offcanvas__title { font-size: var(--text-xl); font-weight: var(--font-semibold); }
.ra-offcanvas__close { cursor: pointer; color: var(--text-muted); padding: 4px; border-radius: var(--radius-sm); transition: var(--transition-colors); }
.ra-offcanvas__close:hover { background: var(--color-neutral-100); color: var(--text-primary); }
.ra-offcanvas__body { padding: var(--space-6); overflow-y: auto; flex: 1; }
.ra-offcanvas__footer { padding: var(--space-4) var(--space-6); border-top: 1px solid var(--border-color); display: flex; gap: var(--space-3); justify-content: flex-end; flex-shrink: 0; }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-offcanvas-css')) {
  const s = document.createElement('style');
  s.id = 'ra-offcanvas-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function Offcanvas({
  open,
  onClose,
  title,
  children,
  footer,
  side = 'right',
  size = 'md',
  className = ''
}) {
  React.useEffect(() => {
    if (open) document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = '';
    };
  }, [open]);
  if (!open) return null;
  const sizeClass = size !== 'md' ? `ra-offcanvas--${size}` : '';
  return React.createPortal(/*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
    className: "ra-offcanvas-overlay",
    onClick: onClose
  }), /*#__PURE__*/React.createElement("div", {
    className: ['ra-offcanvas', `ra-offcanvas--${side}`, sizeClass, className].filter(Boolean).join(' ')
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-offcanvas__header"
  }, /*#__PURE__*/React.createElement("h3", {
    className: "ra-offcanvas__title"
  }, title), /*#__PURE__*/React.createElement("span", {
    className: "ra-offcanvas__close",
    onClick: onClose
  }, /*#__PURE__*/React.createElement("svg", {
    width: "16",
    height: "16",
    viewBox: "0 0 16 16",
    stroke: "currentColor",
    strokeWidth: "2.5",
    strokeLinecap: "round",
    fill: "none"
  }, /*#__PURE__*/React.createElement("line", {
    x1: "3",
    y1: "3",
    x2: "13",
    y2: "13"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "13",
    y1: "3",
    x2: "3",
    y2: "13"
  })))), /*#__PURE__*/React.createElement("div", {
    className: "ra-offcanvas__body"
  }, children), footer && /*#__PURE__*/React.createElement("div", {
    className: "ra-offcanvas__footer"
  }, footer))), document.body);
}
Object.assign(__ds_scope, { Offcanvas });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Offcanvas.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Toast.jsx
try { (() => {
const CSS = `
.ra-toast-container { position: fixed; bottom: var(--space-6); right: var(--space-6); display: flex; flex-direction: column; gap: var(--space-2); z-index: var(--z-toast); pointer-events: none; }
.ra-toast { display: flex; align-items: flex-start; gap: var(--space-3); padding: var(--space-3) var(--space-4); background: var(--color-neutral-900); color: white; border-radius: var(--radius-lg); box-shadow: var(--shadow-xl); pointer-events: all; min-width: 280px; max-width: 420px; animation: ra-toast-in var(--duration-slow) var(--ease-bounce); font-size: var(--text-md); }
@keyframes ra-toast-in { from { opacity:0; transform: translateX(24px); } to { opacity:1; transform: none; } }
.ra-toast--success { background: var(--color-success-700); }
.ra-toast--error   { background: var(--color-error-700); }
.ra-toast--warning { background: #92400e; }
.ra-toast--info    { background: var(--color-info-700); }
.ra-toast__icon { flex-shrink: 0; margin-top: 1px; opacity: 0.9; }
.ra-toast__body { flex: 1; }
.ra-toast__title { font-weight: var(--font-semibold); line-height: var(--leading-tight); }
.ra-toast__desc { font-size: var(--text-base); opacity: 0.8; margin-top: 2px; }
.ra-toast__close { cursor: pointer; opacity: 0.6; flex-shrink: 0; padding: 2px; }
.ra-toast__close:hover { opacity: 1; }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-toast-css')) {
  const s = document.createElement('style');
  s.id = 'ra-toast-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
const CTX = React.createContext(null);
function ToastProvider({
  children
}) {
  const [toasts, setToasts] = React.useState([]);
  const add = React.useCallback(opts => {
    const id = Date.now() + Math.random();
    setToasts(t => [...t, {
      id,
      ...opts
    }]);
    setTimeout(() => setToasts(t => t.filter(x => x.id !== id)), opts.duration || 4000);
  }, []);
  return /*#__PURE__*/React.createElement(CTX.Provider, {
    value: add
  }, children, /*#__PURE__*/React.createElement("div", {
    className: "ra-toast-container"
  }, toasts.map(t => /*#__PURE__*/React.createElement("div", {
    key: t.id,
    className: ['ra-toast', t.type ? `ra-toast--${t.type}` : ''].join(' ')
  }, /*#__PURE__*/React.createElement("span", {
    className: "ra-toast__icon"
  }, t.type === 'success' && /*#__PURE__*/React.createElement("svg", {
    width: "16",
    height: "16",
    viewBox: "0 0 16 16",
    stroke: "white",
    strokeWidth: "2",
    strokeLinecap: "round",
    fill: "none"
  }, /*#__PURE__*/React.createElement("circle", {
    cx: "8",
    cy: "8",
    r: "7"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M5 8l2 2 4-4"
  })), t.type === 'error' && /*#__PURE__*/React.createElement("svg", {
    width: "16",
    height: "16",
    viewBox: "0 0 16 16",
    stroke: "white",
    strokeWidth: "2",
    strokeLinecap: "round",
    fill: "none"
  }, /*#__PURE__*/React.createElement("circle", {
    cx: "8",
    cy: "8",
    r: "7"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "5.5",
    y1: "5.5",
    x2: "10.5",
    y2: "10.5"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "10.5",
    y1: "5.5",
    x2: "5.5",
    y2: "10.5"
  })), (!t.type || t.type === 'info') && /*#__PURE__*/React.createElement("svg", {
    width: "16",
    height: "16",
    viewBox: "0 0 16 16",
    stroke: "white",
    strokeWidth: "2",
    strokeLinecap: "round",
    fill: "none"
  }, /*#__PURE__*/React.createElement("circle", {
    cx: "8",
    cy: "8",
    r: "7"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "8",
    y1: "5",
    x2: "8",
    y2: "9"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "8",
    cy: "11.5",
    r: ".5",
    fill: "white"
  }))), /*#__PURE__*/React.createElement("div", {
    className: "ra-toast__body"
  }, t.title && /*#__PURE__*/React.createElement("div", {
    className: "ra-toast__title"
  }, t.title), t.message && /*#__PURE__*/React.createElement("div", {
    className: "ra-toast__desc"
  }, t.message), !t.title && !t.message && /*#__PURE__*/React.createElement("div", {
    className: "ra-toast__title"
  }, t)), /*#__PURE__*/React.createElement("span", {
    className: "ra-toast__close",
    onClick: () => setToasts(ts => ts.filter(x => x.id !== t.id))
  }, /*#__PURE__*/React.createElement("svg", {
    width: "14",
    height: "14",
    viewBox: "0 0 14 14",
    stroke: "white",
    strokeWidth: "2",
    strokeLinecap: "round",
    fill: "none"
  }, /*#__PURE__*/React.createElement("line", {
    x1: "2",
    y1: "2",
    x2: "12",
    y2: "12"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "12",
    y1: "2",
    x2: "2",
    y2: "12"
  })))))));
}
function useToast() {
  return React.useContext(CTX);
}

/** Individual toast item — rendered internally by ToastProvider. Export satisfies bundler. */
function Toast({
  title,
  message,
  type
}) {
  const cls = ['ra-toast', type ? `ra-toast--${type}` : ''].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("div", {
    className: cls
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-toast__body"
  }, title && /*#__PURE__*/React.createElement("div", {
    className: "ra-toast__title"
  }, title), message && /*#__PURE__*/React.createElement("div", {
    className: "ra-toast__desc"
  }, message)));
}
Object.assign(__ds_scope, { ToastProvider, useToast, Toast });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Toast.jsx", error: String((e && e.message) || e) }); }

// components/forms/Checkbox.jsx
try { (() => {
const CSS = `
.ra-checkbox-label { display: inline-flex; align-items: flex-start; gap: var(--space-2); cursor: pointer; user-select: none; font-size: var(--text-md); color: var(--text-primary); }
.ra-checkbox-label--disabled { cursor: not-allowed; opacity: 0.5; }
.ra-checkbox-input { position: absolute; opacity: 0; width: 0; height: 0; }
.ra-checkbox-box {
  width: 16px; height: 16px; border: 1.5px solid var(--border-color-strong);
  border-radius: var(--radius-sm); background: var(--bg-surface); flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  transition: var(--transition-colors); margin-top: 1px;
}
.ra-checkbox-input:focus-visible + .ra-checkbox-box { box-shadow: var(--shadow-focus); }
.ra-checkbox-input:checked + .ra-checkbox-box { background: var(--color-primary-600); border-color: var(--color-primary-600); }
.ra-checkbox-input:indeterminate + .ra-checkbox-box { background: var(--color-primary-600); border-color: var(--color-primary-600); }
.ra-checkbox-text { line-height: var(--leading-snug); }
.ra-checkbox-desc { font-size: var(--text-sm); color: var(--text-muted); margin-top: 1px; }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-checkbox-css')) {
  const s = document.createElement('style');
  s.id = 'ra-checkbox-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
const CheckIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "10",
  height: "10",
  viewBox: "0 0 12 12",
  fill: "none",
  stroke: "white",
  strokeWidth: "2.5",
  strokeLinecap: "round",
  strokeLinejoin: "round"
}, /*#__PURE__*/React.createElement("polyline", {
  points: "2,6 5,9 10,3"
}));
const IndeterminateIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "8",
  height: "2",
  viewBox: "0 0 8 2"
}, /*#__PURE__*/React.createElement("rect", {
  width: "8",
  height: "2",
  rx: "1",
  fill: "white"
}));
function Checkbox({
  checked,
  defaultChecked,
  indeterminate = false,
  onChange,
  disabled,
  label,
  description,
  id,
  name,
  value,
  className = ''
}) {
  const inputRef = React.useRef(null);
  React.useEffect(() => {
    if (inputRef.current) inputRef.current.indeterminate = indeterminate;
  }, [indeterminate]);
  return /*#__PURE__*/React.createElement("label", {
    className: ['ra-checkbox-label', disabled ? 'ra-checkbox-label--disabled' : '', className].filter(Boolean).join(' ')
  }, /*#__PURE__*/React.createElement("input", {
    ref: inputRef,
    className: "ra-checkbox-input",
    type: "checkbox",
    checked: checked,
    defaultChecked: defaultChecked,
    onChange: onChange,
    disabled: disabled,
    id: id,
    name: name,
    value: value
  }), /*#__PURE__*/React.createElement("span", {
    className: "ra-checkbox-box"
  }, indeterminate ? /*#__PURE__*/React.createElement(IndeterminateIcon, null) : checked ? /*#__PURE__*/React.createElement(CheckIcon, null) : null), (label || description) && /*#__PURE__*/React.createElement("span", null, label && /*#__PURE__*/React.createElement("span", {
    className: "ra-checkbox-text"
  }, label), description && /*#__PURE__*/React.createElement("div", {
    className: "ra-checkbox-desc"
  }, description)));
}
Object.assign(__ds_scope, { Checkbox });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Checkbox.jsx", error: String((e && e.message) || e) }); }

// components/forms/FormField.jsx
try { (() => {
const CSS = `
.ra-field { display: flex; flex-direction: column; gap: var(--space-1-5); }
.ra-field__label {
  font-size: var(--text-base); font-weight: var(--font-medium);
  color: var(--text-primary); display: flex; align-items: center; gap: var(--space-1-5);
}
.ra-field__required { color: var(--color-error-500); font-size: var(--text-md); line-height: 1; }
.ra-field__hint { font-size: var(--text-sm); color: var(--text-muted); line-height: var(--leading-snug); }
.ra-field__error { font-size: var(--text-sm); color: var(--color-error-600); display: flex; align-items: center; gap: var(--space-1); }
.ra-field__help-link { color: var(--text-link); cursor: pointer; text-decoration: underline; font-size: var(--text-sm); }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-field-css')) {
  const s = document.createElement('style');
  s.id = 'ra-field-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function FormField({
  label,
  required,
  hint,
  error,
  onHintLinkClick,
  hintLinkLabel,
  children,
  className = ''
}) {
  return /*#__PURE__*/React.createElement("div", {
    className: `ra-field ${className}`
  }, label && /*#__PURE__*/React.createElement("label", {
    className: "ra-field__label"
  }, /*#__PURE__*/React.createElement("span", null, label), required && /*#__PURE__*/React.createElement("span", {
    className: "ra-field__required"
  }, "*"), hintLinkLabel && /*#__PURE__*/React.createElement("span", {
    className: "ra-field__help-link",
    onClick: onHintLinkClick,
    role: "button",
    tabIndex: 0
  }, hintLinkLabel)), children, hint && !error && /*#__PURE__*/React.createElement("span", {
    className: "ra-field__hint"
  }, hint), error && /*#__PURE__*/React.createElement("span", {
    className: "ra-field__error"
  }, /*#__PURE__*/React.createElement("svg", {
    width: "12",
    height: "12",
    viewBox: "0 0 16 16",
    fill: "var(--color-error-500)"
  }, /*#__PURE__*/React.createElement("circle", {
    cx: "8",
    cy: "8",
    r: "8",
    opacity: ".15"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M7.25 4.5h1.5v5h-1.5V4.5zm0 6h1.5v1.5h-1.5V10.5z",
    fill: "var(--color-error-600)"
  })), error));
}
Object.assign(__ds_scope, { FormField });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/FormField.jsx", error: String((e && e.message) || e) }); }

// components/forms/Input.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const CSS = `
.ra-input-wrap { position: relative; display: flex; align-items: center; }
.ra-input {
  width: 100%; font-family: var(--font-sans); font-size: var(--text-md);
  color: var(--text-primary); background: var(--bg-surface);
  border: 1px solid var(--border-color-strong); border-radius: var(--radius-input);
  transition: var(--transition-colors); outline: none;
  height: 34px; padding: 0 var(--space-3);
}
.ra-input::placeholder { color: var(--text-muted); }
.ra-input:hover:not(:disabled):not(:read-only) { border-color: var(--color-neutral-400); }
.ra-input:focus { border-color: var(--border-focus); box-shadow: var(--shadow-focus); }
.ra-input:disabled { background: var(--color-neutral-50); color: var(--text-muted); cursor: not-allowed; }
.ra-input:read-only { background: var(--color-neutral-50); }
.ra-input--error { border-color: var(--color-error-500) !important; }
.ra-input--error:focus { box-shadow: var(--shadow-focus-error) !important; }
.ra-input--success { border-color: var(--color-success-500) !important; }
.ra-input--lg { height: 40px; font-size: var(--text-lg); padding: 0 var(--space-4); }
.ra-input--sm { height: 28px; font-size: var(--text-sm); padding: 0 var(--space-2-5); }
.ra-input--has-left  { padding-left: 34px; }
.ra-input--has-right { padding-right: 34px; }
.ra-input-icon { position: absolute; display: flex; align-items: center; color: var(--text-muted); pointer-events: none; }
.ra-input-icon--left  { left: 10px; }
.ra-input-icon--right { right: 10px; }
.ra-input-icon--clickable { pointer-events: auto; cursor: pointer; }
.ra-input-icon--clickable:hover { color: var(--text-primary); }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-input-css')) {
  const s = document.createElement('style');
  s.id = 'ra-input-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function Input({
  type = 'text',
  placeholder,
  value,
  defaultValue,
  onChange,
  onBlur,
  onFocus,
  disabled = false,
  readOnly = false,
  error = false,
  success = false,
  size = 'md',
  leftIcon,
  rightIcon,
  onRightIconClick,
  className = '',
  style,
  id,
  name,
  autoComplete,
  autoFocus,
  ...props
}) {
  const cls = ['ra-input', size !== 'md' ? `ra-input--${size}` : '', error ? 'ra-input--error' : '', success ? 'ra-input--success' : '', leftIcon ? 'ra-input--has-left' : '', rightIcon ? 'ra-input--has-right' : '', className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("div", {
    className: "ra-input-wrap",
    style: style
  }, leftIcon && /*#__PURE__*/React.createElement("span", {
    className: "ra-input-icon ra-input-icon--left"
  }, leftIcon), /*#__PURE__*/React.createElement("input", _extends({
    className: cls,
    type: type,
    placeholder: placeholder,
    value: value,
    defaultValue: defaultValue,
    onChange: onChange,
    onBlur: onBlur,
    onFocus: onFocus,
    disabled: disabled,
    readOnly: readOnly,
    id: id,
    name: name,
    autoComplete: autoComplete,
    autoFocus: autoFocus
  }, props)), rightIcon && /*#__PURE__*/React.createElement("span", {
    className: `ra-input-icon ra-input-icon--right ${onRightIconClick ? 'ra-input-icon--clickable' : ''}`,
    onClick: onRightIconClick
  }, rightIcon));
}
Object.assign(__ds_scope, { Input });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Input.jsx", error: String((e && e.message) || e) }); }

// components/forms/Radio.jsx
try { (() => {
const CSS = `
.ra-radio-label { display: inline-flex; align-items: flex-start; gap: var(--space-2); cursor: pointer; user-select: none; font-size: var(--text-md); color: var(--text-primary); }
.ra-radio-label--disabled { cursor: not-allowed; opacity: 0.5; }
.ra-radio-input { position: absolute; opacity: 0; width: 0; height: 0; }
.ra-radio-dot {
  width: 16px; height: 16px; border-radius: 50%; border: 1.5px solid var(--border-color-strong);
  background: var(--bg-surface); flex-shrink: 0; display: flex; align-items: center; justify-content: center;
  transition: var(--transition-colors); margin-top: 1px;
}
.ra-radio-input:focus-visible + .ra-radio-dot { box-shadow: var(--shadow-focus); }
.ra-radio-input:checked + .ra-radio-dot { border-color: var(--color-primary-600); background: var(--color-primary-600); }
.ra-radio-dot::after { content: ''; width: 6px; height: 6px; border-radius: 50%; background: white; opacity: 0; transition: opacity 100ms; }
.ra-radio-input:checked + .ra-radio-dot::after { opacity: 1; }
.ra-radio-group { display: flex; flex-direction: column; gap: var(--space-2-5); }
.ra-radio-group--horizontal { flex-direction: row; flex-wrap: wrap; gap: var(--space-4); }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-radio-css')) {
  const s = document.createElement('style');
  s.id = 'ra-radio-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function Radio({
  checked,
  onChange,
  disabled,
  label,
  description,
  id,
  name,
  value,
  className = ''
}) {
  return /*#__PURE__*/React.createElement("label", {
    className: ['ra-radio-label', disabled ? 'ra-radio-label--disabled' : '', className].filter(Boolean).join(' ')
  }, /*#__PURE__*/React.createElement("input", {
    className: "ra-radio-input",
    type: "radio",
    checked: checked,
    onChange: onChange,
    disabled: disabled,
    id: id,
    name: name,
    value: value
  }), /*#__PURE__*/React.createElement("span", {
    className: "ra-radio-dot"
  }), (label || description) && /*#__PURE__*/React.createElement("span", null, label && /*#__PURE__*/React.createElement("span", null, label), description && /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--text-muted)',
      marginTop: '1px'
    }
  }, description)));
}
function RadioGroup({
  children,
  horizontal = false,
  className = ''
}) {
  return /*#__PURE__*/React.createElement("div", {
    className: ['ra-radio-group', horizontal ? 'ra-radio-group--horizontal' : '', className].filter(Boolean).join(' ')
  }, children);
}
Object.assign(__ds_scope, { Radio, RadioGroup });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Radio.jsx", error: String((e && e.message) || e) }); }

// components/forms/SearchSelect.jsx
try { (() => {
const CSS = `
.ra-ss { position: relative; width: 100%; }
.ra-ss__trigger {
  width: 100%; min-height: 34px; padding: 4px var(--space-8) 4px var(--space-3);
  font-family: var(--font-sans); font-size: var(--text-md); background: var(--bg-surface);
  border: 1px solid var(--border-color-strong); border-radius: var(--radius-input);
  cursor: pointer; text-align: left; transition: var(--transition-colors); outline: none;
  display: flex; align-items: center; flex-wrap: wrap; gap: 4px; color: var(--text-primary);
}
.ra-ss__trigger:hover { border-color: var(--color-neutral-400); }
.ra-ss__trigger:focus, .ra-ss--open .ra-ss__trigger { border-color: var(--border-focus); box-shadow: var(--shadow-focus); }
.ra-ss__placeholder { color: var(--text-muted); }
.ra-ss__caret { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
.ra-ss__dropdown { position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: var(--z-dropdown); background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-dropdown); box-shadow: var(--shadow-dropdown); overflow: hidden; }
.ra-ss__search { padding: var(--space-2); border-bottom: 1px solid var(--border-color); }
.ra-ss__search input { width: 100%; height: 30px; padding: 0 var(--space-3); border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-size: var(--text-sm); outline: none; }
.ra-ss__search input:focus { border-color: var(--border-focus); }
.ra-ss__list { max-height: 220px; overflow-y: auto; }
.ra-ss__option { padding: var(--space-2) var(--space-3); font-size: var(--text-md); cursor: pointer; display: flex; align-items: center; gap: var(--space-2); color: var(--text-primary); }
.ra-ss__option:hover { background: var(--bg-hover); }
.ra-ss__option--selected { color: var(--color-primary-600); font-weight: var(--font-medium); }
.ra-ss__option--selected::after { content: '✓'; margin-left: auto; font-size: var(--text-sm); }
.ra-ss__empty { padding: var(--space-4); text-align: center; color: var(--text-muted); font-size: var(--text-sm); }
.ra-ss__tag { display: inline-flex; align-items: center; gap: 3px; padding: 1px 6px 1px 8px; background: var(--color-primary-100); color: var(--color-primary-800); border-radius: var(--radius-full); font-size: var(--text-xs); white-space: nowrap; }
.ra-ss__tag-remove { cursor: pointer; color: var(--color-primary-600); line-height: 1; padding: 0 1px; font-size: 12px; }
.ra-ss__tag-remove:hover { color: var(--color-primary-900); }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-ss-css')) {
  const s = document.createElement('style');
  s.id = 'ra-ss-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
const Caret = () => /*#__PURE__*/React.createElement("svg", {
  width: "14",
  height: "14",
  viewBox: "0 0 16 16",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("polyline", {
  points: "4,6 8,10 12,6"
}));
function SearchSelect({
  options = [],
  value,
  onChange,
  placeholder = 'Select...',
  multi = false,
  disabled,
  error,
  className = '',
  style
}) {
  const [open, setOpen] = React.useState(false);
  const [search, setSearch] = React.useState('');
  const ref = React.useRef(null);
  const selected = multi ? value || [] : value ? [value] : [];
  const filtered = options.filter(o => {
    const l = typeof o === 'object' ? o.label : o;
    return l.toLowerCase().includes(search.toLowerCase());
  });
  React.useEffect(() => {
    const handler = e => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);
  const toggle = val => {
    if (multi) {
      const next = selected.includes(val) ? selected.filter(v => v !== val) : [...selected, val];
      onChange && onChange(next);
    } else {
      onChange && onChange(val);
      setOpen(false);
    }
  };
  const getLabel = val => {
    const o = options.find(o => (typeof o === 'object' ? o.value : o) === val);
    return o ? typeof o === 'object' ? o.label : o : val;
  };
  return /*#__PURE__*/React.createElement("div", {
    ref: ref,
    className: ['ra-ss', open ? 'ra-ss--open' : '', className].filter(Boolean).join(' '),
    style: style
  }, /*#__PURE__*/React.createElement("button", {
    type: "button",
    className: "ra-ss__trigger",
    onClick: () => !disabled && setOpen(v => !v),
    disabled: disabled
  }, selected.length === 0 && /*#__PURE__*/React.createElement("span", {
    className: "ra-ss__placeholder"
  }, placeholder), multi ? selected.map(v => /*#__PURE__*/React.createElement("span", {
    key: v,
    className: "ra-ss__tag"
  }, getLabel(v), /*#__PURE__*/React.createElement("span", {
    className: "ra-ss__tag-remove",
    onClick: e => {
      e.stopPropagation();
      toggle(v);
    }
  }, "\xD7"))) : selected.length > 0 && /*#__PURE__*/React.createElement("span", null, getLabel(selected[0])), /*#__PURE__*/React.createElement("span", {
    className: "ra-ss__caret"
  }, /*#__PURE__*/React.createElement(Caret, null))), open && /*#__PURE__*/React.createElement("div", {
    className: "ra-ss__dropdown"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-ss__search"
  }, /*#__PURE__*/React.createElement("input", {
    autoFocus: true,
    placeholder: "Search...",
    value: search,
    onChange: e => setSearch(e.target.value)
  })), /*#__PURE__*/React.createElement("div", {
    className: "ra-ss__list"
  }, filtered.length === 0 ? /*#__PURE__*/React.createElement("div", {
    className: "ra-ss__empty"
  }, "No options") : filtered.map(o => {
    const v = typeof o === 'object' ? o.value : o;
    const l = typeof o === 'object' ? o.label : o;
    return /*#__PURE__*/React.createElement("div", {
      key: v,
      className: ['ra-ss__option', selected.includes(v) ? 'ra-ss__option--selected' : ''].join(' '),
      onClick: () => toggle(v)
    }, l);
  }))));
}
Object.assign(__ds_scope, { SearchSelect });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/SearchSelect.jsx", error: String((e && e.message) || e) }); }

// components/forms/Select.jsx
try { (() => {
const CSS = `
.ra-select-wrap { position: relative; display: flex; }
.ra-select {
  width: 100%; font-family: var(--font-sans); font-size: var(--text-md);
  color: var(--text-primary); background: var(--bg-surface);
  border: 1px solid var(--border-color-strong); border-radius: var(--radius-input);
  transition: var(--transition-colors); outline: none; cursor: pointer;
  height: 34px; padding: 0 var(--space-8) 0 var(--space-3);
  appearance: none; -webkit-appearance: none;
}
.ra-select:hover:not(:disabled) { border-color: var(--color-neutral-400); }
.ra-select:focus { border-color: var(--border-focus); box-shadow: var(--shadow-focus); }
.ra-select:disabled { background: var(--color-neutral-50); color: var(--text-muted); cursor: not-allowed; }
.ra-select--error { border-color: var(--color-error-500) !important; }
.ra-select--sm { height: 28px; font-size: var(--text-sm); }
.ra-select--lg { height: 40px; font-size: var(--text-lg); }
.ra-select-caret {
  position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
  pointer-events: none; color: var(--text-muted);
}
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-select-css')) {
  const s = document.createElement('style');
  s.id = 'ra-select-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
const Caret = () => /*#__PURE__*/React.createElement("svg", {
  width: "14",
  height: "14",
  viewBox: "0 0 16 16",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round",
  strokeLinejoin: "round"
}, /*#__PURE__*/React.createElement("polyline", {
  points: "4,6 8,10 12,6"
}));
function Select({
  options = [],
  value,
  defaultValue,
  onChange,
  disabled,
  error,
  size = 'md',
  placeholder,
  className = '',
  style,
  id,
  name
}) {
  const cls = ['ra-select', size !== 'md' ? `ra-select--${size}` : '', error ? 'ra-select--error' : '', className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("div", {
    className: "ra-select-wrap",
    style: style
  }, /*#__PURE__*/React.createElement("select", {
    className: cls,
    value: value,
    defaultValue: defaultValue,
    onChange: onChange,
    disabled: disabled,
    id: id,
    name: name
  }, placeholder && /*#__PURE__*/React.createElement("option", {
    value: "",
    disabled: true
  }, placeholder), options.map(opt => {
    const v = typeof opt === 'object' ? opt.value : opt;
    const l = typeof opt === 'object' ? opt.label : opt;
    return /*#__PURE__*/React.createElement("option", {
      key: v,
      value: v
    }, l);
  })), /*#__PURE__*/React.createElement("span", {
    className: "ra-select-caret"
  }, /*#__PURE__*/React.createElement(Caret, null)));
}
Object.assign(__ds_scope, { Select });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Select.jsx", error: String((e && e.message) || e) }); }

// components/forms/Switch.jsx
try { (() => {
const CSS = `
.ra-switch-label { display: inline-flex; align-items: center; gap: var(--space-2-5); cursor: pointer; user-select: none; }
.ra-switch-label--disabled { cursor: not-allowed; opacity: 0.5; }
.ra-switch-input { position: absolute; opacity: 0; width: 0; height: 0; }
.ra-switch-track {
  width: 36px; height: 20px; border-radius: var(--radius-full);
  background: var(--color-neutral-300); transition: background 150ms ease;
  flex-shrink: 0; position: relative;
}
.ra-switch-input:focus-visible ~ .ra-switch-track { box-shadow: var(--shadow-focus); }
.ra-switch-input:checked ~ .ra-switch-track { background: var(--color-primary-600); }
.ra-switch-thumb {
  position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%;
  background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.2); transition: transform 150ms ease;
}
.ra-switch-input:checked ~ .ra-switch-track .ra-switch-thumb { transform: translateX(16px); }
.ra-switch-text { font-size: var(--text-md); color: var(--text-primary); }
.ra-switch--sm .ra-switch-track { width: 28px; height: 16px; }
.ra-switch--sm .ra-switch-thumb { width: 12px; height: 12px; }
.ra-switch--sm .ra-switch-input:checked ~ .ra-switch-track .ra-switch-thumb { transform: translateX(12px); }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-switch-css')) {
  const s = document.createElement('style');
  s.id = 'ra-switch-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function Switch({
  checked,
  defaultChecked,
  onChange,
  disabled,
  label,
  size = 'md',
  id,
  name,
  className = ''
}) {
  return /*#__PURE__*/React.createElement("label", {
    className: ['ra-switch-label', disabled ? 'ra-switch-label--disabled' : '', size !== 'md' ? `ra-switch--${size}` : '', className].filter(Boolean).join(' ')
  }, /*#__PURE__*/React.createElement("input", {
    className: "ra-switch-input",
    type: "checkbox",
    role: "switch",
    checked: checked,
    defaultChecked: defaultChecked,
    onChange: onChange,
    disabled: disabled,
    id: id,
    name: name
  }), /*#__PURE__*/React.createElement("span", {
    className: "ra-switch-track"
  }, /*#__PURE__*/React.createElement("span", {
    className: "ra-switch-thumb"
  })), label && /*#__PURE__*/React.createElement("span", {
    className: "ra-switch-text"
  }, label));
}
Object.assign(__ds_scope, { Switch });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Switch.jsx", error: String((e && e.message) || e) }); }

// components/forms/Textarea.jsx
try { (() => {
const CSS = `
.ra-textarea {
  width: 100%; font-family: var(--font-sans); font-size: var(--text-md);
  color: var(--text-primary); background: var(--bg-surface);
  border: 1px solid var(--border-color-strong); border-radius: var(--radius-input);
  transition: var(--transition-colors); outline: none; resize: vertical;
  padding: var(--space-2-5) var(--space-3); line-height: var(--leading-normal);
  min-height: 80px;
}
.ra-textarea::placeholder { color: var(--text-muted); }
.ra-textarea:hover:not(:disabled) { border-color: var(--color-neutral-400); }
.ra-textarea:focus { border-color: var(--border-focus); box-shadow: var(--shadow-focus); }
.ra-textarea:disabled { background: var(--color-neutral-50); color: var(--text-muted); cursor: not-allowed; resize: none; }
.ra-textarea--error { border-color: var(--color-error-500) !important; }
.ra-textarea--error:focus { box-shadow: var(--shadow-focus-error) !important; }
.ra-textarea--sm { font-size: var(--text-base); min-height: 60px; }
.ra-textarea--lg { min-height: 140px; }
.ra-textarea--auto { resize: none; overflow: hidden; }
.ra-textarea-footer { display: flex; align-items: center; justify-content: space-between; margin-top: var(--space-1-5); }
.ra-textarea-placeholders { display: flex; flex-wrap: wrap; gap: var(--space-1); }
.ra-textarea-chip { font-size: var(--text-xs); padding: 2px 8px; border-radius: var(--radius-full); background: var(--color-neutral-100); color: var(--text-secondary); cursor: pointer; border: 1px solid var(--border-color); transition: var(--transition-colors); }
.ra-textarea-chip:hover { background: var(--color-primary-50); border-color: var(--color-primary-300); color: var(--color-primary-700); }
.ra-textarea-count { font-size: var(--text-xs); color: var(--text-muted); }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-textarea-css')) {
  const s = document.createElement('style');
  s.id = 'ra-textarea-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function Textarea({
  value,
  defaultValue,
  onChange,
  placeholder,
  disabled,
  error,
  size = 'md',
  rows,
  autoResize = false,
  placeholders = [],
  maxLength,
  className = '',
  style,
  id,
  name
}) {
  const ref = React.useRef(null);
  const handleChange = e => {
    if (autoResize && ref.current) {
      ref.current.style.height = 'auto';
      ref.current.style.height = ref.current.scrollHeight + 'px';
    }
    onChange && onChange(e);
  };
  const insertPlaceholder = ph => {
    if (!ref.current) return;
    const start = ref.current.selectionStart;
    const end = ref.current.selectionEnd;
    const val = ref.current.value;
    const newVal = val.slice(0, start) + ph + val.slice(end);
    const nativeInput = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value');
    nativeInput.set.call(ref.current, newVal);
    ref.current.dispatchEvent(new Event('input', {
      bubbles: true
    }));
    onChange && onChange({
      target: ref.current
    });
  };
  const cls = ['ra-textarea', size !== 'md' ? `ra-textarea--${size}` : '', error ? 'ra-textarea--error' : '', autoResize ? 'ra-textarea--auto' : '', className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("textarea", {
    ref: ref,
    className: cls,
    value: value,
    defaultValue: defaultValue,
    onChange: handleChange,
    placeholder: placeholder,
    disabled: disabled,
    rows: rows,
    maxLength: maxLength,
    id: id,
    name: name,
    style: style
  }), (placeholders.length > 0 || maxLength) && /*#__PURE__*/React.createElement("div", {
    className: "ra-textarea-footer"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-textarea-placeholders"
  }, placeholders.map(p => /*#__PURE__*/React.createElement("span", {
    key: p.value || p,
    className: "ra-textarea-chip",
    onClick: () => insertPlaceholder(p.value || p)
  }, p.label || p))), maxLength && /*#__PURE__*/React.createElement("span", {
    className: "ra-textarea-count"
  }, (value || '').length, "/", maxLength)));
}
Object.assign(__ds_scope, { Textarea });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Textarea.jsx", error: String((e && e.message) || e) }); }

// components/layout/Card.jsx
try { (() => {
const CSS = `
.ra-card { background: var(--bg-surface); border-radius: var(--radius-card); border: 1px solid var(--border-color); box-shadow: var(--shadow-card); overflow: hidden; }
.ra-card--flat { box-shadow: none; }
.ra-card--hoverable { transition: box-shadow var(--duration-normal) var(--ease-default), border-color var(--duration-normal) var(--ease-default); cursor: pointer; }
.ra-card--hoverable:hover { box-shadow: var(--shadow-md); border-color: var(--color-neutral-300); }
.ra-card__header { display: flex; align-items: flex-start; justify-content: space-between; padding: var(--space-4) var(--card-padding); border-bottom: 1px solid var(--border-color); gap: var(--space-4); }
.ra-card__header--no-border { border-bottom: none; padding-bottom: 0; }
.ra-card__title { font-size: var(--text-lg); font-weight: var(--font-semibold); color: var(--text-primary); }
.ra-card__subtitle { font-size: var(--text-sm); color: var(--text-muted); margin-top: 2px; }
.ra-card__actions { display: flex; align-items: center; gap: var(--space-2); flex-shrink: 0; }
.ra-card__body { padding: var(--card-padding); }
.ra-card__body--flush { padding: 0; }
.ra-card__footer { padding: var(--space-3) var(--card-padding); border-top: 1px solid var(--border-color); background: var(--color-neutral-50); }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-card-css')) {
  const s = document.createElement('style');
  s.id = 'ra-card-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function Card({
  children,
  title,
  subtitle,
  actions,
  footer,
  flat = false,
  hoverable = false,
  flush = false,
  noBorder = false,
  className = '',
  style,
  onClick
}) {
  const cls = ['ra-card', flat ? 'ra-card--flat' : '', hoverable ? 'ra-card--hoverable' : '', className].filter(Boolean).join(' ');
  return /*#__PURE__*/React.createElement("div", {
    className: cls,
    style: style,
    onClick: onClick
  }, (title || actions) && /*#__PURE__*/React.createElement("div", {
    className: ['ra-card__header', noBorder ? 'ra-card__header--no-border' : ''].join(' ')
  }, /*#__PURE__*/React.createElement("div", null, title && /*#__PURE__*/React.createElement("div", {
    className: "ra-card__title"
  }, title), subtitle && /*#__PURE__*/React.createElement("div", {
    className: "ra-card__subtitle"
  }, subtitle)), actions && /*#__PURE__*/React.createElement("div", {
    className: "ra-card__actions"
  }, actions)), /*#__PURE__*/React.createElement("div", {
    className: ['ra-card__body', flush ? 'ra-card__body--flush' : ''].join(' ')
  }, children), footer && /*#__PURE__*/React.createElement("div", {
    className: "ra-card__footer"
  }, footer));
}
Object.assign(__ds_scope, { Card });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/Card.jsx", error: String((e && e.message) || e) }); }

// components/layout/Footer.jsx
try { (() => {
const CSS = `
.ra-footer {
  width: 100%; background: var(--bg-surface);
  border-top: 1px solid var(--border-color);
  flex-shrink: 0;
}
.ra-footer__inner {
  max-width: var(--content-max-width); margin: 0 auto;
  padding: 0 var(--page-padding-x);
  height: 44px; display: flex; align-items: center;
  justify-content: space-between; gap: var(--space-4);
}
.ra-footer__copy { font-size: var(--text-sm); color: var(--text-muted); }
.ra-footer__links-btn {
  display: inline-flex; align-items: center; gap: var(--space-1-5);
  height: 28px; padding: 0 var(--space-3);
  font-family: var(--font-sans); font-size: var(--text-sm);
  color: var(--text-secondary); background: transparent;
  border: 1px solid var(--border-color); border-radius: var(--radius-md);
  cursor: pointer; transition: var(--transition-colors);
  font-weight: var(--font-medium);
}
.ra-footer__links-btn:hover { background: var(--bg-hover); color: var(--text-primary); border-color: var(--border-color-strong); }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-footer-css')) {
  const s = document.createElement('style');
  s.id = 'ra-footer-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
const ChevronDown = () => /*#__PURE__*/React.createElement("svg", {
  width: "11",
  height: "11",
  viewBox: "0 0 12 12",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("polyline", {
  points: "2,4 6,8 10,4"
}));
function Footer({
  copyright = 'Rock Admin',
  usefulLinks = [],
  year
}) {
  const y = year || new Date().getFullYear();
  const {
    Dropdown
  } = typeof window !== 'undefined' && window.RockAdminDesignSystem_fced52 || {};
  const defaultLinks = usefulLinks.length ? usefulLinks : [{
    label: 'Documentation'
  }, {
    label: 'Changelog'
  }, {
    divider: true
  }, {
    label: 'Support'
  }, {
    label: 'Privacy Policy'
  }];
  const trigger = /*#__PURE__*/React.createElement("button", {
    className: "ra-footer__links-btn",
    type: "button"
  }, "Useful Links ", /*#__PURE__*/React.createElement(ChevronDown, null));
  return /*#__PURE__*/React.createElement("footer", {
    className: "ra-footer"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-footer__inner"
  }, /*#__PURE__*/React.createElement("span", {
    className: "ra-footer__copy"
  }, "\xA9 ", y, " ", copyright), Dropdown ? /*#__PURE__*/React.createElement(Dropdown, {
    trigger: trigger,
    items: defaultLinks,
    align: "right"
  }) : trigger));
}
Object.assign(__ds_scope, { Footer });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/Footer.jsx", error: String((e && e.message) || e) }); }

// components/layout/InpageSidebar.jsx
try { (() => {
const CSS = `
.ra-inpage-layout { display: flex; gap: 0; flex: 1; min-height: 0; }
.ra-inpage-sidebar { width: var(--inpage-sidebar-width); flex-shrink: 0; border-right: 1px solid var(--border-color); background: var(--bg-surface); padding: var(--space-4) 0; }
.ra-inpage-sidebar__section { margin-bottom: var(--space-5); }
.ra-inpage-sidebar__section-label { font-size: var(--text-xs); font-weight: var(--font-semibold); color: var(--text-muted); text-transform: uppercase; letter-spacing: var(--tracking-wider); padding: 0 var(--space-4) var(--space-1-5); }
.ra-inpage-sidebar__item { display: flex; align-items: center; gap: var(--space-2-5); padding: var(--space-2) var(--space-4); font-size: var(--text-base); color: var(--text-secondary); cursor: pointer; transition: var(--transition-colors); border-left: 2px solid transparent; text-decoration: none; }
.ra-inpage-sidebar__item:hover { background: var(--bg-hover); color: var(--text-primary); }
.ra-inpage-sidebar__item--active { color: var(--color-primary-700); background: var(--color-primary-50); border-left-color: var(--color-primary-600); font-weight: var(--font-medium); }
.ra-inpage-sidebar__item-icon { width: 16px; height: 16px; flex-shrink: 0; opacity: 0.7; }
.ra-inpage-content { flex: 1; overflow-y: auto; padding: var(--space-6); min-width: 0; }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-inpage-css')) {
  const s = document.createElement('style');
  s.id = 'ra-inpage-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function InpageSidebar({
  sections = [],
  activeId,
  onSelect,
  className = ''
}) {
  return /*#__PURE__*/React.createElement("nav", {
    className: ['ra-inpage-sidebar', className].join(' ')
  }, sections.map((section, si) => /*#__PURE__*/React.createElement("div", {
    key: si,
    className: "ra-inpage-sidebar__section"
  }, section.label && /*#__PURE__*/React.createElement("div", {
    className: "ra-inpage-sidebar__section-label"
  }, section.label), section.items.map(item => /*#__PURE__*/React.createElement("div", {
    key: item.id,
    className: ['ra-inpage-sidebar__item', activeId === item.id ? 'ra-inpage-sidebar__item--active' : ''].join(' '),
    onClick: () => onSelect && onSelect(item.id)
  }, item.icon && /*#__PURE__*/React.createElement("span", {
    className: "ra-inpage-sidebar__item-icon"
  }, item.icon), /*#__PURE__*/React.createElement("span", null, item.label))))));
}
function InpageLayout({
  sidebar,
  children,
  className = ''
}) {
  return /*#__PURE__*/React.createElement("div", {
    className: ['ra-inpage-layout', className].join(' ')
  }, sidebar, /*#__PURE__*/React.createElement("div", {
    className: "ra-inpage-content"
  }, children));
}
Object.assign(__ds_scope, { InpageSidebar, InpageLayout });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/InpageSidebar.jsx", error: String((e && e.message) || e) }); }

// components/layout/PageHeader.jsx
try { (() => {
const CSS = `
.ra-page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-4); padding: var(--space-6) 0 var(--space-5); }
.ra-page-header__left { flex: 1; min-width: 0; }
.ra-page-header__eyebrow { font-size: var(--text-sm); color: var(--text-muted); text-transform: uppercase; letter-spacing: var(--tracking-wider); font-weight: var(--font-semibold); margin-bottom: var(--space-1); }
.ra-page-header__title { font-size: var(--text-3xl); font-weight: var(--font-semibold); color: var(--text-primary); letter-spacing: var(--tracking-tight); line-height: var(--leading-tight); }
.ra-page-header__desc { font-size: var(--text-md); color: var(--text-secondary); margin-top: var(--space-1-5); line-height: var(--leading-snug); }
.ra-page-header__meta { display: flex; align-items: center; gap: var(--space-3); margin-top: var(--space-2-5); flex-wrap: wrap; }
.ra-page-header__right { display: flex; align-items: center; gap: var(--space-2); flex-shrink: 0; padding-top: 4px; }
.ra-page-header--compact .ra-page-header__title { font-size: var(--text-2xl); }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-ph-css')) {
  const s = document.createElement('style');
  s.id = 'ra-ph-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function PageHeader({
  title,
  description,
  eyebrow,
  meta,
  actions,
  compact = false,
  className = ''
}) {
  return /*#__PURE__*/React.createElement("div", {
    className: ['ra-page-header', compact ? 'ra-page-header--compact' : '', className].filter(Boolean).join(' ')
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-page-header__left"
  }, eyebrow && /*#__PURE__*/React.createElement("div", {
    className: "ra-page-header__eyebrow"
  }, eyebrow), /*#__PURE__*/React.createElement("h1", {
    className: "ra-page-header__title"
  }, title), description && /*#__PURE__*/React.createElement("p", {
    className: "ra-page-header__desc"
  }, description), meta && /*#__PURE__*/React.createElement("div", {
    className: "ra-page-header__meta"
  }, meta)), actions && /*#__PURE__*/React.createElement("div", {
    className: "ra-page-header__right"
  }, actions));
}
Object.assign(__ds_scope, { PageHeader });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/PageHeader.jsx", error: String((e && e.message) || e) }); }

// components/navigation/Breadcrumb.jsx
try { (() => {
const CSS = `
.ra-breadcrumb { display: flex; align-items: center; gap: var(--space-1); flex-wrap: wrap; }
.ra-breadcrumb__item { display: flex; align-items: center; gap: var(--space-1); font-size: var(--text-sm); color: var(--text-muted); }
.ra-breadcrumb__link { color: var(--text-muted); text-decoration: none; cursor: pointer; transition: color var(--duration-fast) ease; }
.ra-breadcrumb__link:hover { color: var(--text-primary); }
.ra-breadcrumb__current { color: var(--text-primary); font-weight: var(--font-medium); }
.ra-breadcrumb__sep { color: var(--text-disabled); }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-bc-css')) {
  const s = document.createElement('style');
  s.id = 'ra-bc-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
const Sep = () => /*#__PURE__*/React.createElement("svg", {
  width: "12",
  height: "12",
  viewBox: "0 0 12 12",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("polyline", {
  points: "4,2 8,6 4,10"
}));
function Breadcrumb({
  items = [],
  className = ''
}) {
  return /*#__PURE__*/React.createElement("nav", {
    "aria-label": "Breadcrumb",
    className: ['ra-breadcrumb', className].join(' ')
  }, items.map((item, i) => /*#__PURE__*/React.createElement("span", {
    key: i,
    className: "ra-breadcrumb__item"
  }, i > 0 && /*#__PURE__*/React.createElement("span", {
    className: "ra-breadcrumb__sep"
  }, /*#__PURE__*/React.createElement(Sep, null)), i === items.length - 1 ? /*#__PURE__*/React.createElement("span", {
    className: "ra-breadcrumb__current"
  }, item.label) : /*#__PURE__*/React.createElement("span", {
    className: "ra-breadcrumb__link",
    onClick: item.onClick
  }, item.label))));
}
Object.assign(__ds_scope, { Breadcrumb });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/navigation/Breadcrumb.jsx", error: String((e && e.message) || e) }); }

// components/navigation/Dropdown.jsx
try { (() => {
const CSS = `
.ra-dropdown { position: absolute; top: calc(100% + 4px); z-index: var(--z-dropdown); background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-dropdown); box-shadow: var(--shadow-dropdown); min-width: 180px; overflow: hidden; animation: ra-dd-in 120ms var(--ease-out); }
.ra-dropdown--left  { left: 0; }
.ra-dropdown--right { right: 0; }
.ra-dropdown__section { padding: var(--space-1) 0; }
.ra-dropdown__section + .ra-dropdown__section { border-top: 1px solid var(--border-color); }
.ra-dropdown__label { font-size: 10px; font-weight: var(--font-semibold); color: var(--text-muted); text-transform: uppercase; letter-spacing: var(--tracking-wider); padding: var(--space-2) var(--space-4) var(--space-1); }
.ra-dropdown__item { display: flex; align-items: center; gap: var(--space-2-5); padding: var(--space-2) var(--space-4); font-size: var(--text-base); color: var(--text-primary); cursor: pointer; transition: background var(--duration-fast) ease; text-decoration: none; }
.ra-dropdown__item:hover { background: var(--bg-hover); }
.ra-dropdown__item--active { color: var(--color-primary-700); font-weight: var(--font-medium); background: var(--color-primary-50); }
.ra-dropdown__item--danger { color: var(--color-error-600); }
.ra-dropdown__item--danger:hover { background: var(--color-error-50); }
.ra-dropdown__item-icon { width: 15px; height: 15px; color: var(--text-muted); flex-shrink: 0; display: flex; align-items: center; }
.ra-dropdown__divider { height: 1px; background: var(--border-color); margin: var(--space-1) 0; }
`;
if (typeof document !== 'undefined' && !document.getElementById('ra-dropdown-css')) {
  const s = document.createElement('style');
  s.id = 'ra-dropdown-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
function Dropdown({
  trigger,
  items = [],
  sections,
  align = 'left',
  open: controlledOpen,
  onOpenChange,
  className = ''
}) {
  const [internalOpen, setInternalOpen] = React.useState(false);
  const open = controlledOpen !== undefined ? controlledOpen : internalOpen;
  const setOpen = v => {
    setInternalOpen(v);
    onOpenChange && onOpenChange(v);
  };
  const ref = React.useRef(null);
  React.useEffect(() => {
    const h = e => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener('mousedown', h);
    return () => document.removeEventListener('mousedown', h);
  }, []);
  const renderItems = list => list.map((item, i) => {
    if (item.divider) return /*#__PURE__*/React.createElement("div", {
      key: i,
      className: "ra-dropdown__divider"
    });
    const cls = ['ra-dropdown__item', item.active ? 'ra-dropdown__item--active' : '', item.danger ? 'ra-dropdown__item--danger' : ''].filter(Boolean).join(' ');
    return /*#__PURE__*/React.createElement("div", {
      key: i,
      className: cls,
      onClick: () => {
        item.onClick && item.onClick();
        setOpen(false);
      }
    }, item.icon && /*#__PURE__*/React.createElement("span", {
      className: "ra-dropdown__item-icon"
    }, item.icon), /*#__PURE__*/React.createElement("span", null, item.label));
  });
  return /*#__PURE__*/React.createElement("div", {
    ref: ref,
    style: {
      position: 'relative',
      display: 'inline-flex'
    },
    className: className
  }, /*#__PURE__*/React.createElement("div", {
    onClick: () => setOpen(v => !v),
    style: {
      cursor: 'pointer'
    }
  }, trigger), open && /*#__PURE__*/React.createElement("div", {
    className: `ra-dropdown ra-dropdown--${align}`
  }, sections ? sections.map((sec, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    className: "ra-dropdown__section"
  }, sec.label && /*#__PURE__*/React.createElement("div", {
    className: "ra-dropdown__label"
  }, sec.label), renderItems(sec.items))) : /*#__PURE__*/React.createElement("div", {
    className: "ra-dropdown__section"
  }, renderItems(items))));
}
Object.assign(__ds_scope, { Dropdown });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/navigation/Dropdown.jsx", error: String((e && e.message) || e) }); }

// components/navigation/SecondNavbar.jsx
try { (() => {
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
  const s = document.createElement('style');
  s.id = 'ra-secnav-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}
const ChevronDown = () => /*#__PURE__*/React.createElement("svg", {
  className: "ra-snav-caret",
  width: "11",
  height: "11",
  viewBox: "0 0 12 12",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("polyline", {
  points: "2,4 6,8 10,4"
}));
const ChevronRight = () => /*#__PURE__*/React.createElement("svg", {
  width: "12",
  height: "12",
  viewBox: "0 0 12 12",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("polyline", {
  points: "4,2 8,6 4,10"
}));
const CloseIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "16",
  height: "16",
  viewBox: "0 0 16 16",
  stroke: "currentColor",
  strokeWidth: "2.5",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("line", {
  x1: "3",
  y1: "3",
  x2: "13",
  y2: "13"
}), /*#__PURE__*/React.createElement("line", {
  x1: "13",
  y1: "3",
  x2: "3",
  y2: "13"
}));
function useDropdown() {
  const [open, setOpen] = React.useState(false);
  const ref = React.useRef(null);
  React.useEffect(() => {
    const h = e => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener('mousedown', h);
    return () => document.removeEventListener('mousedown', h);
  }, []);
  return {
    open,
    setOpen,
    ref
  };
}
function NavItemSimple({
  item,
  isActive,
  onClick
}) {
  return /*#__PURE__*/React.createElement("div", {
    className: "ra-snav-item"
  }, /*#__PURE__*/React.createElement("button", {
    className: `ra-snav-btn ${isActive ? 'ra-snav-btn--active' : ''}`,
    onClick: () => onClick && onClick(item.id)
  }, item.icon && /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'flex',
      alignItems: 'center'
    }
  }, item.icon), /*#__PURE__*/React.createElement("span", null, item.label), item.badge ? /*#__PURE__*/React.createElement("span", {
    className: "ra-snav-badge"
  }, item.badge > 99 ? '99+' : item.badge) : null));
}
function NavItemDropdown({
  item,
  isActive,
  activeSubId,
  onClick
}) {
  const dd = useDropdown();
  const allChildren = item.sections ? item.sections.flatMap(s => s.items) : item.children || [];
  const totalBadge = allChildren.reduce((s, c) => s + (c.badge || 0), 0) + (item.badge || 0);
  const hasActiveSub = allChildren.some(c => c.id === activeSubId);
  return /*#__PURE__*/React.createElement("div", {
    className: "ra-snav-item",
    ref: dd.ref
  }, /*#__PURE__*/React.createElement("button", {
    className: `ra-snav-btn ra-snav-btn--has-dd ${isActive || hasActiveSub ? 'ra-snav-btn--active' : ''} ${dd.open ? 'ra-snav-btn--dd-open' : ''}`,
    onClick: () => dd.setOpen(v => !v)
  }, item.icon && /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'flex',
      alignItems: 'center'
    }
  }, item.icon), /*#__PURE__*/React.createElement("span", null, item.label), totalBadge > 0 && /*#__PURE__*/React.createElement("span", {
    className: "ra-snav-badge"
  }, totalBadge > 99 ? '99+' : totalBadge), /*#__PURE__*/React.createElement(ChevronDown, null)), dd.open && /*#__PURE__*/React.createElement("div", {
    className: "ra-snav-dd"
  }, (item.sections || [{
    items: item.children || []
  }]).map((sec, si) => /*#__PURE__*/React.createElement("div", {
    key: si,
    className: "ra-snav-dd__section"
  }, sec.label && /*#__PURE__*/React.createElement("div", {
    className: "ra-snav-dd__label"
  }, sec.label), sec.items.map(sub => /*#__PURE__*/React.createElement("div", {
    key: sub.id,
    className: `ra-snav-dd__item ${activeSubId === sub.id ? 'ra-snav-dd__item--active' : ''}`,
    onClick: () => {
      onClick && onClick(sub.id);
      dd.setOpen(false);
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-snav-dd__item-left"
  }, sub.icon && /*#__PURE__*/React.createElement("span", {
    className: "ra-snav-dd__item-icon"
  }, sub.icon), /*#__PURE__*/React.createElement("span", null, sub.label)), sub.badge ? /*#__PURE__*/React.createElement("span", {
    className: "ra-snav-dd__item-badge"
  }, sub.badge > 99 ? '99+' : sub.badge) : null))))));
}

/* ---- Mobile offcanvas ---- */
function MobileCanvas({
  items,
  activeId,
  onNavigate,
  projectLabel,
  onClose
}) {
  const [expanded, setExpanded] = React.useState(new Set());
  const toggle = id => setExpanded(prev => {
    const next = new Set(prev);
    next.has(id) ? next.delete(id) : next.add(id);
    return next;
  });
  const handleNav = id => {
    onNavigate && onNavigate(id);
    onClose();
  };
  const allChildren = item => item.sections ? item.sections.flatMap(s => s.items) : item.children || [];
  return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
    className: "ra-secnav-canvas__overlay",
    onClick: onClose
  }), /*#__PURE__*/React.createElement("div", {
    className: "ra-secnav-canvas"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-secnav-canvas__header"
  }, /*#__PURE__*/React.createElement("span", {
    className: "ra-secnav-canvas__title"
  }, projectLabel || 'Navigation'), /*#__PURE__*/React.createElement("button", {
    className: "ra-secnav-canvas__close",
    onClick: onClose
  }, /*#__PURE__*/React.createElement(CloseIcon, null))), /*#__PURE__*/React.createElement("div", {
    className: "ra-secnav-canvas__body"
  }, items.map((item, idx) => {
    const children = allChildren(item);
    const hasChildren = children.length > 0;
    const isOpen = expanded.has(item.id);
    const totalBadge = children.reduce((s, c) => s + (c.badge || 0), 0) + (item.badge || 0);
    const isItemActive = activeId === item.id || children.some(c => c.id === activeId);
    return /*#__PURE__*/React.createElement(React.Fragment, {
      key: item.id
    }, idx > 0 && !hasChildren && /*#__PURE__*/React.createElement("div", {
      className: "ra-secnav-canvas__section-divider"
    }), /*#__PURE__*/React.createElement("button", {
      className: `ra-secnav-canvas__item ${isItemActive && !hasChildren ? 'ra-secnav-canvas__item--active' : ''}`,
      onClick: () => hasChildren ? toggle(item.id) : handleNav(item.id)
    }, item.icon && /*#__PURE__*/React.createElement("span", {
      style: {
        display: 'flex',
        alignItems: 'center',
        opacity: .65,
        flexShrink: 0
      }
    }, item.icon), /*#__PURE__*/React.createElement("span", {
      style: {
        flex: 1,
        textAlign: 'left'
      }
    }, item.label), totalBadge > 0 && !isOpen && /*#__PURE__*/React.createElement("span", {
      className: "ra-secnav-canvas__badge"
    }, totalBadge > 99 ? '99+' : totalBadge), hasChildren && /*#__PURE__*/React.createElement("span", {
      className: `ra-secnav-canvas__expand ${isOpen ? 'ra-secnav-canvas__expand--open' : ''}`
    }, /*#__PURE__*/React.createElement(ChevronRight, null))), hasChildren && isOpen && children.map(sub => /*#__PURE__*/React.createElement("button", {
      key: sub.id,
      className: `ra-secnav-canvas__item ra-secnav-canvas__item--sub ${activeId === sub.id ? 'ra-secnav-canvas__item--active' : ''}`,
      onClick: () => handleNav(sub.id)
    }, sub.icon && /*#__PURE__*/React.createElement("span", {
      style: {
        display: 'flex',
        alignItems: 'center',
        opacity: .5,
        flexShrink: 0
      }
    }, sub.icon), /*#__PURE__*/React.createElement("span", {
      style: {
        flex: 1,
        textAlign: 'left'
      }
    }, sub.label), sub.badge ? /*#__PURE__*/React.createElement("span", {
      className: "ra-secnav-canvas__badge"
    }, sub.badge > 99 ? '99+' : sub.badge) : null)));
  }))));
}
function SecondNavbar({
  items = [],
  activeId,
  onNavigate,
  rightContent,
  projectLabel,
  mobileOpen = false,
  onMobileClose,
  className = ''
}) {
  // Find label for current active item for mobile bar
  const findLabel = id => {
    for (const item of items) {
      if (item.id === id) return item.label;
      const children = item.sections ? item.sections.flatMap(s => s.items) : item.children || [];
      const sub = children.find(c => c.id === id);
      if (sub) return sub.label;
    }
    return 'Menu';
  };
  return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("nav", {
    className: `ra-secnav ${className}`
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-secnav__inner"
  }, items.map(item => {
    const hasDD = item.children && item.children.length > 0 || item.sections && item.sections.length > 0;
    return hasDD ? /*#__PURE__*/React.createElement(NavItemDropdown, {
      key: item.id,
      item: item,
      isActive: activeId === item.id,
      activeSubId: activeId,
      onClick: onNavigate
    }) : /*#__PURE__*/React.createElement(NavItemSimple, {
      key: item.id,
      item: item,
      isActive: activeId === item.id,
      onClick: onNavigate
    });
  })), rightContent && /*#__PURE__*/React.createElement("div", {
    className: "ra-secnav__right"
  }, rightContent)), mobileOpen && /*#__PURE__*/React.createElement(MobileCanvas, {
    items: items,
    activeId: activeId,
    onNavigate: onNavigate,
    projectLabel: projectLabel || 'Navigation',
    onClose: onMobileClose
  }));
}
Object.assign(__ds_scope, { SecondNavbar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/navigation/SecondNavbar.jsx", error: String((e && e.message) || e) }); }

// components/navigation/TopNavbar.jsx
try { (() => {
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
  const s = document.createElement('style');
  s.id = 'ra-topnav-css';
  s.textContent = CSS;
  document.head.appendChild(s);
}

/* ── Icons ──────────────────────────────── */
// Brand mark — stylized R (v2)
const BrandMark = () => /*#__PURE__*/React.createElement("svg", {
  width: "15",
  height: "15",
  viewBox: "0 0 14 14",
  fill: "none",
  stroke: "white",
  strokeWidth: "2.2",
  strokeLinecap: "round",
  strokeLinejoin: "round"
}, /*#__PURE__*/React.createElement("path", {
  d: "M3 12V2h5a3 3 0 010 6H3"
}), /*#__PURE__*/React.createElement("path", {
  d: "M8 8l3 4"
}));

// Cog icon — 8-tooth gear
const CogIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "16",
  height: "16",
  viewBox: "0 0 20 20",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round",
  strokeLinejoin: "round"
}, /*#__PURE__*/React.createElement("circle", {
  cx: "10",
  cy: "10",
  r: "3"
}), /*#__PURE__*/React.createElement("path", {
  d: "M10 1v2.5M10 16.5V19M1 10h2.5M16.5 10H19M3.22 3.22l1.77 1.77M15.01 15.01l1.77 1.77M3.22 16.78l1.77-1.77M15.01 4.99l1.77-1.77"
}));

// Project icon — colored 2x2 grid, color = active project color
const ProjectIcon = ({
  color = 'rgba(255,255,255,0.45)',
  size = 14
}) => /*#__PURE__*/React.createElement("svg", {
  width: size,
  height: size,
  viewBox: "0 0 14 14",
  fill: color
}, /*#__PURE__*/React.createElement("rect", {
  x: "1",
  y: "1",
  width: "5",
  height: "5",
  rx: "1.2"
}), /*#__PURE__*/React.createElement("rect", {
  x: "8",
  y: "1",
  width: "5",
  height: "5",
  rx: "1.2"
}), /*#__PURE__*/React.createElement("rect", {
  x: "1",
  y: "8",
  width: "5",
  height: "5",
  rx: "1.2"
}), /*#__PURE__*/React.createElement("rect", {
  x: "8",
  y: "8",
  width: "5",
  height: "5",
  rx: "1.2"
}));
const ProjCaret = () => /*#__PURE__*/React.createElement("svg", {
  className: "ra-proj-caret",
  width: "9",
  height: "9",
  viewBox: "0 0 10 10",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("polyline", {
  points: "2,3 5,7 8,3"
}));
const BellIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "18",
  height: "18",
  viewBox: "0 0 20 20",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round",
  strokeLinejoin: "round"
}, /*#__PURE__*/React.createElement("path", {
  d: "M10 2a6 6 0 00-6 6v3l-1.5 2.5h15L16 11V8a6 6 0 00-6-6z"
}), /*#__PURE__*/React.createElement("path", {
  d: "M8.5 17a1.5 1.5 0 003 0"
}));
const ChevronDown = () => /*#__PURE__*/React.createElement("svg", {
  width: "11",
  height: "11",
  viewBox: "0 0 12 12",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("polyline", {
  points: "2,4 6,8 10,4"
}));
const HamburgerIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "18",
  height: "18",
  viewBox: "0 0 20 20",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("line", {
  x1: "2",
  y1: "5",
  x2: "18",
  y2: "5"
}), /*#__PURE__*/React.createElement("line", {
  x1: "2",
  y1: "10",
  x2: "18",
  y2: "10"
}), /*#__PURE__*/React.createElement("line", {
  x1: "2",
  y1: "15",
  x2: "18",
  y2: "15"
}));
const LogOutIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "14",
  height: "14",
  viewBox: "0 0 16 16",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("path", {
  d: "M6 14H3a1 1 0 01-1-1V3a1 1 0 011-1h3"
}), /*#__PURE__*/React.createElement("polyline", {
  points: "11,11 14,8 11,5"
}), /*#__PURE__*/React.createElement("line", {
  x1: "14",
  y1: "8",
  x2: "6",
  y2: "8"
}));
const UserIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "14",
  height: "14",
  viewBox: "0 0 16 16",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("circle", {
  cx: "8",
  cy: "5",
  r: "3"
}), /*#__PURE__*/React.createElement("path", {
  d: "M2 14c0-3.31 2.69-6 6-6s6 2.69 6 6"
}));
const GridIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "14",
  height: "14",
  viewBox: "0 0 16 16",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("rect", {
  x: "1",
  y: "1",
  width: "6",
  height: "6",
  rx: "1"
}), /*#__PURE__*/React.createElement("rect", {
  x: "9",
  y: "1",
  width: "6",
  height: "6",
  rx: "1"
}), /*#__PURE__*/React.createElement("rect", {
  x: "1",
  y: "9",
  width: "6",
  height: "6",
  rx: "1"
}), /*#__PURE__*/React.createElement("rect", {
  x: "9",
  y: "9",
  width: "6",
  height: "6",
  rx: "1"
}));
const ShieldIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "14",
  height: "14",
  viewBox: "0 0 16 16",
  fill: "none",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round"
}, /*#__PURE__*/React.createElement("path", {
  d: "M8 2L3 4v4c0 3 2.5 5.5 5 6 2.5-.5 5-3 5-6V4L8 2z"
}));
function useDropdown() {
  const [open, setOpen] = React.useState(false);
  const ref = React.useRef(null);
  React.useEffect(() => {
    const h = e => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener('mousedown', h);
    return () => document.removeEventListener('mousedown', h);
  }, []);
  return {
    open,
    setOpen,
    ref
  };
}
function AvatarInitials({
  name,
  size = 24
}) {
  const colors = ['#2563eb', '#7c3aed', '#059669', '#d97706'];
  let h = 0;
  for (let i = 0; i < (name || '').length; i++) h = h * 31 + name.charCodeAt(i) & 0xffffffff;
  const bg = colors[Math.abs(h) % colors.length];
  const parts = (name || '').trim().split(/\s+/);
  const init = parts.length === 1 ? parts[0][0] : parts[0][0] + parts[parts.length - 1][0];
  return /*#__PURE__*/React.createElement("div", {
    style: {
      width: size,
      height: size,
      borderRadius: '50%',
      background: bg,
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      fontSize: size * 0.42,
      fontWeight: 600,
      color: 'white',
      flexShrink: 0,
      fontFamily: 'var(--font-sans)'
    }
  }, init.toUpperCase());
}
function TopNavbar({
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
  className = ''
}) {
  const notifDd = useDropdown();
  const projDd = useDropdown();
  const settingsDd = useDropdown();
  const userDd = useDropdown();
  const unreadCount = notifications.filter(n => n.unread).length;
  const activeProj = projects.find(p => p.id === activeProject);
  const shortBrand = brandShortName || (brandName || '').split(/\s+/).map(w => w[0]).join('').toUpperCase();
  const defaultSettings = settingsItems.length ? settingsItems : [{
    icon: /*#__PURE__*/React.createElement(GridIcon, null),
    label: 'All Projects',
    onClick: onSettingsClick
  }, {
    icon: /*#__PURE__*/React.createElement(UserIcon, null),
    label: 'User Management',
    onClick: onSettingsClick
  }, {
    icon: /*#__PURE__*/React.createElement(ShieldIcon, null),
    label: 'Permissions',
    onClick: onSettingsClick
  }];
  return /*#__PURE__*/React.createElement("header", {
    className: `ra-topnav ${className}`
  }, /*#__PURE__*/React.createElement("button", {
    className: "ra-topnav__iconbtn ra-topnav__hamburger",
    onClick: onMobileMenuOpen,
    title: "Menu",
    style: {
      marginLeft: 'var(--space-2)'
    }
  }, /*#__PURE__*/React.createElement(HamburgerIcon, null)), /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav__brand"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav__brand-mark"
  }, /*#__PURE__*/React.createElement(BrandMark, null)), /*#__PURE__*/React.createElement("span", {
    className: "ra-topnav__brand-name ra-topnav__brand-name--full"
  }, brandName), /*#__PURE__*/React.createElement("span", {
    className: "ra-topnav__brand-name ra-topnav__brand-name--short"
  }, shortBrand), projects.length > 0 && /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative'
    },
    ref: projDd.ref
  }, /*#__PURE__*/React.createElement("button", {
    className: "ra-topnav__proj-icon-btn",
    onClick: () => projDd.setOpen(v => !v),
    title: activeProj?.name || 'Switch project'
  }, /*#__PURE__*/React.createElement(ProjectIcon, {
    color: activeProj?.color || 'rgba(255,255,255,0.35)'
  }), /*#__PURE__*/React.createElement("span", {
    className: "ra-topnav__proj-label"
  }, activeProj?.name), /*#__PURE__*/React.createElement(ProjCaret, null)), projDd.open && /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd ra-topnav-dd--projects"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd__section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd__label"
  }, "Switch Project"), projects.map(p => /*#__PURE__*/React.createElement("div", {
    key: p.id,
    className: `ra-topnav-dd__item ${activeProject === p.id ? 'ra-topnav-dd__item--active' : ''}`,
    onClick: () => {
      onProjectChange && onProjectChange(p.id);
      projDd.setOpen(false);
    }
  }, /*#__PURE__*/React.createElement(ProjectIcon, {
    color: p.color || 'var(--text-muted)',
    size: 13
  }), /*#__PURE__*/React.createElement("span", null, p.name), activeProject === p.id && /*#__PURE__*/React.createElement("span", {
    style: {
      marginLeft: 'auto',
      fontSize: 11,
      color: 'var(--color-primary-500)'
    }
  }, "\u2713"))))))), /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav__spacer"
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative'
    },
    ref: notifDd.ref
  }, /*#__PURE__*/React.createElement("button", {
    className: "ra-topnav__iconbtn",
    onClick: () => notifDd.setOpen(v => !v),
    title: "Notifications"
  }, /*#__PURE__*/React.createElement(BellIcon, null), unreadCount > 0 && /*#__PURE__*/React.createElement("span", {
    className: "ra-topnav__badge"
  }, unreadCount > 9 ? '9+' : unreadCount)), notifDd.open && /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd ra-topnav-dd--notifications"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd__header"
  }, /*#__PURE__*/React.createElement("span", {
    className: "ra-topnav-dd__header-title"
  }, "Notifications"), unreadCount > 0 && /*#__PURE__*/React.createElement("span", {
    className: "ra-topnav-dd__mark-read",
    onClick: onMarkAllRead
  }, "Mark all read")), /*#__PURE__*/React.createElement("div", {
    className: "ra-notif-list"
  }, notifications.length === 0 ? /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 'var(--space-6)',
      textAlign: 'center',
      color: 'var(--text-muted)',
      fontSize: 'var(--text-sm)'
    }
  }, "No notifications") : notifications.map((n, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    className: `ra-notif-item ${n.unread ? 'ra-notif-item--unread' : ''}`
  }, n.unread && /*#__PURE__*/React.createElement("span", {
    className: "ra-notif-item__dot"
  }), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    className: "ra-notif-item__title"
  }, n.title), n.description && /*#__PURE__*/React.createElement("div", {
    className: "ra-notif-item__desc"
  }, n.description), /*#__PURE__*/React.createElement("div", {
    className: "ra-notif-item__time"
  }, n.time))))))), /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav__right"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative'
    },
    ref: settingsDd.ref
  }, /*#__PURE__*/React.createElement("button", {
    className: "ra-topnav__trigger",
    onClick: () => settingsDd.setOpen(v => !v)
  }, /*#__PURE__*/React.createElement(CogIcon, null), /*#__PURE__*/React.createElement("span", {
    className: "ra-topnav__settings-label"
  }, "Settings"), /*#__PURE__*/React.createElement("span", {
    className: "ra-topnav__trigger-caret"
  }, /*#__PURE__*/React.createElement(ChevronDown, null))), settingsDd.open && /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd__section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd__label"
  }, "System"), defaultSettings.map((item, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    className: "ra-topnav-dd__item",
    onClick: () => {
      item.onClick && item.onClick();
      settingsDd.setOpen(false);
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: "ra-topnav-dd__item-icon"
  }, item.icon), /*#__PURE__*/React.createElement("span", null, item.label)))))), /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative'
    },
    ref: userDd.ref
  }, /*#__PURE__*/React.createElement("button", {
    className: "ra-topnav__trigger",
    onClick: () => userDd.setOpen(v => !v)
  }, user.avatar ? /*#__PURE__*/React.createElement("img", {
    style: {
      width: 24,
      height: 24,
      borderRadius: '50%',
      objectFit: 'cover'
    },
    src: user.avatar,
    alt: user.name
  }) : /*#__PURE__*/React.createElement(AvatarInitials, {
    name: user.name || 'User',
    size: 24
  }), /*#__PURE__*/React.createElement("span", {
    className: "ra-topnav__user-label"
  }, user.name || 'Account'), /*#__PURE__*/React.createElement("span", {
    className: "ra-topnav__trigger-caret"
  }, /*#__PURE__*/React.createElement(ChevronDown, null))), userDd.open && /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd__user"
  }, /*#__PURE__*/React.createElement(AvatarInitials, {
    name: user.name || 'User',
    size: 36
  }), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd__user-name"
  }, user.name || 'User'), /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd__user-email"
  }, user.email))), /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd__section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd__item",
    onClick: () => {
      onProfileClick && onProfileClick();
      userDd.setOpen(false);
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: "ra-topnav-dd__item-icon"
  }, /*#__PURE__*/React.createElement(UserIcon, null)), /*#__PURE__*/React.createElement("span", null, "Profile")), /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd__item",
    onClick: () => {
      onSettingsClick && onSettingsClick();
      userDd.setOpen(false);
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: "ra-topnav-dd__item-icon"
  }, /*#__PURE__*/React.createElement(CogIcon, null)), /*#__PURE__*/React.createElement("span", null, "Preferences"))), /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd__section"
  }, /*#__PURE__*/React.createElement("div", {
    className: "ra-topnav-dd__item ra-topnav-dd__item--danger",
    onClick: () => {
      onLogout && onLogout();
      userDd.setOpen(false);
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: "ra-topnav-dd__item-icon"
  }, /*#__PURE__*/React.createElement(LogOutIcon, null)), /*#__PURE__*/React.createElement("span", null, "Sign out")))))));
}
Object.assign(__ds_scope, { TopNavbar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/navigation/TopNavbar.jsx", error: String((e && e.message) || e) }); }

// ui_kits/rock_admin/screens/DashboardEcom.jsx
try { (() => {
const {
  StatCard,
  LineChart,
  BarChart,
  DonutChart,
  Badge,
  Avatar
} = window.RockAdminDesignSystem_fced52;
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
const LINE_SERIES = [{
  name: 'Revenue (€)',
  data: [28400, 35100, 31800, 47200, 42600, 58900],
  color: 'var(--color-primary-500)'
}, {
  name: 'Orders',
  data: [820, 1050, 940, 1380, 1220, 1710],
  color: 'var(--color-teal-500)'
}];
const BAR_SERIES = [{
  name: 'This year',
  data: [3200, 4100, 3800, 5200, 4900, 6800],
  color: 'var(--color-primary-400)'
}, {
  name: 'Last year',
  data: [2100, 2900, 2600, 3800, 3500, 4900],
  color: 'var(--color-neutral-200)'
}];
const TRAFFIC = [{
  label: 'Direct',
  value: 4200,
  color: 'var(--color-primary-500)'
}, {
  label: 'Organic',
  value: 3100,
  color: 'var(--color-teal-500)'
}, {
  label: 'Paid',
  value: 1800,
  color: 'var(--color-orange-500)'
}, {
  label: 'Social',
  value: 900,
  color: 'var(--color-pink-500)'
}];
const ORDERS = [{
  id: '#4821',
  customer: 'Jan Novák',
  product: 'AirMax Pro × 2',
  total: '€298',
  status: 'processing',
  time: '2m ago'
}, {
  id: '#4820',
  customer: 'Marie Dvořák',
  product: 'Leather Jacket',
  total: '€389',
  status: 'paid',
  time: '14m ago'
}, {
  id: '#4819',
  customer: 'Petr Svoboda',
  product: 'Wireless Headphones',
  total: '€229',
  status: 'shipped',
  time: '1h ago'
}, {
  id: '#4818',
  customer: 'Anna Procházková',
  product: 'Running Shorts',
  total: '€59',
  status: 'delivered',
  time: '2h ago'
}, {
  id: '#4817',
  customer: 'Karel Novotný',
  product: 'Sports Bag + Shorts',
  total: '€142',
  status: 'refund',
  time: '5h ago'
}];
const STATUS_COLOR = {
  processing: 'blue',
  paid: 'green',
  shipped: 'indigo',
  delivered: 'teal',
  refund: 'yellow'
};
const EuroIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "18",
  height: "18",
  viewBox: "0 0 20 20",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("path", {
  d: "M15 5a7 7 0 100 10"
}), /*#__PURE__*/React.createElement("line", {
  x1: "3",
  y1: "8",
  x2: "12",
  y2: "8"
}), /*#__PURE__*/React.createElement("line", {
  x1: "3",
  y1: "12",
  x2: "11",
  y2: "12"
}));
const CartIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "18",
  height: "18",
  viewBox: "0 0 20 20",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("circle", {
  cx: "8",
  cy: "17",
  r: "1.5",
  fill: "currentColor"
}), /*#__PURE__*/React.createElement("circle", {
  cx: "15",
  cy: "17",
  r: "1.5",
  fill: "currentColor"
}), /*#__PURE__*/React.createElement("path", {
  d: "M1 1h3l2 10h9l2-7H6"
}));
const UsersIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "18",
  height: "18",
  viewBox: "0 0 20 20",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("circle", {
  cx: "8",
  cy: "7",
  r: "4"
}), /*#__PURE__*/React.createElement("path", {
  d: "M2 18c0-3.86 2.69-7 6-7"
}), /*#__PURE__*/React.createElement("circle", {
  cx: "15",
  cy: "9",
  r: "3"
}), /*#__PURE__*/React.createElement("path", {
  d: "M13 18c0-2.5.9-4.5 2-5"
}));
const TrendIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "18",
  height: "18",
  viewBox: "0 0 20 20",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("polyline", {
  points: "2,14 7,9 11,13 18,5"
}), /*#__PURE__*/React.createElement("polyline", {
  points: "14,5 18,5 18,9"
}));
function DashboardEcom() {
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    className: "ra-cols-4",
    style: {
      marginBottom: 20
    }
  }, /*#__PURE__*/React.createElement(StatCard, {
    label: "Revenue",
    value: "\u20AC48.2k",
    trend: 12.4,
    trendLabel: "vs last mo",
    sparklineData: [28400, 35100, 31800, 47200, 42600, 58900],
    icon: /*#__PURE__*/React.createElement(EuroIcon, null),
    iconColor: "var(--color-primary-500)",
    iconBg: "var(--color-primary-600)"
  }), /*#__PURE__*/React.createElement(StatCard, {
    label: "Orders",
    value: "1,284",
    trend: 8.1,
    trendLabel: "vs last mo",
    sparklineData: [820, 1050, 940, 1380, 1220, 1710],
    icon: /*#__PURE__*/React.createElement(CartIcon, null),
    iconColor: "var(--color-teal-500)",
    iconBg: "var(--color-teal-600)"
  }), /*#__PURE__*/React.createElement(StatCard, {
    label: "Customers",
    value: "3,421",
    trend: -2.3,
    trendLabel: "vs last mo",
    icon: /*#__PURE__*/React.createElement(UsersIcon, null),
    iconColor: "var(--color-indigo-500)",
    iconBg: "var(--color-indigo-600)"
  }), /*#__PURE__*/React.createElement(StatCard, {
    label: "Conv. Rate",
    value: "3.6%",
    trend: 0.4,
    trendLabel: "vs last mo",
    sparklineData: [3.1, 3.4, 3.0, 3.8, 3.5, 3.6],
    icon: /*#__PURE__*/React.createElement(TrendIcon, null),
    iconColor: "var(--color-orange-500)",
    iconBg: "var(--color-orange-500)"
  })), /*#__PURE__*/React.createElement("div", {
    className: "ra-cols-2-1",
    style: {
      marginBottom: 20
    }
  }, /*#__PURE__*/React.createElement(LineChart, {
    title: "Revenue & Orders",
    subtitle: "Last 6 months",
    labels: MONTHS,
    series: LINE_SERIES,
    height: 220
  }), /*#__PURE__*/React.createElement(DonutChart, {
    title: "Traffic Sources",
    data: TRAFFIC,
    size: 175
  })), /*#__PURE__*/React.createElement("div", {
    className: "ra-cols-3-2"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-lg)',
      overflow: 'hidden',
      boxShadow: 'var(--shadow-xs)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      padding: 'var(--space-4) var(--space-5)',
      borderBottom: '1px solid var(--border-color)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 'var(--font-semibold)',
      fontSize: 'var(--text-md)'
    }
  }, "Recent Orders"), /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--text-link)'
    }
  }, "View all \u2192")), /*#__PURE__*/React.createElement("table", {
    style: {
      width: '100%',
      borderCollapse: 'collapse',
      fontSize: 'var(--text-base)'
    }
  }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", {
    style: {
      borderBottom: '1px solid var(--border-color)'
    }
  }, ['Order', 'Customer', 'Product', 'Total', 'Status', 'Time'].map(h => /*#__PURE__*/React.createElement("th", {
    key: h,
    style: {
      textAlign: 'left',
      padding: '8px 20px',
      fontSize: 11,
      fontWeight: 600,
      color: 'var(--text-muted)',
      textTransform: 'uppercase',
      letterSpacing: '.05em',
      background: 'var(--color-neutral-50)'
    }
  }, h)))), /*#__PURE__*/React.createElement("tbody", null, ORDERS.map((o, i) => /*#__PURE__*/React.createElement("tr", {
    key: o.id,
    style: {
      borderBottom: i < ORDERS.length - 1 ? '1px solid var(--border-color)' : 'none'
    }
  }, /*#__PURE__*/React.createElement("td", {
    style: {
      padding: '10px 20px',
      fontFamily: 'var(--font-mono)',
      fontSize: 'var(--text-base)',
      color: 'var(--color-primary-600)',
      fontWeight: 500
    }
  }, o.id), /*#__PURE__*/React.createElement("td", {
    style: {
      padding: '10px 20px'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8
    }
  }, /*#__PURE__*/React.createElement(Avatar, {
    name: o.customer,
    size: "xs"
  }), /*#__PURE__*/React.createElement("span", null, o.customer))), /*#__PURE__*/React.createElement("td", {
    style: {
      padding: '10px 20px',
      color: 'var(--text-secondary)',
      maxWidth: 160,
      overflow: 'hidden',
      textOverflow: 'ellipsis',
      whiteSpace: 'nowrap'
    }
  }, o.product), /*#__PURE__*/React.createElement("td", {
    style: {
      padding: '10px 20px',
      fontWeight: 'var(--font-semibold)'
    }
  }, o.total), /*#__PURE__*/React.createElement("td", {
    style: {
      padding: '10px 20px'
    }
  }, /*#__PURE__*/React.createElement(Badge, {
    color: STATUS_COLOR[o.status],
    dot: true
  }, o.status)), /*#__PURE__*/React.createElement("td", {
    style: {
      padding: '10px 20px',
      color: 'var(--text-muted)',
      whiteSpace: 'nowrap'
    }
  }, o.time)))))), /*#__PURE__*/React.createElement(BarChart, {
    title: "Monthly Comparison",
    subtitle: "This year vs last",
    labels: MONTHS,
    series: BAR_SERIES,
    height: 300
  })));
}
window.DashboardEcomScreen = DashboardEcom;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/rock_admin/screens/DashboardEcom.jsx", error: String((e && e.message) || e) }); }

// ui_kits/rock_admin/screens/FormAdd.jsx
try { (() => {
const {
  PageHeader,
  FormField,
  Input,
  Select,
  Textarea,
  Switch,
  Button,
  Breadcrumb,
  Badge,
  Checkbox,
  SearchSelect
} = window.RockAdminDesignSystem_fced52;
function FormAdd({
  onNavigate
}) {
  // Narrow container for form pages
  const narrowWrap = {
    maxWidth: 'var(--content-medium-width)',
    margin: '0 auto'
  };
  const [status, setStatus] = React.useState('draft');
  const [featured, setFeatured] = React.useState(false);
  const [saleEnabled, setSaleEnabled] = React.useState(false);
  const [saved, setSaved] = React.useState(false);
  const save = () => {
    setSaved(true);
    setTimeout(() => setSaved(false), 2500);
  };
  const ArrowLeftIcon = () => /*#__PURE__*/React.createElement("svg", {
    width: "14",
    height: "14",
    viewBox: "0 0 14 14",
    stroke: "currentColor",
    strokeWidth: "2",
    strokeLinecap: "round",
    fill: "none"
  }, /*#__PURE__*/React.createElement("polyline", {
    points: "9,2 4,7 9,12"
  }));
  return /*#__PURE__*/React.createElement("div", {
    style: narrowWrap
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: 4
    }
  }, /*#__PURE__*/React.createElement(Breadcrumb, {
    items: [{
      label: 'Products',
      onClick: () => onNavigate && onNavigate('products')
    }, {
      label: 'New Product'
    }]
  })), /*#__PURE__*/React.createElement(PageHeader, {
    title: "Add Product",
    description: "Create a new product in your catalog.",
    actions: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      leftIcon: /*#__PURE__*/React.createElement(ArrowLeftIcon, null),
      size: "sm",
      onClick: () => onNavigate && onNavigate('products')
    }, "Back"), /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      size: "sm"
    }, "Save as Draft"), /*#__PURE__*/React.createElement(Button, {
      size: "sm",
      loading: false,
      onClick: save
    }, saved ? '✓ Saved' : 'Publish'))
  }), /*#__PURE__*/React.createElement("div", {
    className: "ra-form-layout"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 16
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-lg)',
      padding: 'var(--space-5)',
      boxShadow: 'var(--shadow-xs)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 'var(--font-semibold)',
      fontSize: 'var(--text-md)',
      marginBottom: 16,
      paddingBottom: 12,
      borderBottom: '1px solid var(--border-color)'
    }
  }, "Basic Information"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 14
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Product Name",
    required: true
  }, /*#__PURE__*/React.createElement(Input, {
    placeholder: "e.g. AirMax Pro Sneakers"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Slug",
    hint: "Used in URL: /products/your-slug"
  }, /*#__PURE__*/React.createElement(Input, {
    placeholder: "airmax-pro-sneakers"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Short Description"
  }, /*#__PURE__*/React.createElement(Textarea, {
    placeholder: "Brief product description shown in listings...",
    rows: 2
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Full Description",
    hint: "Supports placeholders",
    hintLinkLabel: "?",
    onHintLinkClick: () => alert('Use {name}, {sku} as dynamic placeholders')
  }, /*#__PURE__*/React.createElement(Textarea, {
    placeholder: "Detailed product description...",
    rows: 4,
    placeholders: [{
      label: '{name}',
      value: '{name}'
    }, {
      label: '{sku}',
      value: '{sku}'
    }, {
      label: '{category}',
      value: '{category}'
    }]
  })))), /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-lg)',
      padding: 'var(--space-5)',
      boxShadow: 'var(--shadow-xs)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 'var(--font-semibold)',
      fontSize: 'var(--text-md)',
      marginBottom: 16,
      paddingBottom: 12,
      borderBottom: '1px solid var(--border-color)'
    }
  }, "Pricing"), /*#__PURE__*/React.createElement("div", {
    className: "ra-cols-2",
    style: {
      gap: 14
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Price (CZK)",
    required: true
  }, /*#__PURE__*/React.createElement(Input, {
    type: "number",
    placeholder: "0.00"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Price (EUR)"
  }, /*#__PURE__*/React.createElement(Input, {
    type: "number",
    placeholder: "0.00"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Sale Price",
    hint: saleEnabled ? 'Shown as discounted' : 'Enable sale to set'
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 8,
      alignItems: 'center'
    }
  }, /*#__PURE__*/React.createElement(Switch, {
    checked: saleEnabled,
    onChange: e => setSaleEnabled(e.target.checked)
  }), /*#__PURE__*/React.createElement(Input, {
    type: "number",
    placeholder: "0.00",
    disabled: !saleEnabled,
    style: {
      flex: 1
    }
  }))), /*#__PURE__*/React.createElement(FormField, {
    label: "Tax Class"
  }, /*#__PURE__*/React.createElement(Select, {
    options: ['Standard 21%', 'Reduced 12%', 'Zero 0%']
  })))), /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-lg)',
      padding: 'var(--space-5)',
      boxShadow: 'var(--shadow-xs)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 'var(--font-semibold)',
      fontSize: 'var(--text-md)',
      marginBottom: 16,
      paddingBottom: 12,
      borderBottom: '1px solid var(--border-color)'
    }
  }, "Inventory & Shipping"), /*#__PURE__*/React.createElement("div", {
    className: "ra-cols-2",
    style: {
      gap: 14
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "SKU",
    hint: "Unique identifier"
  }, /*#__PURE__*/React.createElement(Input, {
    placeholder: "SKU-000"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Barcode"
  }, /*#__PURE__*/React.createElement(Input, {
    placeholder: "EAN / UPC"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Stock Quantity"
  }, /*#__PURE__*/React.createElement(Input, {
    type: "number",
    placeholder: "0"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Low Stock Threshold"
  }, /*#__PURE__*/React.createElement(Input, {
    type: "number",
    placeholder: "5"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Weight (g)"
  }, /*#__PURE__*/React.createElement(Input, {
    type: "number",
    placeholder: "0"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Dimensions (cm)"
  }, /*#__PURE__*/React.createElement(Input, {
    placeholder: "L \xD7 W \xD7 H"
  }))))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 16
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-lg)',
      padding: 'var(--space-5)',
      boxShadow: 'var(--shadow-xs)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 'var(--font-semibold)',
      marginBottom: 14,
      paddingBottom: 10,
      borderBottom: '1px solid var(--border-color)'
    }
  }, "Status & Visibility"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 12
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Status"
  }, /*#__PURE__*/React.createElement(Select, {
    value: status,
    onChange: e => setStatus(e.target.value),
    options: [{
      value: 'draft',
      label: 'Draft'
    }, {
      value: 'active',
      label: 'Active'
    }, {
      value: 'archived',
      label: 'Archived'
    }]
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Visibility"
  }, /*#__PURE__*/React.createElement(Select, {
    options: ['Visible', 'Hidden', 'Featured Only']
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      paddingTop: 4
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-md)'
    }
  }, "Featured product"), /*#__PURE__*/React.createElement(Switch, {
    checked: featured,
    onChange: e => setFeatured(e.target.checked)
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-md)'
    }
  }, "Allow reviews"), /*#__PURE__*/React.createElement(Switch, {
    defaultChecked: true
  })))), /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-lg)',
      padding: 'var(--space-5)',
      boxShadow: 'var(--shadow-xs)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 'var(--font-semibold)',
      marginBottom: 14,
      paddingBottom: 10,
      borderBottom: '1px solid var(--border-color)'
    }
  }, "Classification"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 12
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Category"
  }, /*#__PURE__*/React.createElement(SearchSelect, {
    options: ['Electronics', 'Clothing', 'Footwear', 'Sports', 'Books', 'Toys', 'Home'],
    placeholder: "Select category..."
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Tags"
  }, /*#__PURE__*/React.createElement(SearchSelect, {
    options: ['featured', 'sale', 'new', 'bestseller', 'limited', 'bundle'],
    placeholder: "Add tags...",
    multi: true
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Brand"
  }, /*#__PURE__*/React.createElement(Input, {
    placeholder: "Brand name"
  })))), /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-lg)',
      padding: 'var(--space-5)',
      boxShadow: 'var(--shadow-xs)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 'var(--font-semibold)',
      marginBottom: 14,
      paddingBottom: 10,
      borderBottom: '1px solid var(--border-color)'
    }
  }, "SEO"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 12
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Meta Title",
    hint: "60 chars max"
  }, /*#__PURE__*/React.createElement(Input, {
    placeholder: "Product page title..."
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Meta Description",
    hint: "160 chars max"
  }, /*#__PURE__*/React.createElement(Textarea, {
    placeholder: "Description for search engines...",
    rows: 3,
    maxLength: 160
  })))))));
}
window.FormAddScreen = FormAdd;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/rock_admin/screens/FormAdd.jsx", error: String((e && e.message) || e) }); }

// ui_kits/rock_admin/screens/Login.jsx
try { (() => {
const {
  Button,
  Input,
  Checkbox,
  FormField,
  Alert
} = window.RockAdminDesignSystem_fced52;
function Login({
  onLogin
}) {
  const [email, setEmail] = React.useState('admin@company.cz');
  const [pw, setPw] = React.useState('');
  const [err, setErr] = React.useState('');
  const [loading, setLoading] = React.useState(false);
  const [showPw, setShowPw] = React.useState(false);
  const EyeIcon = () => /*#__PURE__*/React.createElement("svg", {
    width: "14",
    height: "14",
    viewBox: "0 0 16 16",
    stroke: "currentColor",
    strokeWidth: "1.5",
    fill: "none",
    strokeLinecap: "round"
  }, /*#__PURE__*/React.createElement("ellipse", {
    cx: "8",
    cy: "8",
    rx: "7",
    ry: "4"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "8",
    cy: "8",
    r: "2"
  }));
  const submit = e => {
    e.preventDefault();
    if (!email || !pw) {
      setErr('Please fill in all fields.');
      return;
    }
    setLoading(true);
    setErr('');
    setTimeout(() => {
      setLoading(false);
      onLogin && onLogin();
    }, 1000);
  };
  return /*#__PURE__*/React.createElement("div", {
    style: {
      minHeight: '100vh',
      background: 'var(--bg-page)',
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      justifyContent: 'center',
      padding: 'var(--space-6)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: '100%',
      maxWidth: 400
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      gap: 10,
      marginBottom: 32
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 36,
      height: 36,
      background: 'var(--color-primary-600)',
      borderRadius: 8,
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center'
    }
  }, /*#__PURE__*/React.createElement("svg", {
    width: "20",
    height: "20",
    viewBox: "0 0 16 16",
    fill: "none"
  }, /*#__PURE__*/React.createElement("rect", {
    x: "1",
    y: "1",
    width: "6",
    height: "6",
    rx: "1.5",
    fill: "white",
    opacity: "0.95"
  }), /*#__PURE__*/React.createElement("rect", {
    x: "9",
    y: "1",
    width: "6",
    height: "6",
    rx: "1.5",
    fill: "white",
    opacity: "0.55"
  }), /*#__PURE__*/React.createElement("rect", {
    x: "1",
    y: "9",
    width: "6",
    height: "6",
    rx: "1.5",
    fill: "white",
    opacity: "0.55"
  }), /*#__PURE__*/React.createElement("rect", {
    x: "9",
    y: "9",
    width: "6",
    height: "6",
    rx: "1.5",
    fill: "white",
    opacity: "0.25"
  }))), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 700,
      fontSize: 'var(--text-xl)',
      color: 'var(--text-primary)',
      letterSpacing: 'var(--tracking-tight)'
    }
  }, "Rock Admin"))), /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-xl)',
      padding: 'var(--space-8)',
      boxShadow: 'var(--shadow-lg)'
    }
  }, /*#__PURE__*/React.createElement("h1", {
    style: {
      fontSize: 'var(--text-2xl)',
      fontWeight: 'var(--font-semibold)',
      color: 'var(--text-primary)',
      marginBottom: 4,
      letterSpacing: 'var(--tracking-tight)'
    }
  }, "Sign in"), /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: 'var(--text-md)',
      color: 'var(--text-muted)',
      marginBottom: 'var(--space-6)'
    }
  }, "Welcome back to your admin panel"), err && /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: 16
    }
  }, /*#__PURE__*/React.createElement(Alert, {
    type: "error",
    size: "sm"
  }, err)), /*#__PURE__*/React.createElement("form", {
    onSubmit: submit,
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 16
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Email address"
  }, /*#__PURE__*/React.createElement(Input, {
    type: "email",
    value: email,
    onChange: e => setEmail(e.target.value),
    placeholder: "you@company.cz",
    autoComplete: "email"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Password"
  }, /*#__PURE__*/React.createElement(Input, {
    type: showPw ? 'text' : 'password',
    value: pw,
    onChange: e => setPw(e.target.value),
    placeholder: "Your password",
    autoComplete: "current-password",
    rightIcon: /*#__PURE__*/React.createElement(EyeIcon, null),
    onRightIconClick: () => setShowPw(v => !v)
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between'
    }
  }, /*#__PURE__*/React.createElement(Checkbox, {
    label: "Remember me",
    defaultChecked: true
  }), /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--text-link)'
    }
  }, "Forgot password?")), /*#__PURE__*/React.createElement(Button, {
    type: "submit",
    fullWidth: true,
    size: "lg",
    loading: loading
  }, "Sign in"))), /*#__PURE__*/React.createElement("p", {
    style: {
      textAlign: 'center',
      marginTop: 20,
      fontSize: 'var(--text-sm)',
      color: 'var(--text-muted)'
    }
  }, "Rock Admin \xA9 2025 \u2014 ", /*#__PURE__*/React.createElement("a", {
    href: "#",
    style: {
      color: 'var(--text-link)'
    }
  }, "Privacy Policy"))));
}
window.LoginScreen = Login;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/rock_admin/screens/Login.jsx", error: String((e && e.message) || e) }); }

// ui_kits/rock_admin/screens/Monitoring.jsx
try { (() => {
const {
  Badge,
  Card,
  Alert,
  StatCard
} = window.RockAdminDesignSystem_fced52;
const ENDPOINTS = [{
  name: 'GET /api/products',
  status: 'ok',
  latency: 42,
  uptime: '99.99%'
}, {
  name: 'POST /api/orders',
  status: 'ok',
  latency: 78,
  uptime: '99.97%'
}, {
  name: 'GET /api/users',
  status: 'ok',
  latency: 31,
  uptime: '100%'
}, {
  name: 'POST /api/auth/login',
  status: 'ok',
  latency: 115,
  uptime: '100%'
}, {
  name: 'GET /api/analytics',
  status: 'slow',
  latency: 890,
  uptime: '99.1%'
}, {
  name: 'POST /api/export',
  status: 'error',
  latency: null,
  uptime: '94.2%'
}];
const LOGS = [{
  ts: '14:32:11',
  level: 'error',
  msg: 'POST /api/export — timeout after 30000ms',
  service: 'api'
}, {
  ts: '14:30:05',
  level: 'warn',
  msg: 'GET /api/analytics — response time 890ms exceeds threshold',
  service: 'api'
}, {
  ts: '14:28:44',
  level: 'info',
  msg: '1,284 active sessions',
  service: 'app'
}, {
  ts: '14:25:10',
  level: 'info',
  msg: 'Scheduled backup completed — 2.3GB',
  service: 'cron'
}, {
  ts: '14:20:33',
  level: 'info',
  msg: 'Cache cleared (products)',
  service: 'cache'
}];
function MetricBar({
  value,
  color
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'relative',
      height: 8,
      background: 'var(--color-neutral-100)',
      borderRadius: 4,
      overflow: 'hidden',
      marginTop: 6
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: 'absolute',
      left: 0,
      top: 0,
      height: '100%',
      width: value + '%',
      background: color,
      borderRadius: 4,
      transition: 'width .5s ease'
    }
  }));
}
function Monitoring() {
  const ServerIcon = () => /*#__PURE__*/React.createElement("svg", {
    width: "20",
    height: "20",
    viewBox: "0 0 20 20",
    stroke: "currentColor",
    strokeWidth: "1.5",
    fill: "none",
    strokeLinecap: "round"
  }, /*#__PURE__*/React.createElement("rect", {
    x: "2",
    y: "3",
    width: "16",
    height: "5",
    rx: "1"
  }), /*#__PURE__*/React.createElement("rect", {
    x: "2",
    y: "11",
    width: "16",
    height: "5",
    rx: "1"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "6",
    cy: "5.5",
    r: "1",
    fill: "currentColor",
    stroke: "none"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "6",
    cy: "13.5",
    r: "1",
    fill: "currentColor",
    stroke: "none"
  }));
  const CpuIcon = () => /*#__PURE__*/React.createElement("svg", {
    width: "20",
    height: "20",
    viewBox: "0 0 20 20",
    stroke: "currentColor",
    strokeWidth: "1.5",
    fill: "none",
    strokeLinecap: "round"
  }, /*#__PURE__*/React.createElement("rect", {
    x: "5",
    y: "5",
    width: "10",
    height: "10",
    rx: "1"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M7 2v3M10 2v3M13 2v3M7 15v3M10 15v3M13 15v3M2 7h3M2 10h3M2 13h3M15 7h3M15 10h3M15 13h3"
  }));
  const HddIcon = () => /*#__PURE__*/React.createElement("svg", {
    width: "20",
    height: "20",
    viewBox: "0 0 20 20",
    stroke: "currentColor",
    strokeWidth: "1.5",
    fill: "none",
    strokeLinecap: "round"
  }, /*#__PURE__*/React.createElement("rect", {
    x: "2",
    y: "5",
    width: "16",
    height: "10",
    rx: "2"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "14",
    cy: "10",
    r: "1.5"
  }), /*#__PURE__*/React.createElement("line", {
    x1: "4",
    y1: "10",
    x2: "10",
    y2: "10"
  }));
  const WifiIcon = () => /*#__PURE__*/React.createElement("svg", {
    width: "20",
    height: "20",
    viewBox: "0 0 20 20",
    stroke: "currentColor",
    strokeWidth: "1.5",
    fill: "none",
    strokeLinecap: "round"
  }, /*#__PURE__*/React.createElement("path", {
    d: "M2 8c4.4-4.4 11.6-4.4 16 0"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M5 11c2.8-2.8 7.2-2.8 10 0"
  }), /*#__PURE__*/React.createElement("path", {
    d: "M8 14c1.4-1.4 3.6-1.4 5 0"
  }), /*#__PURE__*/React.createElement("circle", {
    cx: "10",
    cy: "17",
    r: "1",
    fill: "currentColor",
    stroke: "none"
  }));
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      marginBottom: 'var(--space-6)'
    }
  }, /*#__PURE__*/React.createElement(Alert, {
    type: "error",
    title: "Export service down"
  }, "POST /api/export is returning 503. Investigating since 14:30.")), /*#__PURE__*/React.createElement("div", {
    className: "ra-cols-4",
    style: {
      marginBottom: 24
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-lg)',
      padding: 'var(--space-5)',
      boxShadow: 'var(--shadow-xs)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      marginBottom: 4
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-xs)',
      fontWeight: 600,
      textTransform: 'uppercase',
      letterSpacing: '.06em',
      color: 'var(--text-muted)'
    }
  }, "CPU Usage"), /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--color-warning-500)'
    }
  }, /*#__PURE__*/React.createElement(CpuIcon, null))), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-4xl)',
      fontWeight: 600,
      letterSpacing: 'var(--tracking-tight)'
    }
  }, "45%"), /*#__PURE__*/React.createElement(MetricBar, {
    value: 45,
    color: "var(--color-warning-500)"
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-xs)',
      color: 'var(--text-muted)',
      marginTop: 6
    }
  }, "4 cores \xB7 avg load 1.8")), /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-lg)',
      padding: 'var(--space-5)',
      boxShadow: 'var(--shadow-xs)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      marginBottom: 4
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-xs)',
      fontWeight: 600,
      textTransform: 'uppercase',
      letterSpacing: '.06em',
      color: 'var(--text-muted)'
    }
  }, "Memory"), /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--color-indigo-500)'
    }
  }, /*#__PURE__*/React.createElement(ServerIcon, null))), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-4xl)',
      fontWeight: 600,
      letterSpacing: 'var(--tracking-tight)'
    }
  }, "62%"), /*#__PURE__*/React.createElement(MetricBar, {
    value: 62,
    color: "var(--color-indigo-500)"
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-xs)',
      color: 'var(--text-muted)',
      marginTop: 6
    }
  }, "4.96 GB / 8 GB")), /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-lg)',
      padding: 'var(--space-5)',
      boxShadow: 'var(--shadow-xs)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      marginBottom: 4
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-xs)',
      fontWeight: 600,
      textTransform: 'uppercase',
      letterSpacing: '.06em',
      color: 'var(--text-muted)'
    }
  }, "Disk"), /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--color-success-600)'
    }
  }, /*#__PURE__*/React.createElement(HddIcon, null))), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-4xl)',
      fontWeight: 600,
      letterSpacing: 'var(--tracking-tight)'
    }
  }, "38%"), /*#__PURE__*/React.createElement(MetricBar, {
    value: 38,
    color: "var(--color-success-500)"
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-xs)',
      color: 'var(--text-muted)',
      marginTop: 6
    }
  }, "76 GB / 200 GB")), /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-lg)',
      padding: 'var(--space-5)',
      boxShadow: 'var(--shadow-xs)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      marginBottom: 4
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-xs)',
      fontWeight: 600,
      textTransform: 'uppercase',
      letterSpacing: '.06em',
      color: 'var(--text-muted)'
    }
  }, "Network"), /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--color-teal-500)'
    }
  }, /*#__PURE__*/React.createElement(WifiIcon, null))), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-4xl)',
      fontWeight: 600,
      letterSpacing: 'var(--tracking-tight)'
    }
  }, "1.2k"), /*#__PURE__*/React.createElement(MetricBar, {
    value: 12,
    color: "var(--color-teal-500)"
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-xs)',
      color: 'var(--text-muted)',
      marginTop: 6
    }
  }, "req/min \xB7 48ms avg"))), /*#__PURE__*/React.createElement("div", {
    className: "ra-cols-2"
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-lg)',
      overflow: 'hidden',
      boxShadow: 'var(--shadow-xs)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 'var(--space-4) var(--space-5)',
      borderBottom: '1px solid var(--border-color)',
      fontWeight: 600,
      fontSize: 'var(--text-md)'
    }
  }, "API Endpoints"), ENDPOINTS.map((ep, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      padding: 'var(--space-3) var(--space-5)',
      borderBottom: i < ENDPOINTS.length - 1 ? '1px solid var(--border-color)' : 'none',
      gap: 8
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-base)',
      fontFamily: 'var(--font-mono)',
      color: 'var(--text-primary)',
      overflow: 'hidden',
      textOverflow: 'ellipsis',
      whiteSpace: 'nowrap'
    }
  }, ep.name), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-xs)',
      color: 'var(--text-muted)',
      marginTop: 1
    }
  }, "Uptime ", ep.uptime)), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 8,
      flexShrink: 0
    }
  }, ep.latency && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-xs)',
      color: ep.latency > 500 ? 'var(--color-warning-600)' : 'var(--text-muted)',
      fontFamily: 'var(--font-mono)'
    }
  }, ep.latency, "ms"), /*#__PURE__*/React.createElement(Badge, {
    color: ep.status === 'ok' ? 'green' : ep.status === 'slow' ? 'yellow' : 'red',
    dot: true,
    pulse: ep.status === 'error'
  }, ep.status))))), /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-lg)',
      overflow: 'hidden',
      boxShadow: 'var(--shadow-xs)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 'var(--space-4) var(--space-5)',
      borderBottom: '1px solid var(--border-color)',
      fontWeight: 600,
      fontSize: 'var(--text-md)'
    }
  }, "System Logs"), LOGS.map((log, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    style: {
      display: 'flex',
      gap: 12,
      padding: 'var(--space-3) var(--space-5)',
      borderBottom: i < LOGS.length - 1 ? '1px solid var(--border-color)' : 'none',
      alignItems: 'flex-start'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-xs)',
      fontFamily: 'var(--font-mono)',
      color: 'var(--text-muted)',
      flexShrink: 0,
      marginTop: 1
    }
  }, log.ts), /*#__PURE__*/React.createElement(Badge, {
    size: "xs",
    color: log.level === 'error' ? 'red' : log.level === 'warn' ? 'yellow' : 'gray'
  }, log.level), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--text-primary)',
      flex: 1,
      lineHeight: 'var(--leading-snug)'
    }
  }, log.msg))))));
}
window.MonitoringScreen = Monitoring;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/rock_admin/screens/Monitoring.jsx", error: String((e && e.message) || e) }); }

// ui_kits/rock_admin/screens/ProductList.jsx
try { (() => {
const {
  Table,
  Badge,
  Button,
  PageHeader,
  Select,
  SearchSelect
} = window.RockAdminDesignSystem_fced52;
const PlusIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "13",
  height: "13",
  viewBox: "0 0 14 14",
  stroke: "currentColor",
  strokeWidth: "2.5",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("line", {
  x1: "7",
  y1: "1",
  x2: "7",
  y2: "13"
}), /*#__PURE__*/React.createElement("line", {
  x1: "1",
  y1: "7",
  x2: "13",
  y2: "7"
}));
const DownloadIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "13",
  height: "13",
  viewBox: "0 0 14 14",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("line", {
  x1: "7",
  y1: "1",
  x2: "7",
  y2: "9"
}), /*#__PURE__*/React.createElement("polyline", {
  points: "3,5 7,9 11,5"
}), /*#__PURE__*/React.createElement("line", {
  x1: "1",
  y1: "13",
  x2: "13",
  y2: "13"
}));
const EditIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "12",
  height: "12",
  viewBox: "0 0 14 14",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("path", {
  d: "M2,12l2-1 7-7-1-1-7,7-1,2z"
}));
const TrashIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "12",
  height: "12",
  viewBox: "0 0 14 14",
  stroke: "currentColor",
  strokeWidth: "2",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("polyline", {
  points: "2,3 12,3"
}), /*#__PURE__*/React.createElement("path", {
  d: "M5,3V2h4v1M3,3l1,9h6l1-9"
}));
const EyeIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "12",
  height: "12",
  viewBox: "0 0 14 14",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("ellipse", {
  cx: "7",
  cy: "7",
  rx: "6",
  ry: "3.5"
}), /*#__PURE__*/React.createElement("circle", {
  cx: "7",
  cy: "7",
  r: "2"
}));
const GridIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "14",
  height: "14",
  viewBox: "0 0 14 14",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("rect", {
  x: "1",
  y: "1",
  width: "5",
  height: "5",
  rx: "1"
}), /*#__PURE__*/React.createElement("rect", {
  x: "8",
  y: "1",
  width: "5",
  height: "5",
  rx: "1"
}), /*#__PURE__*/React.createElement("rect", {
  x: "1",
  y: "8",
  width: "5",
  height: "5",
  rx: "1"
}), /*#__PURE__*/React.createElement("rect", {
  x: "8",
  y: "8",
  width: "5",
  height: "5",
  rx: "1"
}));
const ListIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "14",
  height: "14",
  viewBox: "0 0 14 14",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("line", {
  x1: "1",
  y1: "3",
  x2: "13",
  y2: "3"
}), /*#__PURE__*/React.createElement("line", {
  x1: "1",
  y1: "7",
  x2: "13",
  y2: "7"
}), /*#__PURE__*/React.createElement("line", {
  x1: "1",
  y1: "11",
  x2: "13",
  y2: "11"
}));
const ALL_PRODUCTS = [{
  id: 1,
  name: 'AirMax Pro Sneakers',
  sku: 'SKU-001',
  category: 'Footwear',
  price: '€149.00',
  stock: 142,
  status: 'active',
  visibility: 'visible'
}, {
  id: 2,
  name: 'Leather Jacket Classic',
  sku: 'SKU-002',
  category: 'Clothing',
  price: '€389.00',
  stock: 23,
  status: 'active',
  visibility: 'visible'
}, {
  id: 3,
  name: 'Wireless Headphones X9',
  sku: 'SKU-003',
  category: 'Electronics',
  price: '€229.00',
  stock: 0,
  status: 'draft',
  visibility: 'hidden'
}, {
  id: 4,
  name: 'Running Shorts Elite',
  sku: 'SKU-004',
  category: 'Sports',
  price: '€59.00',
  stock: 88,
  status: 'active',
  visibility: 'visible'
}, {
  id: 5,
  name: 'Sports Water Bottle 1L',
  sku: 'SKU-005',
  category: 'Sports',
  price: '€24.00',
  stock: 204,
  status: 'active',
  visibility: 'visible'
}, {
  id: 6,
  name: 'Yoga Mat Premium',
  sku: 'SKU-006',
  category: 'Sports',
  price: '€79.00',
  stock: 7,
  status: 'active',
  visibility: 'visible'
}, {
  id: 7,
  name: 'Winter Parka Jacket',
  sku: 'SKU-007',
  category: 'Clothing',
  price: '€449.00',
  stock: 15,
  status: 'active',
  visibility: 'visible'
}, {
  id: 8,
  name: 'Bluetooth Speaker Mini',
  sku: 'SKU-008',
  category: 'Electronics',
  price: '€89.00',
  stock: 0,
  status: 'archived',
  visibility: 'hidden'
}];
const COLS = [{
  key: 'name',
  label: 'Product',
  sortable: true,
  render: (v, row) => /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 2
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 'var(--font-medium)',
      color: 'var(--text-primary)'
    }
  }, v), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-xs)',
      color: 'var(--text-muted)',
      fontFamily: 'var(--font-mono)'
    }
  }, row.sku))
}, {
  key: 'category',
  label: 'Category',
  render: v => /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--text-secondary)'
    }
  }, v)
}, {
  key: 'price',
  label: 'Price',
  sortable: true,
  align: 'right',
  render: v => /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 'var(--font-semibold)'
    }
  }, v)
}, {
  key: 'stock',
  label: 'Stock',
  align: 'right',
  render: v => /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 'var(--font-medium)',
      color: v === 0 ? 'var(--color-error-600)' : v < 10 ? 'var(--color-warning-600)' : 'var(--text-primary)'
    }
  }, v === 0 ? 'Out of stock' : v)
}, {
  key: 'status',
  label: 'Status',
  render: v => /*#__PURE__*/React.createElement(Badge, {
    color: v === 'active' ? 'green' : v === 'draft' ? 'gray' : 'red',
    dot: true
  }, v)
}, {
  key: 'visibility',
  label: 'Visibility',
  render: v => /*#__PURE__*/React.createElement(Badge, {
    color: v === 'visible' ? 'blue' : 'gray',
    size: "xs"
  }, v)
}, {
  key: '_actions',
  label: '',
  width: 100,
  render: (_, row) => /*#__PURE__*/React.createElement("div", {
    className: "ra-td-actions"
  }, /*#__PURE__*/React.createElement("button", {
    title: "Edit",
    style: {
      width: 26,
      height: 26,
      border: '1px solid var(--border-color)',
      borderRadius: 4,
      background: 'white',
      cursor: 'pointer',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      color: 'var(--text-muted)'
    }
  }, /*#__PURE__*/React.createElement(EditIcon, null)), /*#__PURE__*/React.createElement("button", {
    title: "Preview",
    style: {
      width: 26,
      height: 26,
      border: '1px solid var(--border-color)',
      borderRadius: 4,
      background: 'white',
      cursor: 'pointer',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      color: 'var(--text-muted)'
    }
  }, /*#__PURE__*/React.createElement(EyeIcon, null)), /*#__PURE__*/React.createElement("button", {
    title: "Delete",
    style: {
      width: 26,
      height: 26,
      border: '1px solid var(--border-color)',
      borderRadius: 4,
      background: 'white',
      cursor: 'pointer',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      color: 'var(--color-error-400)'
    }
  }, /*#__PURE__*/React.createElement(TrashIcon, null)))
}];
function ProductList({
  onNavigate
}) {
  const [search, setSearch] = React.useState('');
  const [selected, setSelected] = React.useState([]);
  const [sort, setSort] = React.useState({
    key: 'name',
    dir: 'asc'
  });
  const [page, setPage] = React.useState(1);
  const [statusFilter, setStatusFilter] = React.useState('');
  const filtered = ALL_PRODUCTS.filter(p => {
    if (search && !p.name.toLowerCase().includes(search.toLowerCase()) && !p.sku.toLowerCase().includes(search.toLowerCase())) return false;
    if (statusFilter && p.status !== statusFilter) return false;
    return true;
  });
  const sorted = [...filtered].sort((a, b) => {
    const av = a[sort.key] || '';
    const bv = b[sort.key] || '';
    return sort.dir === 'asc' ? String(av).localeCompare(String(bv)) : String(bv).localeCompare(String(av));
  });
  const perPage = 5;
  const totalPages = Math.ceil(sorted.length / perPage);
  const rows = sorted.slice((page - 1) * perPage, page * perPage);
  const toolbar = /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 8,
      alignItems: 'center'
    }
  }, /*#__PURE__*/React.createElement(Select, {
    options: [{
      value: '',
      label: 'All statuses'
    }, {
      value: 'active',
      label: 'Active'
    }, {
      value: 'draft',
      label: 'Draft'
    }, {
      value: 'archived',
      label: 'Archived'
    }],
    value: statusFilter,
    onChange: e => {
      setStatusFilter(e.target.value);
      setPage(1);
    },
    style: {
      width: 150
    }
  }), /*#__PURE__*/React.createElement(Select, {
    options: ['All categories', 'Footwear', 'Clothing', 'Electronics', 'Sports'],
    style: {
      width: 150
    }
  }));
  const toolbarRight = /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 4,
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-md)',
      overflow: 'hidden'
    }
  }, /*#__PURE__*/React.createElement("button", {
    style: {
      width: 30,
      height: 30,
      border: 'none',
      background: 'var(--color-primary-600)',
      cursor: 'pointer',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      color: 'white'
    }
  }, /*#__PURE__*/React.createElement(ListIcon, null)), /*#__PURE__*/React.createElement("button", {
    style: {
      width: 30,
      height: 30,
      border: 'none',
      background: 'transparent',
      cursor: 'pointer',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      color: 'var(--text-muted)'
    }
  }, /*#__PURE__*/React.createElement(GridIcon, null)));
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHeader, {
    title: "Products",
    description: "Manage your product catalog.",
    meta: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(Badge, {
      color: "green"
    }, ALL_PRODUCTS.filter(p => p.status === 'active').length, " active"), /*#__PURE__*/React.createElement(Badge, {
      color: "gray"
    }, ALL_PRODUCTS.filter(p => p.status === 'draft').length, " draft")),
    actions: /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      leftIcon: /*#__PURE__*/React.createElement(DownloadIcon, null),
      size: "sm"
    }, "Export"), /*#__PURE__*/React.createElement(Button, {
      leftIcon: /*#__PURE__*/React.createElement(PlusIcon, null),
      size: "sm",
      onClick: () => onNavigate && onNavigate('form-add')
    }, "New Product"))
  }), selected.length > 0 && /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 12,
      padding: '10px 16px',
      background: 'var(--color-primary-50)',
      border: '1px solid var(--color-primary-200)',
      borderRadius: 'var(--radius-md)',
      marginBottom: 12
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--color-primary-700)',
      fontWeight: 500
    }
  }, selected.length, " selected"), /*#__PURE__*/React.createElement("div", {
    style: {
      width: 1,
      height: 16,
      background: 'var(--color-primary-300)'
    }
  }), /*#__PURE__*/React.createElement("button", {
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--text-link)',
      background: 'none',
      border: 'none',
      cursor: 'pointer',
      padding: 0
    }
  }, "Set Active"), /*#__PURE__*/React.createElement("button", {
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--text-link)',
      background: 'none',
      border: 'none',
      cursor: 'pointer',
      padding: 0
    }
  }, "Archive"), /*#__PURE__*/React.createElement("button", {
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--color-error-600)',
      background: 'none',
      border: 'none',
      cursor: 'pointer',
      padding: 0
    }
  }, "Delete"), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1
    }
  }), /*#__PURE__*/React.createElement("button", {
    onClick: () => setSelected([]),
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--text-muted)',
      background: 'none',
      border: 'none',
      cursor: 'pointer',
      padding: 0
    }
  }, "\u2715 Clear")), /*#__PURE__*/React.createElement(Table, {
    columns: COLS,
    rows: rows,
    selectable: true,
    selectedIds: selected,
    onSelectChange: setSelected,
    searchValue: search,
    onSearchChange: v => {
      setSearch(v);
      setPage(1);
    },
    searchPlaceholder: "Search by name or SKU...",
    sortKey: sort.key,
    sortDir: sort.dir,
    onSortChange: (k, d) => setSort({
      key: k,
      dir: d
    }),
    toolbar: toolbar,
    toolbarRight: toolbarRight,
    pagination: {
      page,
      pages: totalPages,
      total: sorted.length,
      info: `${(page - 1) * perPage + 1}–${Math.min(page * perPage, sorted.length)} of ${sorted.length} products`,
      onChange: setPage
    }
  }));
}
window.ProductListScreen = ProductList;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/rock_admin/screens/ProductList.jsx", error: String((e && e.message) || e) }); }

// ui_kits/rock_admin/screens/Settings.jsx
try { (() => {
const {
  InpageSidebar,
  InpageLayout,
  FormField,
  Input,
  Select,
  Switch,
  Button,
  Alert
} = window.RockAdminDesignSystem_fced52;
const GearIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "14",
  height: "14",
  viewBox: "0 0 16 16",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("circle", {
  cx: "8",
  cy: "8",
  r: "2.5"
}), /*#__PURE__*/React.createElement("path", {
  d: "M8 1v2M8 13v2M1 8h2M13 8h2M3.2 3.2l1.4 1.4M11.4 11.4l1.4 1.4M3.2 12.8l1.4-1.4M11.4 4.6l1.4-1.4"
}));
const BellIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "14",
  height: "14",
  viewBox: "0 0 16 16",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("path", {
  d: "M8 2a5 5 0 00-5 5v3l-1.5 2h13L13 10V7a5 5 0 00-5-5z"
}), /*#__PURE__*/React.createElement("path", {
  d: "M6.5 14a1.5 1.5 0 003 0"
}));
const ShieldIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "14",
  height: "14",
  viewBox: "0 0 16 16",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("path", {
  d: "M8 2L3 4v4c0 3 2.5 5.5 5 6 2.5-.5 5-3 5-6V4L8 2z"
}));
const ApiIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "14",
  height: "14",
  viewBox: "0 0 16 16",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("path", {
  d: "M4 8h8M8 4v8"
}), /*#__PURE__*/React.createElement("rect", {
  x: "1",
  y: "1",
  width: "14",
  height: "14",
  rx: "2"
}));
const PayIcon = () => /*#__PURE__*/React.createElement("svg", {
  width: "14",
  height: "14",
  viewBox: "0 0 16 16",
  stroke: "currentColor",
  strokeWidth: "1.5",
  strokeLinecap: "round",
  fill: "none"
}, /*#__PURE__*/React.createElement("rect", {
  x: "1",
  y: "4",
  width: "14",
  height: "9",
  rx: "1"
}), /*#__PURE__*/React.createElement("line", {
  x1: "1",
  y1: "8",
  x2: "15",
  y2: "8"
}));
const SECTIONS = [{
  label: 'General',
  items: [{
    id: 'general',
    label: 'General',
    icon: /*#__PURE__*/React.createElement(GearIcon, null)
  }, {
    id: 'store',
    label: 'Store Settings',
    icon: /*#__PURE__*/React.createElement(GearIcon, null)
  }]
}, {
  label: 'Notifications',
  items: [{
    id: 'notifications',
    label: 'Email Alerts',
    icon: /*#__PURE__*/React.createElement(BellIcon, null)
  }, {
    id: 'webhooks',
    label: 'Webhooks',
    icon: /*#__PURE__*/React.createElement(ApiIcon, null)
  }]
}, {
  label: 'Security',
  items: [{
    id: 'security',
    label: 'Security',
    icon: /*#__PURE__*/React.createElement(ShieldIcon, null)
  }, {
    id: 'api',
    label: 'API Keys',
    icon: /*#__PURE__*/React.createElement(ApiIcon, null)
  }]
}, {
  label: 'Integrations',
  items: [{
    id: 'payment',
    label: 'Payments',
    icon: /*#__PURE__*/React.createElement(PayIcon, null)
  }, {
    id: 'shipping',
    label: 'Shipping',
    icon: /*#__PURE__*/React.createElement(ApiIcon, null)
  }]
}];
const CONTENT = {
  general: () => /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 20,
      maxWidth: 540
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h2", {
    style: {
      fontSize: 'var(--text-xl)',
      fontWeight: 'var(--font-semibold)',
      marginBottom: 4
    }
  }, "General Settings"), /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--text-muted)'
    }
  }, "Basic configuration for your store.")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: '1fr 1fr',
      gap: 16
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Store Name"
  }, /*#__PURE__*/React.createElement(Input, {
    defaultValue: "My E-shop"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Store URL"
  }, /*#__PURE__*/React.createElement(Input, {
    defaultValue: "https://myeshop.cz"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Default Language"
  }, /*#__PURE__*/React.createElement(Select, {
    options: ['Czech', 'English', 'German', 'Slovak'],
    defaultValue: "Czech"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Timezone"
  }, /*#__PURE__*/React.createElement(Select, {
    options: ['Europe/Prague', 'Europe/London', 'America/New_York'],
    defaultValue: "Europe/Prague"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Currency"
  }, /*#__PURE__*/React.createElement(Select, {
    options: ['CZK — Czech Koruna', 'EUR — Euro', 'USD — US Dollar'],
    defaultValue: "CZK \u2014 Czech Koruna"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Tax Mode"
  }, /*#__PURE__*/React.createElement(Select, {
    options: ['Prices include tax', 'Prices exclude tax']
  }))), /*#__PURE__*/React.createElement(FormField, {
    label: "Admin Email",
    hint: "Receives order notifications and system alerts"
  }, /*#__PURE__*/React.createElement(Input, {
    type: "email",
    defaultValue: "admin@myeshop.cz"
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 8,
      paddingTop: 4
    }
  }, /*#__PURE__*/React.createElement(Button, null, "Save Changes"), /*#__PURE__*/React.createElement(Button, {
    variant: "secondary"
  }, "Cancel"))),
  notifications: () => /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 20,
      maxWidth: 540
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h2", {
    style: {
      fontSize: 'var(--text-xl)',
      fontWeight: 'var(--font-semibold)',
      marginBottom: 4
    }
  }, "Email Notifications"), /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--text-muted)'
    }
  }, "Choose which events trigger email alerts.")), [['New order received', 'Send an email when a new order is placed', true], ['Order shipped', 'Notify when an order status changes to Shipped', true], ['Low stock alert', 'Alert when product stock falls below threshold', true], ['New customer registration', 'Notify on new account creation', false], ['Failed payment', 'Alert on failed payment attempts', true], ['Export completed', 'Notify when a data export is ready', false]].map(([label, desc, def]) => /*#__PURE__*/React.createElement("div", {
    key: label,
    style: {
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'space-between',
      padding: 'var(--space-3) var(--space-4)',
      background: 'var(--color-neutral-50)',
      borderRadius: 'var(--radius-md)',
      border: '1px solid var(--border-color)'
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-md)',
      fontWeight: 'var(--font-medium)'
    }
  }, label), /*#__PURE__*/React.createElement("div", {
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--text-muted)'
    }
  }, desc)), /*#__PURE__*/React.createElement(Switch, {
    defaultChecked: def
  }))), /*#__PURE__*/React.createElement(Button, {
    style: {
      alignSelf: 'flex-start'
    }
  }, "Save Changes")),
  security: () => /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 20,
      maxWidth: 540
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h2", {
    style: {
      fontSize: 'var(--text-xl)',
      fontWeight: 'var(--font-semibold)',
      marginBottom: 4
    }
  }, "Security"), /*#__PURE__*/React.createElement("p", {
    style: {
      fontSize: 'var(--text-sm)',
      color: 'var(--text-muted)'
    }
  }, "Manage authentication and access settings.")), /*#__PURE__*/React.createElement(Alert, {
    type: "info",
    title: "2FA is enabled"
  }, "Your account is protected with two-factor authentication."), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 16
    }
  }, /*#__PURE__*/React.createElement(FormField, {
    label: "Current Password"
  }, /*#__PURE__*/React.createElement(Input, {
    type: "password",
    placeholder: "\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "New Password"
  }, /*#__PURE__*/React.createElement(Input, {
    type: "password",
    placeholder: "Minimum 8 characters"
  })), /*#__PURE__*/React.createElement(FormField, {
    label: "Confirm Password"
  }, /*#__PURE__*/React.createElement(Input, {
    type: "password",
    placeholder: "Repeat new password"
  }))), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      fontWeight: 'var(--font-semibold)',
      marginBottom: 12
    }
  }, "Session Settings"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 10
    }
  }, /*#__PURE__*/React.createElement(Switch, {
    defaultChecked: true,
    label: "Auto-logout after 30 minutes of inactivity"
  }), /*#__PURE__*/React.createElement(Switch, {
    defaultChecked: false,
    label: "Allow concurrent sessions"
  }), /*#__PURE__*/React.createElement(Switch, {
    defaultChecked: true,
    label: "Log all admin actions to audit trail"
  }))), /*#__PURE__*/React.createElement(Button, {
    variant: "primary",
    style: {
      alignSelf: 'flex-start'
    }
  }, "Update Password"))
};
function Settings() {
  const [active, setActive] = React.useState('general');
  const wrap = {
    maxWidth: 'var(--content-medium-width)',
    margin: '0 auto'
  };
  const Content = CONTENT[active] || CONTENT['general'];
  return /*#__PURE__*/React.createElement("div", {
    style: wrap
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--bg-surface)',
      border: '1px solid var(--border-color)',
      borderRadius: 'var(--radius-lg)',
      overflow: 'hidden',
      boxShadow: 'var(--shadow-sm)',
      minHeight: 480
    }
  }, /*#__PURE__*/React.createElement(InpageLayout, {
    sidebar: /*#__PURE__*/React.createElement(InpageSidebar, {
      sections: SECTIONS,
      activeId: active,
      onSelect: setActive
    })
  }, /*#__PURE__*/React.createElement(Content, null))));
}
window.SettingsScreen = Settings;
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/rock_admin/screens/Settings.jsx", error: String((e && e.message) || e) }); }

__ds_ns.Button = __ds_scope.Button;

__ds_ns.IconButton = __ds_scope.IconButton;

__ds_ns.BarChart = __ds_scope.BarChart;

__ds_ns.DonutChart = __ds_scope.DonutChart;

__ds_ns.LineChart = __ds_scope.LineChart;

__ds_ns.StatCard = __ds_scope.StatCard;

__ds_ns.Avatar = __ds_scope.Avatar;

__ds_ns.AvatarGroup = __ds_scope.AvatarGroup;

__ds_ns.Badge = __ds_scope.Badge;

__ds_ns.Table = __ds_scope.Table;

__ds_ns.TreeView = __ds_scope.TreeView;

__ds_ns.Alert = __ds_scope.Alert;

__ds_ns.ConfirmModal = __ds_scope.ConfirmModal;

__ds_ns.Modal = __ds_scope.Modal;

__ds_ns.Offcanvas = __ds_scope.Offcanvas;

__ds_ns.ToastProvider = __ds_scope.ToastProvider;

__ds_ns.Toast = __ds_scope.Toast;

__ds_ns.Checkbox = __ds_scope.Checkbox;

__ds_ns.FormField = __ds_scope.FormField;

__ds_ns.Input = __ds_scope.Input;

__ds_ns.Radio = __ds_scope.Radio;

__ds_ns.RadioGroup = __ds_scope.RadioGroup;

__ds_ns.SearchSelect = __ds_scope.SearchSelect;

__ds_ns.Select = __ds_scope.Select;

__ds_ns.Switch = __ds_scope.Switch;

__ds_ns.Textarea = __ds_scope.Textarea;

__ds_ns.Card = __ds_scope.Card;

__ds_ns.Footer = __ds_scope.Footer;

__ds_ns.InpageSidebar = __ds_scope.InpageSidebar;

__ds_ns.InpageLayout = __ds_scope.InpageLayout;

__ds_ns.PageHeader = __ds_scope.PageHeader;

__ds_ns.Breadcrumb = __ds_scope.Breadcrumb;

__ds_ns.Dropdown = __ds_scope.Dropdown;

__ds_ns.SecondNavbar = __ds_scope.SecondNavbar;

__ds_ns.TopNavbar = __ds_scope.TopNavbar;

})();
