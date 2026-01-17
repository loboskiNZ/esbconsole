require('dotenv').config();
const msal = require('@azure/msal-node');

const config = {
    auth: {
        clientId: process.env.AZURE_CLIENT_ID,
        authority: `https://login.microsoftonline.com/${process.env.AZURE_TENANT_ID}`,
        clientSecret: process.env.AZURE_CLIENT_SECRET,
    }
};

const cca = new msal.ConfidentialClientApplication(config);

/**
 * Acquires a token for the Application (Daemon flow)
 * Permission required: Mail.Send (Application)
 */
async function getAppToken() {
    try {
        const result = await cca.acquireTokenByClientCredential({
            scopes: ['https://graph.microsoft.com/.default'],
        });
        return result.accessToken;
    } catch (error) {
        console.error("Error acquiring app token:", error);
        throw error;
    }
}

module.exports = { getAppToken };
