import React from 'react';

export function ConfirmModal({ open, onClose, onConfirm, title = 'Are you sure?', message, confirmLabel = 'Confirm', cancelLabel = 'Cancel', variant = 'danger', loading = false }) {
  if (!open) return null;
  const { Modal } = window.RockAdmin || {};
  const { Button } = window.RockAdmin || {};
  return (
    <div style={{ position:'fixed',inset:0,background:'rgba(0,0,0,0.45)',backdropFilter:'blur(2px)',display:'flex',alignItems:'center',justifyContent:'center',zIndex:'var(--z-modal)',padding:'var(--space-4)' }} onClick={e => e.target === e.currentTarget && onClose && onClose()}>
      <div style={{ background:'var(--bg-surface)',borderRadius:'var(--radius-modal)',boxShadow:'var(--shadow-modal)',width:'100%',maxWidth:'420px',padding:'var(--space-6)',display:'flex',flexDirection:'column',gap:'var(--space-4)' }}>
        <div style={{ display:'flex',gap:'var(--space-3)',alignItems:'flex-start' }}>
          <div style={{ width:40,height:40,borderRadius:'50%',display:'flex',alignItems:'center',justifyContent:'center',flexShrink:0, background: variant==='danger' ? 'var(--color-error-50)' : 'var(--color-warning-50)' }}>
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke={variant==='danger'?'var(--color-error-600)':'var(--color-warning-600)'} strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
              <path d="M10 4v6M10 14.5v.5" strokeWidth="2"/>
              <circle cx="10" cy="10" r="8.5"/>
            </svg>
          </div>
          <div>
            <div style={{ fontWeight:'var(--font-semibold)',fontSize:'var(--text-lg)',marginBottom:'var(--space-1)',color:'var(--text-primary)' }}>{title}</div>
            {message && <div style={{ fontSize:'var(--text-md)',color:'var(--text-secondary)',lineHeight:'var(--leading-snug)' }}>{message}</div>}
          </div>
        </div>
        <div style={{ display:'flex',gap:'var(--space-3)',justifyContent:'flex-end' }}>
          <button type="button" onClick={onClose} style={{ height:34,padding:'0 14px',background:'var(--bg-surface)',border:'1px solid var(--border-color-strong)',borderRadius:'var(--radius-btn)',cursor:'pointer',fontFamily:'var(--font-sans)',fontSize:'var(--text-base)',color:'var(--text-primary)',fontWeight:'var(--font-medium)',boxShadow:'var(--shadow-xs)' }}>{cancelLabel}</button>
          <button type="button" onClick={onConfirm} disabled={loading} style={{ height:34,padding:'0 14px',background:variant==='danger'?'var(--color-error-600)':'var(--color-warning-500)',border:'none',borderRadius:'var(--radius-btn)',cursor:'pointer',color:'white',fontFamily:'var(--font-sans)',fontSize:'var(--text-base)',fontWeight:'var(--font-medium)',opacity:loading?0.6:1 }}>{loading ? 'Processing...' : confirmLabel}</button>
        </div>
      </div>
    </div>
  );
}
