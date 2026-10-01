
const { Badge, Card, Alert, StatCard } = window.RockAdminDesignSystem_fced52;

const ENDPOINTS = [
  { name: 'GET /api/products', status: 'ok', latency: 42, uptime: '99.99%' },
  { name: 'POST /api/orders', status: 'ok', latency: 78, uptime: '99.97%' },
  { name: 'GET /api/users', status: 'ok', latency: 31, uptime: '100%' },
  { name: 'POST /api/auth/login', status: 'ok', latency: 115, uptime: '100%' },
  { name: 'GET /api/analytics', status: 'slow', latency: 890, uptime: '99.1%' },
  { name: 'POST /api/export', status: 'error', latency: null, uptime: '94.2%' },
];

const LOGS = [
  { ts: '14:32:11', level: 'error', msg: 'POST /api/export — timeout after 30000ms', service: 'api' },
  { ts: '14:30:05', level: 'warn',  msg: 'GET /api/analytics — response time 890ms exceeds threshold', service: 'api' },
  { ts: '14:28:44', level: 'info',  msg: '1,284 active sessions', service: 'app' },
  { ts: '14:25:10', level: 'info',  msg: 'Scheduled backup completed — 2.3GB', service: 'cron' },
  { ts: '14:20:33', level: 'info',  msg: 'Cache cleared (products)', service: 'cache' },
];

function MetricBar({ value, color }) {
  return (
    <div style={{position:'relative',height:8,background:'var(--color-neutral-100)',borderRadius:4,overflow:'hidden',marginTop:6}}>
      <div style={{position:'absolute',left:0,top:0,height:'100%',width:value+'%',background:color,borderRadius:4,transition:'width .5s ease'}} />
    </div>
  );
}

