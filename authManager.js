const { Client } = require("@microsoft/microsoft-graph-client");
require('isomorphic-fetch');

// This is a "Passthrough" client. 
// It assumes the Frontend has already acquired a valid Access Token for Graph API.
const getGraphClient = (accessToken) => {
    return Client.init({
        authProvider: (done) => {
            done(null, accessToken);
        }
    });
};

module.exports = {
    getGraphClient
};
