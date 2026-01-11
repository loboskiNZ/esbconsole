import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { PublicClientApplication } from "@azure/msal-browser";
import { MsalProvider } from "@azure/msal-react";
import { msalConfig } from "./authConfig";
import './index.css'
import App from './App.jsx'

const msalInstance = new PublicClientApplication(msalConfig);

// Initialize usually needed for v3, but React wrapper handles some. 
// Best practice: await initialize() but in top-level we can't await easily.
// msal-react handles it mostly, or we rely on the provider.
// However, newer msal-browser requires initialize().
// Let's do it safely.

if (!msalInstance.getActiveAccount() && msalInstance.getAllAccounts().length > 0) {
    msalInstance.setActiveAccount(msalInstance.getAllAccounts()[0]);
}

// We need to handle the async init for v3+ of msal-browser which returns a promise for initialize()
// We can wrap render in a function.

msalInstance.initialize().then(() => {
    createRoot(document.getElementById('root')).render(
      <StrictMode>
        <MsalProvider instance={msalInstance}>
            <App />
        </MsalProvider>
      </StrictMode>,
    )
});
