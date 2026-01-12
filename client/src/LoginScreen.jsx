import React, { useState } from 'react';
import axios from 'axios';

const LoginScreen = ({ onLoginSuccess }) => {
    const [step, setStep] = useState(0); // 0: Splash, 1: Email, 2: Password
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [showForgot, setShowForgot] = useState(false);

    // Splash Timer
    React.useEffect(() => {
        const timer = setTimeout(() => {
            setStep(1);
        }, 2000);
        return () => clearTimeout(timer);
    }, []);

    const handleEmailSubmit = (e) => {
        e.preventDefault();
        if (email.trim().length > 3) {
            setError('');
            setStep(2);
        } else {
            setError("Please enter a valid email");
        }
    };

    const handleLogin = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);

        try {
            const res = await axios.post('/api/login', { email, password });
            const { token, musician } = res.data;
            
            // Persist
            localStorage.setItem('musician_token', token);
            localStorage.setItem('musician_user', JSON.stringify(musician));
             
            if (onLoginSuccess) onLoginSuccess(musician);

        } catch (err) {
            console.error(err);
            // Error handling per spec: Go back to username screen
            setError(err.response?.data?.error || "Login Failed");
            setLoading(false);
            setStep(1); // Back to Email
            setShowForgot(true); // Show link
            setPassword(''); // Clear password
        } 
    };

    return (
        <div style={{
            position: 'fixed', top: 0, left: 0, width: '100vw', height: '100vh',
            background: 'black', 
            backgroundImage: 'url(/login_bg.png)', backgroundSize: 'cover', backgroundPosition: 'center',
            display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
            zIndex: 9999, color: 'white', fontFamily: 'sans-serif'
        }}>
            {/* Dark Overlay */}
            <div style={{
                position:'absolute', top:0, left:0, right:0, bottom:0, 
                background:'rgba(0,0,0,0.6)', backdropFilter: 'blur(3px)'
            }} />

            <div style={{position:'relative', zIndex:10, width:'100%', maxWidth:'400px', padding:'20px', textAlign:'center'}}>
                
                {/* Logo */}
                <img src="/esb_logo.png" alt="ESB" style={{height:'80px', marginBottom:'20px', filter:'drop-shadow(0 0 10px rgba(0,136,255,0.5))'}} />

                {/* SPLASH STEP */}
                <div style={{
                    transition: 'opacity 1s ease',
                    opacity: step === 0 ? 1 : 0,
                    height: step === 0 ? 'auto' : 0,
                    overflow: 'hidden',
                    pointerEvents: step === 0 ? 'all' : 'none'
                }}>
                    <h2 style={{fontWeight:'300', letterSpacing:'1px'}}>ESB Studio is getting ready for you...</h2>
                </div>

                {/* FORM CONTAINER */}
                <div style={{
                    transition: 'opacity 0.5s ease, transform 0.5s ease',
                    opacity: step > 0 ? 1 : 0,
                    transform: step > 0 ? 'translateY(0)' : 'translateY(20px)',
                    pointerEvents: step > 0 ? 'all' : 'none',
                    height: step > 0 ? 'auto' : 0,
                    overflow: 'visible'
                }}>

                    {error && <div style={{
                        background:'rgba(255, 50, 50, 0.8)', color:'white', padding:'10px', 
                        borderRadius:'6px', marginBottom:'15px', border: '1px solid #f00',
                        fontSize:'0.9em'
                    }}>{error}</div>}

                    {/* Step 1: EMAIL */}
                    {step === 1 && (
                        <form onSubmit={handleEmailSubmit} style={{display:'flex', flexDirection:'column', gap:'15px'}}>
                           <div style={{textAlign:'left'}}>
                                <label style={{display:'block', marginBottom:'5px', color:'#ccc', fontSize:'0.9em', textTransform:'uppercase', letterSpacing:'1px'}}>Email Address</label>
                                <div style={{display:'flex', gap:'10px'}}>
                                    <input 
                                        type="email" 
                                        value={email} 
                                        onChange={e => setEmail(e.target.value)}
                                        placeholder="Enter your email"
                                        required
                                        autoFocus
                                        style={{
                                            flex: 1, padding:'12px', borderRadius:'6px', border:'1px solid #555', 
                                            background:'rgba(0,0,0,0.5)', color:'white', fontSize:'1.1em', boxSizing: 'border-box',
                                            outline: 'none', transition: 'border-color 0.2s'
                                        }}
                                        onFocus={e => e.target.style.borderColor = '#0088ff'}
                                        onBlur={e => e.target.style.borderColor = '#555'}
                                    />
                                    <button type="submit" style={{
                                        background: '#0088ff', border:'none', borderRadius:'6px', width:'50px',
                                        color:'white', fontSize:'1.5em', cursor:'pointer', display:'flex', alignItems:'center', justifyContent:'center',
                                        transition: 'background 0.2s'
                                    }}
                                    onMouseOver={e => e.target.style.background = '#0066cc'}
                                    onMouseOut={e => e.target.style.background = '#0088ff'}
                                    >
                                        →
                                    </button>
                                </div>
                            </div>
                            
                            {showForgot && (
                                <div style={{marginTop:'10px'}}>
                                    <a href="#" style={{color:'#888', textDecoration:'underline', fontSize:'0.9em'}} onClick={e => e.preventDefault()}>Forgotten Username?</a>
                                </div>
                            )}
                        </form>
                    )}

                    {/* Step 2: PASSWORD */}
                    {step === 2 && (
                        <form onSubmit={handleLogin} style={{display:'flex', flexDirection:'column', gap:'15px'}}>
                             {/* User Info Header */}
                             <div style={{background:'rgba(255,255,255,0.1)', padding:'10px', borderRadius:'6px', display:'flex', alignItems:'center', gap:'10px', marginBottom:'5px'}}>
                                 <div style={{width:'30px', height:'30px', background:'#555', borderRadius:'50%', display:'flex', alignItems:'center', justifyContent:'center'}}>👤</div>
                                 <span style={{fontSize:'0.9em', color:'#ddd'}}>{email}</span>
                                 <span 
                                    style={{marginLeft:'auto', color:'#aaa', fontSize:'0.8em', cursor:'pointer', textDecoration:'underline'}}
                                    onClick={() => setStep(1)}
                                 >Change</span>
                             </div>

                             <div style={{textAlign:'left'}}>
                                <label style={{display:'block', marginBottom:'5px', color:'#ccc', fontSize:'0.9em', textTransform:'uppercase', letterSpacing:'1px'}}>Password</label>
                                <input 
                                    type="password" 
                                    value={password} 
                                    onChange={e => setPassword(e.target.value)}
                                    placeholder="Studio Password"
                                    required
                                    autoFocus
                                    style={{
                                        width:'100%', padding:'12px', borderRadius:'6px', border:'1px solid #555', 
                                        background:'rgba(0,0,0,0.5)', color:'white', fontSize:'1.1em', boxSizing: 'border-box',
                                        outline: 'none', transition: 'border-color 0.2s', textAlign:'center', letterSpacing:'3px'
                                    }}
                                    onFocus={e => e.target.style.borderColor = '#0088ff'}
                                    onBlur={e => e.target.style.borderColor = '#555'}
                                />
                            </div>

                            <button 
                                type="submit" 
                                disabled={loading}
                                style={{
                                    marginTop:'10px', padding:'15px', background: loading ? '#555' : '#00ab00', color:'white',
                                    border:'none', borderRadius:'6px', fontSize:'1.1em', fontWeight:'bold', 
                                    cursor: loading ? 'default' : 'pointer', width:'100%',
                                    textTransform: 'uppercase', letterSpacing: '1px', boxShadow: '0 4px 15px rgba(0,0,0,0.3)'
                                }}
                            >
                                {loading ? 'Logging In...' : 'ENTER STUDIO'}
                            </button>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
};

export default LoginScreen;