function Monitoring() {
  const ServerIcon = () => <svg width="20" height="20" viewBox="0 0 20 20" stroke="currentColor" strokeWidth="1.5" fill="none" strokeLinecap="round"><rect x="2" y="3" width="16" height="5" rx="1"/><rect x="2" y="11" width="16" height="5" rx="1"/><circle cx="6" cy="5.5" r="1" fill="currentColor" stroke="none"/><circle cx="6" cy="13.5" r="1" fill="currentColor" stroke="none"/></svg>;
  const CpuIcon = () => <svg width="20" height="20" viewBox="0 0 20 20" stroke="currentColor" strokeWidth="1.5" fill="none" strokeLinecap="round"><rect x="5" y="5" width="10" height="10" rx="1"/><path d="M7 2v3M10 2v3M13 2v3M7 15v3M10 15v3M13 15v3M2 7h3M2 10h3M2 13h3M15 7h3M15 10h3M15 13h3"/></svg>;
  const HddIcon = () => <svg width="20" height="20" viewBox="0 0 20 20" stroke="currentColor" strokeWidth="1.5" fill="none" strokeLinecap="round"><rect x="2" y="5" width="16" height="10" rx="2"/><circle cx="14" cy="10" r="1.5"/><line x1="4" y1="10" x2="10" y2="10"/></svg>;
  const WifiIcon = () => <svg width="20" height="20" viewBox="0 0 20 20" stroke="currentColor" strokeWidth="1.5" fill="none" strokeLinecap="round"><path d="M2 8c4.4-4.4 11.6-4.4 16 0"/><path d="M5 11c2.8-2.8 7.2-2.8 10 0"/><path d="M8 14c1.4-1.4 3.6-1.4 5 0"/><circle cx="10" cy="17" r="1" fill="currentColor" stroke="none"/></svg>;

  return (
    <div>
      <div style={{marginBottom:'var(--space-6)'}}>
        <Alert type="error" title="Export service down">POST /api/export is returning 503. Investigating since 14:30.</Alert>
      </div>
      <div className="ra-cols-4" style={{marginBottom:24}}>
        <div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-lg)',padding:'var(--space-5)',boxShadow:'var(--shadow-xs)'}}>
          <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:4}}>
            <span style={{fontSize:'var(--text-xs)',fontWeight:600,textTransform:'uppercase',letterSpacing:'.06em',color:'var(--text-muted)'}}>CPU Usage</span>
            <span style={{color:'var(--color-warning-500)'}}><CpuIcon /></span>
          </div>
          <div style={{fontSize:'var(--text-4xl)',fontWeight:600,letterSpacing:'var(--tracking-tight)'}}>45%</div>
          <MetricBar value={45} color="var(--color-warning-500)" />
          <div style={{fontSize:'var(--text-xs)',color:'var(--text-muted)',marginTop:6}}>4 cores · avg load 1.8</div>
        </div>
        <div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-lg)',padding:'var(--space-5)',boxShadow:'var(--shadow-xs)'}}>
          <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:4}}>
            <span style={{fontSize:'var(--text-xs)',fontWeight:600,textTransform:'uppercase',letterSpacing:'.06em',color:'var(--text-muted)'}}>Memory</span>
            <span style={{color:'var(--color-indigo-500)'}}><ServerIcon /></span>
          </div>
          <div style={{fontSize:'var(--text-4xl)',fontWeight:600,letterSpacing:'var(--tracking-tight)'}}>62%</div>
          <MetricBar value={62} color="var(--color-indigo-500)" />
          <div style={{fontSize:'var(--text-xs)',color:'var(--text-muted)',marginTop:6}}>4.96 GB / 8 GB</div>
        </div>
        <div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-lg)',padding:'var(--space-5)',boxShadow:'var(--shadow-xs)'}}>
          <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:4}}>
            <span style={{fontSize:'var(--text-xs)',fontWeight:600,textTransform:'uppercase',letterSpacing:'.06em',color:'var(--text-muted)'}}>Disk</span>
            <span style={{color:'var(--color-success-600)'}}><HddIcon /></span>
          </div>
          <div style={{fontSize:'var(--text-4xl)',fontWeight:600,letterSpacing:'var(--tracking-tight)'}}>38%</div>
          <MetricBar value={38} color="var(--color-success-500)" />
          <div style={{fontSize:'var(--text-xs)',color:'var(--text-muted)',marginTop:6}}>76 GB / 200 GB</div>
        </div>
        <div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-lg)',padding:'var(--space-5)',boxShadow:'var(--shadow-xs)'}}>
          <div style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:4}}>
            <span style={{fontSize:'var(--text-xs)',fontWeight:600,textTransform:'uppercase',letterSpacing:'.06em',color:'var(--text-muted)'}}>Network</span>
            <span style={{color:'var(--color-teal-500)'}}><WifiIcon /></span>
          </div>
          <div style={{fontSize:'var(--text-4xl)',fontWeight:600,letterSpacing:'var(--tracking-tight)'}}>1.2k</div>
          <MetricBar value={12} color="var(--color-teal-500)" />
          <div style={{fontSize:'var(--text-xs)',color:'var(--text-muted)',marginTop:6}}>req/min · 48ms avg</div>
        </div>
      </div>
      <div className="ra-cols-2">
        {/* API Endpoints */}
        <div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-lg)',overflow:'hidden',boxShadow:'var(--shadow-xs)'}}>
          <div style={{padding:'var(--space-4) var(--space-5)',borderBottom:'1px solid var(--border-color)',fontWeight:600,fontSize:'var(--text-md)'}}>API Endpoints</div>
          {ENDPOINTS.map((ep, i) => (
            <div key={i} style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'var(--space-3) var(--space-5)',borderBottom: i<ENDPOINTS.length-1?'1px solid var(--border-color)':'none',gap:8}}>
              <div style={{flex:1,minWidth:0}}>
                <div style={{fontSize:'var(--text-base)',fontFamily:'var(--font-mono)',color:'var(--text-primary)',overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap'}}>{ep.name}</div>
                <div style={{fontSize:'var(--text-xs)',color:'var(--text-muted)',marginTop:1}}>Uptime {ep.uptime}</div>
              </div>
              <div style={{display:'flex',alignItems:'center',gap:8,flexShrink:0}}>
                {ep.latency && <span style={{fontSize:'var(--text-xs)',color:ep.latency>500?'var(--color-warning-600)':'var(--text-muted)',fontFamily:'var(--font-mono)'}}>{ep.latency}ms</span>}
                <Badge color={ep.status==='ok'?'green':ep.status==='slow'?'yellow':'red'} dot pulse={ep.status==='error'}>
                  {ep.status}
                </Badge>
              </div>
            </div>
          ))}
        </div>
        {/* System Logs */}
        <div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-lg)',overflow:'hidden',boxShadow:'var(--shadow-xs)'}}>
          <div style={{padding:'var(--space-4) var(--space-5)',borderBottom:'1px solid var(--border-color)',fontWeight:600,fontSize:'var(--text-md)'}}>System Logs</div>
          {LOGS.map((log, i) => (
            <div key={i} style={{display:'flex',gap:12,padding:'var(--space-3) var(--space-5)',borderBottom:i<LOGS.length-1?'1px solid var(--border-color)':'none',alignItems:'flex-start'}}>
              <span style={{fontSize:'var(--text-xs)',fontFamily:'var(--font-mono)',color:'var(--text-muted)',flexShrink:0,marginTop:1}}>{log.ts}</span>
              <Badge size="xs" color={log.level==='error'?'red':log.level==='warn'?'yellow':'gray'}>{log.level}</Badge>
              <span style={{fontSize:'var(--text-sm)',color:'var(--text-primary)',flex:1,lineHeight:'var(--leading-snug)'}}>{log.msg}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
window.MonitoringScreen = Monitoring;
