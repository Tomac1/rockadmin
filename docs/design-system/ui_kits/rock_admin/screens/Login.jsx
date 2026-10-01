
const { Button, Input, Checkbox, FormField, Alert } = window.RockAdminDesignSystem_fced52;

function Login({ onLogin }) {
  const [email, setEmail] = React.useState('admin@company.cz');
  const [pw, setPw] = React.useState('');
  const [err, setErr] = React.useState('');
  const [loading, setLoading] = React.useState(false);
  const [showPw, setShowPw] = React.useState(false);

  const EyeIcon = () => <svg width="14" height="14" viewBox="0 0 16 16" stroke="currentColor" strokeWidth="1.5" fill="none" strokeLinecap="round"><ellipse cx="8" cy="8" rx="7" ry="4"/><circle cx="8" cy="8" r="2"/></svg>;

  const submit = (e) => {
    e.preventDefault();
    if (!email || !pw) { setErr('Please fill in all fields.'); return; }
    setLoading(true); setErr('');
    setTimeout(() => { setLoading(false); onLogin && onLogin(); }, 1000);
  };

  return (
    <div style={{minHeight:'100vh',background:'var(--bg-page)',display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',padding:'var(--space-6)'}}>
      <div style={{width:'100%',maxWidth:400}}>
        {/* Logo */}
        <div style={{display:'flex',alignItems:'center',justifyContent:'center',gap:10,marginBottom:32}}>
          <div style={{width:36,height:36,background:'var(--color-primary-600)',borderRadius:8,display:'flex',alignItems:'center',justifyContent:'center'}}>
            <svg width="20" height="20" viewBox="0 0 16 16" fill="none"><rect x="1" y="1" width="6" height="6" rx="1.5" fill="white" opacity="0.95"/><rect x="9" y="1" width="6" height="6" rx="1.5" fill="white" opacity="0.55"/><rect x="1" y="9" width="6" height="6" rx="1.5" fill="white" opacity="0.55"/><rect x="9" y="9" width="6" height="6" rx="1.5" fill="white" opacity="0.25"/></svg>
          </div>
          <div>
            <div style={{fontWeight:700,fontSize:'var(--text-xl)',color:'var(--text-primary)',letterSpacing:'var(--tracking-tight)'}}>Rock Admin</div>
          </div>
        </div>
        {/* Card */}
        <div style={{background:'var(--bg-surface)',border:'1px solid var(--border-color)',borderRadius:'var(--radius-xl)',padding:'var(--space-8)',boxShadow:'var(--shadow-lg)'}}>
          <h1 style={{fontSize:'var(--text-2xl)',fontWeight:'var(--font-semibold)',color:'var(--text-primary)',marginBottom:4,letterSpacing:'var(--tracking-tight)'}}>Sign in</h1>
          <p style={{fontSize:'var(--text-md)',color:'var(--text-muted)',marginBottom:'var(--space-6)'}}>Welcome back to your admin panel</p>
          {err && <div style={{marginBottom:16}}><Alert type="error" size="sm">{err}</Alert></div>}
          <form onSubmit={submit} style={{display:'flex',flexDirection:'column',gap:16}}>
            <FormField label="Email address">
              <Input type="email" value={email} onChange={e=>setEmail(e.target.value)} placeholder="you@company.cz" autoComplete="email" />
            </FormField>
            <FormField label="Password">
              <Input type={showPw?'text':'password'} value={pw} onChange={e=>setPw(e.target.value)} placeholder="Your password" autoComplete="current-password"
                rightIcon={<EyeIcon />} onRightIconClick={()=>setShowPw(v=>!v)} />
            </FormField>
            <div style={{display:'flex',alignItems:'center',justifyContent:'space-between'}}>
              <Checkbox label="Remember me" defaultChecked />
              <a href="#" style={{fontSize:'var(--text-sm)',color:'var(--text-link)'}}>Forgot password?</a>
            </div>
            <Button type="submit" fullWidth size="lg" loading={loading}>Sign in</Button>
          </form>
        </div>
        <p style={{textAlign:'center',marginTop:20,fontSize:'var(--text-sm)',color:'var(--text-muted)'}}>Rock Admin © 2025 — <a href="#" style={{color:'var(--text-link)'}}>Privacy Policy</a></p>
      </div>
    </div>
  );
}
window.LoginScreen = Login;
