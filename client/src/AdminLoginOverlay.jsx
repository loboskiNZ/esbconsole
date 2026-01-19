import React, { useState, useEffect } from 'react';
import { useMsal } from "@azure/msal-react";
import { loginRequest } from "./authConfig";
import { Lock, Server, Activity, Wifi, ShieldCheck, AlertCircle, RefreshCw } from 'lucide-react';
import axios from 'axios';

const AdminLoginOverlay = ({ onLogin }) => {
    const { instance, accounts } = useMsal();
    
    // States: 'locked' | 'authenticating' | 'checking_health' | 'reset_flow'
    const [status, setStatus] = useState('locked'); 
    const [password, setPassword] = useState('');
    const [error, setError] = useState(null);
    const [health, setHealth] = useState({
        api: 'pending',
        x32: 'pending',
        ableton: 'pending'
    });

    const handleUnlock = async (e) => {
        if (e) e.preventDefault();
        setError(null);
        setStatus('authenticating');

        try {
            // Verify Password with Backend
            const res = await axios.post('/api/admin/unlock', { password });
            if (res.data.success) {
                // Success -> Start Health Check
                startHealthCheck();
            } else {
                setStatus('locked');
                setError("Incorrect Password");
            }
        } catch (err) {
            setStatus('locked');
            setError("Authentication Failed");
            console.error(err);
        }
    };

    const startHealthCheck = async () => {
        setStatus('checking_health');
        
        try {
            const res = await axios.get('/api/system/health');
            const data = res.data;
            
            setHealth({
                api: 'ok',
                x32: data.x32 ? 'ok' : 'fail',
                ableton: data.ableton ? 'ok' : (data.abletonActive ? 'ok' : 'warn')
            });

            // Delay slightly to show the "Green" state before dismissing
            setTimeout(() => {
                onLogin();
            }, 1000);

        } catch (err) {
            console.error("Health Check Failed", err);
            setHealth({ api: 'fail', x32: 'fail', ableton: 'fail' });
        }
    };

    const handleResetPassword = async () => {
        setError(null);
        try {
            const loginResponse = await instance.loginPopup(loginRequest);
            const account = loginResponse.account;
            
            // Check Admin Identity
            if (account.username.toLowerCase() === 'ed@loboski.nz') {
                setStatus('reset_flow');
                // Auto-trigger backend reset
                await triggerBackendReset(account.username);
            } else {
                setError("Unauthorized: Only ed@loboski.nz can reset the admin password.");
                await instance.logoutPopup(); // Force logout so they can try again if wrong account
            }
        } catch (e) {
            console.error(e);
            setError("Microsoft Login Failed");
        }
    };

    const triggerBackendReset = async (email) => {
        try {
            const res = await axios.post('/api/auth/reset-password', { email });
            if (res.data.success) {
                alert("Success! A new password has been emailed to " + email);
                setStatus('locked');
                setPassword(''); // Clear fields
            } else {
                setError("Reset Failed: " + res.data.error);
                setStatus('locked');
            }
        } catch (e) {
            setError("System Error during reset.");
            setStatus('locked');
        }
    };

    const StatusIcon = ({ status }) => {
        if (status === 'ok') return <span style={{color:'#0f0'}}>● OK</span>;
        if (status === 'fail') return <span style={{color:'#f00'}}>● OFF</span>;
        if (status === 'warn') return <span style={{color:'#fa0'}}>● IDLE</span>;
        return <span style={{color:'#666'}}>● ...</span>;
    };

    return (
        <div style={{
            position: 'fixed', top: 0, left: 0, width: '100vw', height: '100vh',
            background: 'black', zIndex: 9999,
            display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
            color: 'white'
        }}>
            {/* BRANDING */}
            <img src="/esb_logo.png" alt="ESB" style={{height: '100px', marginBottom: '20px'}} />
            <h1 style={{
                fontFamily: "'Orbitron', sans-serif", 
                letterSpacing: '4px', margin: '0 0 40px 0',
                textShadow: '0 0 20px rgba(0,255,100,0.5)'
            }}>CONSOLE ADMIN</h1>

            {/* MAIN CONTENT AREA */}
            <div style={{
                width: '350px', background: '#111', border: '1px solid #333', borderRadius: '8px',
                padding: '30px', boxShadow: '0 10px 40px rgba(0,0,0,0.5)', position: 'relative'
            }}>
                
                {status === 'checking_health' ? (
                    <div style={{display:'flex', flexDirection:'column', gap:'15px'}}>
                        <h3 style={{marginTop:0, borderBottom:'1px solid #333', paddingBottom:'10px'}}>System Health Check</h3>
                        
                        <div style={{display:'flex', justifyContent:'space-between', alignItems:'center'}}>
                            <div style={{display:'flex', gap:'10px', alignItems:'center'}}><Server size={18}/> Server API</div>
                            <StatusIcon status={health.api} />
                        </div>
                        <div style={{display:'flex', justifyContent:'space-between', alignItems:'center'}}>
                            <div style={{display:'flex', gap:'10px', alignItems:'center'}}><Wifi size={18}/> X32 Connection</div>
                            <StatusIcon status={health.x32} />
                        </div>
                        <div style={{display:'flex', justifyContent:'space-between', alignItems:'center'}}>
                            <div style={{display:'flex', gap:'10px', alignItems:'center'}}><Activity size={18}/> Ableton Link</div>
                            <StatusIcon status={health.ableton} />
                        </div>

                        <div style={{marginTop:'20px', textAlign:'center', color:'#666', fontSize:'0.8em'}}>
                            Verifying critical ports...
                        </div>
                    </div>
                ) : (
                    <form onSubmit={handleUnlock}>
                        <div style={{marginBottom: '20px'}}>
                            <label style={{display:'block', color:'#888', marginBottom:'8px', fontSize:'0.8em'}}>ADMIN PASSWORD</label>
                            <div style={{display:'flex', alignItems:'center', background:'#222', border: error ? '1px solid red' : '1px solid #444', borderRadius:'4px', padding:'0 10px'}}>
                                <Lock size={16} color="#666" />
                                <input 
                                    type="password" 
                                    value={password}
                                    onChange={e => setPassword(e.target.value)}
                                    autoFocus
                                    style={{
                                        background:'transparent', border:'none', color:'white', 
                                        padding:'10px', width:'100%', outline:'none', fontSize:'1.1em'
                                    }}
                                    placeholder="••••••"
                                    disabled={status !== 'locked'}
                                />
                            </div>
                            {error && <div style={{color:'red', fontSize:'0.8em', marginTop:'5px', display:'flex', gap:'5px', alignItems:'center'}}><AlertCircle size={12}/> {error}</div>}
                        </div>

                        <button 
                            type="submit"
                            disabled={status !== 'locked' || !password}
                            style={{
                                width: '100%', padding: '12px', borderRadius: '4px',
                                background: status === 'authenticating' ? '#333' : '#0078d4', 
                                color: 'white', border: 'none', fontWeight: 'bold', cursor: 'pointer',
                                transition: 'all 0.2s'
                            }}
                        >
                            {status === 'authenticating' || status === 'reset_flow' ? 'PROCESSING...' : 'UNLOCK CONSOLE'}
                        </button>

                        <div style={{marginTop: '20px', textAlign: 'center'}}>
                            <button 
                                type="button"
                                onClick={handleResetPassword}
                                style={{
                                    background:'none', border:'none', color:'#666', 
                                    textDecoration:'underline', cursor:'pointer', fontSize:'0.8em'
                                }}
                            >
                                Forgot Password? (Log in with Microsoft)
                            </button>
                        </div>
                    </form>
                )}

                {/* Loading Spinner Overlay if Resetting */}
                {status === 'reset_flow' && (
                    <div style={{
                        position:'absolute', top:0, left:0, right:0, bottom:0, 
                        background:'rgba(0,0,0,0.8)', display:'flex', flexDirection:'column', 
                        alignItems:'center', justifyContent:'center', borderRadius:'8px'
                    }}>
                        <RefreshCw size={32} className="spin" style={{marginBottom:'10px', color:'#0078d4'}} />
                        <div>Resetting Password...</div>
                    </div>
                )}
            </div>

            <div style={{marginTop: '40px', color: '#444', fontSize: '0.8em'}}>
                <ShieldCheck size={14} style={{verticalAlign:'middle', marginRight:'5px'}}/> 
                SECURE SESSIONS • SYSTEM v{__APP_VERSION__}
            </div>
            
            <style>{`
                .spin { animation: spin 1s linear infinite; }
                @keyframes spin { 100% { transform: rotate(360deg); } }
            `}</style>
        </div>
    );
};

export default AdminLoginOverlay;
