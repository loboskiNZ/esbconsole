
import React, { useEffect, useState } from 'react';

// Styles for the component
const styles = {
    overlay: {
        position: 'fixed',
        top: 0,
        left: 0,
        width: '100vw',
        height: '100vh',
        backgroundColor: 'rgba(0, 0, 0, 0.98)', // Slightly more opaque since we removed blur
        // backdropFilter: 'blur(10px)', // REMOVED: Causes WebKit Crash on iPad
        zIndex: 9999,
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        color: '#fff',
        fontFamily: '"Outfit", sans-serif',
        opacity: 1,
        transition: 'opacity 1s ease-out'
    },
    overlayHidden: {
        opacity: 0,
        pointerEvents: 'none'
    },
    logoContainer: {
        width: '300px',
        height: '300px',
        marginBottom: '40px',
        perspective: '1000px'
    },
    logo: {
        width: '100%',
        height: '100%',
        objectFit: 'contain',
        animation: 'spin 10s linear infinite'
    },
    text: {
        fontSize: '2.5rem',
        fontWeight: '200',
        textAlign: 'center',
        opacity: 0,
        transform: 'translateY(20px)',
        animation: 'fadeInUp 1s ease-out forwards 0.5s'
    },
    highlight: {
        color: '#00ffff',
        fontWeight: '600'
    },
    status: {
        marginTop: '20px',
        fontSize: '1rem',
        color: '#888',
        fontStyle: 'italic'
    }
};

// Add keyframes to document
const styleSheet = document.createElement("style");
styleSheet.innerText = `
@keyframes spin {
    from { transform: rotateY(0deg); }
    to { transform: rotateY(360deg); }
}
@keyframes fadeInUp {
    to { opacity: 1; transform: translateY(0); }
}
`;
document.head.appendChild(styleSheet);

const WelcomeSplash = ({ name, onComplete, onRestore }) => {
    const [visible, setVisible] = useState(true);
    const [status, setStatus] = useState("Loading assets...");

    useEffect(() => {
        // Step 1: Start Restoration
        setStatus("Restoring your personal mix...");
        if (onRestore) {
            onRestore();
        }

        // Step 2: Verification (Fake delay + update status)
        setTimeout(() => {
            setStatus("Syncing with console...");
        }, 2000);

        // Step 3: Success & Exit
        setTimeout(() => {
            setStatus("Ready to perform!");
        }, 3500);

        // Step 4: Fade Out (Total 5s)
        const timer = setTimeout(() => {
            setVisible(false);
            if (onComplete) {
                // Wait for fade out transition before unmounting logic triggers if handled by parent
                setTimeout(onComplete, 1000);
            }
        }, 5000);

        return () => clearTimeout(timer);
    }, [onComplete, onRestore]);

    if (!visible) return null;

    return (
        <div style={{...styles.overlay, ...(visible ? {} : styles.overlayHidden)}}>
            <div style={styles.logoContainer}>
                <img src="/esb_logo.png" alt="ESB Logo" style={styles.logo} />
            </div>
            <div style={styles.text}>
                Welcome, <span style={styles.highlight}>{name}</span>
            </div>
            <div style={styles.status}>
                {status}
            </div>
        </div>
    );
};

export default WelcomeSplash;
