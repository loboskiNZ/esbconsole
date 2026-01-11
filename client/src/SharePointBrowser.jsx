import React, { useState, useEffect } from 'react';
import { useMsal } from "@azure/msal-react";
import { loginRequest } from "./authConfig";
import axios from 'axios';

const SharePointBrowser = ({ onClose }) => {
    const { instance, accounts } = useMsal();
    const activeAccount = instance.getActiveAccount();
    const [files, setFiles] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [currentPath, setCurrentPath] = useState(null); // null = root
    const [history, setHistory] = useState([]);

    const [configMode, setConfigMode] = useState(false);
    const [config, setConfig] = useState({ siteId: '', driveId: '', folderId: '' });
    
    const [downloadingFileId, setDownloadingFileId] = useState(null);
    const [setlistName, setSetlistName] = useState(null);

    // Fetch Setlist Name
    useEffect(() => {
        axios.get('/api/setlist/data').then(res => {
            const data = res.data;
            if (data && data.setlists && data.activeSetlistId) {
                const active = data.setlists[data.activeSetlistId];
                if (active) setSetlistName(active.name);
            }
        }).catch(e => console.error(e));
    }, []);

    useEffect(() => {
        // Load initial config
        axios.get('/api/sharepoint/config').then(res => setConfig(res.data)).catch(e => console.error(e));
        
        // Only fetch if we have an account and haven't fetched yet (or strict mode double invokation)
        if (activeAccount && files.length === 0 && !loading && !error) {
            fetchFiles();
        }
    }, [activeAccount]); // Removed other deps to prevent loops

    const getToken = async () => {
        const request = {
            ...loginRequest,
            account: accounts[0]
        };
        try {
            const response = await instance.acquireTokenSilent(request);
            return response.accessToken;
        } catch (err) {
            if (err.name === "InteractionRequiredAuthError" || err.errorCode === "invalid_grant") {
                const response = await instance.acquireTokenPopup(request);
                return response.accessToken;
            } else {
                throw err;
            }
        }
    };

    const fetchFiles = async (folderId = null) => {
        if (loading) return; // Prevent double fetch
        setLoading(true);
        setError(null);
        try {
            const token = await getToken();

            let url = '/api/sharepoint/files';
            // Ensure folderId is a string, not an event object or null
            if (folderId && typeof folderId === 'string') {
                url += `?folderId=${encodeURIComponent(folderId)}`;
            } else if (config.folderId && !folderId) {
                 // Use configured root if we are at root (currentPath is null)
                 url += `?folderId=${encodeURIComponent(config.folderId)}`;
            }
            
            const res = await axios.get(url, {
                headers: { Authorization: 'Bearer ' + token }
            });
            setFiles(res.data);
            // If we fetched a specific folder, OR if we fell back to config.folderId, update state
            if(folderId && typeof folderId === 'string') {
                setCurrentPath(folderId);
            } else if (config.folderId && !folderId) {
                setCurrentPath(config.folderId);
            }
        } catch (e) {
            console.error(e);
            setError(`Failed to load: ${e.response?.config?.url || 'unknown'} | Error: ${e.message}`);
        } finally {
            setLoading(false);
        }
    };

    const handleFolderClick = (id) => {
        setHistory([...history, currentPath]);
        fetchFiles(id);
    };

    const handleBack = () => {
        if (history.length === 0) return;
        const prev = history[history.length - 1];
        setHistory(history.slice(0, -1));
        setCurrentPath(prev);
        fetchFiles(prev); // If prev is null, it fetches root
    };

    const saveConfig = async (overrideConfig = null) => {
        setError(null);
        const configToSave = overrideConfig || config;
        try {
            await axios.post('/api/sharepoint/config', configToSave);
            // Update local state if we used an override
            if (overrideConfig) setConfig(overrideConfig);
            
            setConfigMode(false);
            // Re-fetch files in case folder changed
            // If we just set home, we probably want to stay where we are, but re-fetching ensures we are good.
            fetchFiles(configToSave.folderId); 
        } catch (e) {
            setError("Failed to save config: " + e.message);
        }
    };

    // Helper for config inputs
    const setConf = (k, v) => setConfig(prev => ({...prev, [k]: v}));

    const createSetlistFolder = async () => {
        setLoading(true);
        try {
            const token = await getToken();

            const targetId = currentPath || config.folderId;
            console.log("Creating folder in:", targetId || "ROOT");
            
            const response = await axios.post('/api/sharepoint/folder', {
                parentId: targetId, 
                name: setlistName
            }, {
                headers: { Authorization: 'Bearer ' + token }
            });

            // Auto-create "scenes" subfolder
            if (response.data && response.data.id) {
                console.log("Main folder created. ID:", response.data.id, "Creating 'scenes' subfolder...");
                try {
                     await axios.post('/api/sharepoint/folder', {
                        parentId: response.data.id, 
                        name: 'scenes'
                    }, {
                        headers: { Authorization: 'Bearer ' + token }
                    });
                    console.log("'scenes' subfolder created successfully.");
                } catch (sceneErr) {
                    console.error("Failed to create scenes subfolder:", sceneErr);
                    alert("Setlist folder created, but 'scenes' subfolder failed to create automatically.\nError: " + (sceneErr.response?.data?.error || sceneErr.message));
                }
                // SUCCESS: Auto-Navigate to the new folder
                alert("Folder Created Successfully!\nName: " + response.data.name + "\n\nTaking you there now...");
                setHistory([...history, currentPath]);
                setCurrentPath(response.data.id);
                fetchFiles(response.data.id);
            } else {
                console.warn("Main folder created but no ID returned. response.data:", response.data);
                alert("Main folder created, but server did not return an ID.\nCannot create 'scenes' subfolder.");
                // Fallback refresh
                fetchFiles(currentPath);
            }


        } catch (e) {
            console.error(e);
            const msg = e.response && e.response.data && e.response.data.error ? e.response.data.error : e.message;
            setError("Create Folder Failed: " + msg);
            setLoading(false);
        }
    };

    const downloadFile = async (fileId, fileName) => {
        setDownloadingFileId(fileId);
        setError(null);
        try {
            const token = await getToken();

            const res = await axios.get(`/api/sharepoint/download/${fileId}`, {
                headers: { Authorization: 'Bearer ' + token },
                responseType: 'blob'
            });
            
            const url = window.URL.createObjectURL(new Blob([res.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', fileName);
            document.body.appendChild(link);
            link.click();
            link.remove();
            
        } catch (e) {
            setError("Download Failed: " + e.message);
        } finally {
            setDownloadingFileId(null);
        }
    };

    return (
        <div style={{
            position:'fixed', top:'10%', left:'10%', width:'80%', height:'80%', 
            background:'#222', zIndex:3000, border:'1px solid #444', 
            borderRadius:'8px', display:'flex', flexDirection:'column',
            boxShadow:'0 0 50px rgba(0,0,0,0.8)'
        }}>
            {/* HEADER */}
            <div style={{
                padding:'15px', borderBottom:'1px solid #333', background:'#1a1a1a', 
                borderRadius:'8px 8px 0 0', display:'flex', justifyContent:'space-between', alignItems:'center'
            }}>
                <div style={{display:'flex', gap:'10px', alignItems:'center'}}>
                    <h2 style={{margin:0, color:'#00bbff'}}>SharePoint Files</h2>
                    {!activeAccount && <button onClick={() => instance.loginPopup(loginRequest)} style={{padding:'5px 10px', background:'#0078d4', color:'white', border:'none', borderRadius:'4px', cursor:'pointer'}}>Sign In</button>}
                    {activeAccount && <div style={{color:'#0f0', fontSize:'0.8em'}}>Connected: {activeAccount.username}</div>}
                </div>
                
                <div style={{display:'flex', gap:'10px'}}>
                    <button onClick={() => setConfigMode(!configMode)} style={{background:'transparent', border:'1px solid #444', color:'#888', borderRadius:'4px', cursor:'pointer'}}>⚙️</button>
                    <button onClick={onClose} style={{background:'#f00', color:'#fff', border:'none', padding:'5px 15px', borderRadius:'4px', cursor:'pointer'}}>CLOSE</button>
                </div>
            </div>

            {/* CONTENT */}
            <div style={{flex:1, overflowY:'auto', background:'#000', borderRadius:'5px', padding:'10px'}}>
                
                {/* Error Banner */}
                {error && (
                    <div style={{
                        background:'#311', color:'#faa', border:'1px solid #f00', 
                        padding:'10px', marginBottom:'15px', borderRadius:'4px',
                        display:'flex', justifyContent:'space-between', alignItems:'center'
                    }}>
                        <span>⚠️ {error}</span>
                        <button onClick={() => setError(null)} style={{background:'transparent', border:'none', color:'#faa', cursor:'pointer'}}>✕</button>
                    </div>
                )}
                
                {configMode ? (
                    <div style={{padding:'20px', color:'#eee', maxWidth:'500px', margin:'0 auto'}}>
                        <h3>Configuration</h3>
                        <div style={{marginBottom:'10px'}}>
                            <label style={{display:'block', fontSize:'0.8em', color:'#888'}}>Site ID</label>
                            <input value={config.siteId} onChange={e => setConf('siteId', e.target.value)} style={{width:'100%', padding:'8px', background:'#111', border:'1px solid #333', color:'white'}} placeholder="Optional: Leave empty for Me root" />
                        </div>
                        <div style={{marginBottom:'10px'}}>
                            <label style={{display:'block', fontSize:'0.8em', color:'#888'}}>Drive ID</label>
                            <input value={config.driveId} onChange={e => setConf('driveId', e.target.value)} style={{width:'100%', padding:'8px', background:'#111', border:'1px solid #333', color:'white'}} placeholder="Optional" />
                        </div>
                        <div style={{marginBottom:'10px'}}>
                            <label style={{display:'block', fontSize:'0.8em', color:'#888'}}>Root Folder ID</label>
                            <input value={config.folderId} onChange={e => setConf('folderId', e.target.value)} style={{width:'100%', padding:'8px', background:'#111', border:'1px solid #333', color:'white'}} placeholder="Optional" />
                        </div>
                        <div style={{display:'flex', gap:'10px', marginTop:'20px'}}>
                            <button onClick={saveConfig} style={{padding:'8px 20px', background:'#0078d4', color:'white', border:'none', borderRadius:'4px', cursor:'pointer'}}>Save Config</button>
                            <button onClick={() => setConfigMode(false)} style={{padding:'8px 20px', background:'#333', color:'#ccc', border:'none', borderRadius:'4px', cursor:'pointer'}}>Cancel</button>
                        </div>
                    </div>
                ) : (
                    <>
                        {/* Breadcrumb / Nav */}
                        <div style={{marginBottom:'15px', display:'flex', gap:'10px', alignItems:'center'}}>
                            <button disabled={!currentPath} onClick={handleBack} style={{
                                background: currentPath ? '#333' : '#111', color: currentPath ? '#fff' : '#444',
                                border:'none', padding:'5px 10px', borderRadius:'4px', cursor: currentPath ? 'pointer' : 'default'
                            }}>⬅ Back</button>
                            
                            {currentPath && (
                                <button onClick={() => {
                                    const newConfig = { ...config, folderId: currentPath };
                                    saveConfig(newConfig).then(() => alert("Saved current folder as Home!"));
                                }} style={{
                                    background: '#0078d4', color: '#fff', border:'none', 
                                    padding:'5px 10px', borderRadius:'4px', cursor: 'pointer',
                                    marginLeft:'auto'
                                }}>
                                    Set as Home 🏠
                                </button>
                            )}
                            
                            {/* Setlist Folder Shortcut */}
                            {setlistName && !loading && (
                                (() => {
                                    // Check if folder exists
                                    // Check if folder exists
                                    const match = files.find(f => f.name.toLowerCase() === (setlistName || '').toLowerCase() && f.folder);
                                    if (match) {
                                        return (
                                            <button onClick={() => handleFolderClick(match.id)} style={{
                                                background: '#4CAF50', color: '#fff', border:'none', 
                                                padding:'5px 10px', borderRadius:'4px', cursor: 'pointer',
                                                marginLeft: '10px'
                                            }}>
                                                Open "{setlistName}" Folder 📂
                                            </button>
                                        )
                                    } else {
                                         // If we are at root and it's missing, offer to create
                                         if (currentPath === config.folderId || currentPath === null) {
                                            return (
                                                <button onClick={createSetlistFolder} style={{
                                                    background: '#ff9800', color: '#000', border:'none', 
                                                    padding:'5px 10px', borderRadius:'4px', cursor: 'pointer',
                                                    marginLeft: '10px'
                                                }}>
                                                    + Create "{setlistName}"
                                                </button>
                                            )
                                         }
                                         return null;
                                    }
                                    return null;
                                })()
                            )}
                        </div>

                        {loading && <div style={{color:'#00bbff', padding:'20px'}}>Loading files...</div>}
                        
                        {!activeAccount && !loading && (
                            <div style={{height:'100%', display:'flex', alignItems:'center', justifyContent:'center', color:'#666', flexDirection:'column', gap:'20px'}}>
                                <div>Please sign in to access your OneDrive/SharePoint files.</div>
                            </div>
                        )}

                        {activeAccount && !loading && files.length === 0 && (
                            <div style={{padding:'40px', color:'#666', textAlign:'center', display:'flex', flexDirection:'column', alignItems:'center', gap:'10px'}}>
                                <div style={{fontSize:'3em'}}>📂</div>
                                <div>This folder is empty.</div>
                                <div style={{fontSize:'0.8em'}}>Upload files to your SharePoint/OneDrive folder to see them here.</div>
                            </div>
                        )}

                        <div style={{display:'grid', gridTemplateColumns:'repeat(auto-fill, minmax(200px, 1fr))', gap:'15px'}}>
                            {files.map(file => (
                                <div key={file.id} 
                                    onClick={(e) => {
                                        e.preventDefault();
                                        if (downloadingFileId || loading) return; 
                                        if (file.folder) {
                                             console.log("Navigating to folder:", file.id);
                                             handleFolderClick(file.id);
                                        } else {
                                             downloadFile(file.id, file.name);
                                        }
                                    }} 
                                    style={{
                                        background: downloadingFileId === file.id ? '#332' : '#1a1a1a', 
                                        padding:'15px', borderRadius:'8px', border:'1px solid #333',
                                        cursor: (downloadingFileId || loading) ? 'wait' : 'pointer', 
                                        display:'flex', flexDirection:'column', gap:'10px',
                                        transition: 'all 0.2s',
                                        userSelect: 'none', // Prevent text selection on rapid clicks
                                        opacity: (downloadingFileId && downloadingFileId !== file.id) ? 0.5 : 1
                                    }}
                                    onMouseEnter={e => (!downloadingFileId && !loading) && (e.currentTarget.style.background = '#222')}
                                    onMouseLeave={e => (!downloadingFileId && !loading) && (e.currentTarget.style.background = '#1a1a1a')}
                                >
                                    <div style={{fontSize:'3em', textAlign:'center'}}>
                                        {downloadingFileId === file.id ? '⏳' : (file.folder ? '📁' : '📄')}
                                    </div>
                                    <div style={{wordBreak:'break-word', color:'#ddd', fontWeight:'bold', textAlign:'center'}}>
                                        {file.name}
                                    </div>
                                    <div style={{fontSize:'0.7em', color:'#444', textAlign:'center'}}>
                                        {file.id ? file.id.substring(0,8)+'...' : 'NO ID'}
                                    </div>
                                    <div style={{fontSize:'0.7em', color:'#666', textAlign:'center'}}>
                                        {downloadingFileId === file.id ? 'Downloading...' : (file.size / 1024 / 1024).toFixed(2) + ' MB'}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </>
                )}
            </div>
        </div>
    );
};

export default SharePointBrowser;
