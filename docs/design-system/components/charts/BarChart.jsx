import React from 'react';

const PALETTE = ['var(--color-primary-500)','var(--color-indigo-500)','var(--color-teal-500)','var(--color-orange-500)'];

export function BarChart({ title, subtitle, labels = [], series = [], height = 220, horizontal = false, stacked = false, className = '' }) {
  const [tooltip, setTooltip] = React.useState(null);
  const padL = 44, padR = 16, padT = 8, padB = 36;
  const svgRef = React.useRef(null);
  const [w, setW] = React.useState(600);
  React.useEffect(() => {
    const ob = new ResizeObserver(e => setW(e[0].contentRect.width));
    if (svgRef.current) ob.observe(svgRef.current);
    return () => ob.disconnect();
  }, []);

  const n = labels.length;
  const cw = w - padL - padR; const ch = height - padT - padB;
  const maxV = Math.max(...series.flatMap(s => stacked
    ? series.reduce((acc, s) => { acc.forEach((v,i)=>{}); return acc; }, Array(n).fill(0))
    : s.data)) * 1.1 || 10;
  const groupW = cw / (n || 1);
  const barW = Math.max(4, Math.min(32, (groupW / (stacked ? 1 : series.length)) - 4));
  const fmt = v => v >= 1000 ? (v/1000).toFixed(1)+'k' : Math.round(v);
  const ticks = 5;

  return (
    <div className={['ra-chart', className].join(' ')}>
      <div className="ra-chart__header">
        <div><div className="ra-chart__title">{title}</div>{subtitle && <div className="ra-chart__subtitle">{subtitle}</div>}</div>
        <div className="ra-chart__legend">
          {series.map((s, i) => <div key={i} className="ra-chart__legend-item"><span className="ra-chart__legend-dot" style={{ background: s.color || PALETTE[i % PALETTE.length] }}/>{s.name}</div>)}
        </div>
      </div>
      <div ref={svgRef} style={{ position: 'relative' }}>
        <svg width="100%" height={height} viewBox={`0 0 ${w} ${height}`}>
          {Array.from({length: ticks}, (_, i) => {
            const v = (maxV * i) / (ticks - 1);
            const y = padT + ch - (v / maxV) * ch;
            return <g key={i}><line x1={padL} y1={y} x2={w-padR} y2={y} stroke="var(--color-neutral-200)" strokeWidth="1" strokeDasharray={i===0?'':'3,3'}/><text x={padL-6} y={y+4} textAnchor="end" fontSize="11" fill="var(--color-neutral-400)">{fmt(v)}</text></g>;
          })}
          {labels.map((l, li) => {
            const cx = padL + (li + 0.5) * groupW;
            return <text key={li} x={cx} y={height-8} textAnchor="middle" fontSize="11" fill="var(--color-neutral-400)">{l}</text>;
          })}
          {labels.map((l, li) => {
            const cx = padL + li * groupW;
            return series.map((s, si) => {
              const color = s.color || PALETTE[si % PALETTE.length];
              const v = s.data[li] || 0;
              const bh = (v / maxV) * ch;
              const bx = cx + (stacked ? (groupW - barW) / 2 : si * (barW + 2) + (groupW - series.length * (barW + 2)) / 2);
              const by = padT + ch - bh;
              return (
                <rect key={`${li}-${si}`} x={bx} y={by} width={barW} height={Math.max(bh, 0)} rx="2" fill={color} opacity="0.9"
                  style={{ cursor: 'pointer', transition: 'opacity 150ms' }}
                  onMouseEnter={() => setTooltip({ x: bx + barW/2, y: by, label: l, value: v, name: s.name })}
                  onMouseLeave={() => setTooltip(null)} />
              );
            });
          })}
        </svg>
        {tooltip && (
          <div className="ra-chart-tooltip" style={{ left: tooltip.x, top: tooltip.y }}>
            <strong>{tooltip.label}</strong> · {tooltip.name}: {fmt(tooltip.value)}
          </div>
        )}
      </div>
    </div>
  );
}
