import React, { useState, useRef, useEffect } from 'react';
import ReactCrop, { centerCrop, makeAspectCrop } from 'react-image-crop';
import 'react-image-crop/dist/ReactCrop.css';
import * as pdfjsLib from 'pdfjs-dist';
import axios from 'axios';
import { Scissors, Save, X, ArrowLeft, ArrowRight } from 'lucide-react';

// Set worker source for PDF.js
// Set worker source for PDF.js - Using local import for offline support
// Set worker source for PDF.js - Using static file for stability
pdfjsLib.GlobalWorkerOptions.workerSrc = '/pdf.worker.min.mjs';

const SnippetMaker = ({ fileUrl, song, user, onClose, onSave, targetId }) => {
    const [pageNumber, setPageNumber] = useState(1);
    const [numPages, setNumPages] = useState(0);
    const [pdf, setPdf] = useState(null);
    const [canvasUrl, setCanvasUrl] = useState(null);
    const [crop, setCrop] = useState(null);
    const [completedCrop, setCompletedCrop] = useState(null);
    const [selectedCueIndex, setSelectedCueIndex] = useState(0);
    const [isProcessing, setIsProcessing] = useState(false);
    
    const canvasRef = useRef(null);
    const imgRef = useRef(null);

    const [loadingStatus, setLoadingStatus] = useState("Initializing...");

    // Load PDF
    useEffect(() => {
        let loadingTask = null;
        let isMounted = true;

        const loadPdf = async () => {
            try {
                setLoadingStatus("Fetching PDF...");
                console.log("📄 [SnippetMaker] Loading PDF:", fileUrl);
                loadingTask = pdfjsLib.getDocument(fileUrl);
                
                // Add progress data support if needed, but for now just await
                const loadedPdf = await loadingTask.promise;
                
                if (isMounted) {
                    setPdf(loadedPdf);
                    setNumPages(loadedPdf.numPages);
                    setLoadingStatus(`Parsing ${loadedPdf.numPages} pages...`);
                    renderPage(loadedPdf, 1);
                }
            } catch (e) {
                console.error("❌ [SnippetMaker] Error loading PDF", e);
                if (isMounted) setLoadingStatus(`Error: ${e.message}`);
            }
        };
        
        if (fileUrl) loadPdf();
        
        return () => {
            isMounted = false;
        };
    }, [fileUrl]);

    // Render Page to Canvas -> DataURL
    const renderPage = async (loadedPdf, num) => {
        try {
            setLoadingStatus(`Rendering Page ${num}...`);
            const page = await loadedPdf.getPage(num);
            
            // USER REQUEST: High Resolution Snippets
            // Use device pixel ratio or default to 2.0 for sharp text
            const scale = (window.devicePixelRatio || 1) * 2.0; 
            const viewport = page.getViewport({ scale: scale }); 
            
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            // FIX: Explicitly set white background to prevent transparent PDFs appearing black
            context.fillStyle = 'white';
            context.fillRect(0, 0, canvas.width, canvas.height);

            // Render with Timeout Race
            const renderTask = page.render({ canvasContext: context, viewport });
            
            const timeoutPromise = new Promise((_, reject) => 
                setTimeout(() => reject(new Error("Render Timeout (5s)")), 5000)
            );

            await Promise.race([renderTask.promise, timeoutPromise]);
            
            setCanvasUrl(canvas.toDataURL('image/png'));
            setLoadingStatus("Ready");
        } catch (e) {
            console.error("Render error", e);
            setLoadingStatus(`Render Error: ${e.message}`);
        }
    };

    const changePage = (delta) => {
        const newPage = pageNumber + delta;
        if (newPage >= 1 && newPage <= numPages) {
            setPageNumber(newPage);
            renderPage(pdf, newPage);
            setCrop(null); // Reset crop
        }
    };

    // Extract Crop and Save
    const handleSave = async () => {
        if (!completedCrop || !imgRef.current) return;

        setIsProcessing(true);
        
        try {
            // 1. Cut the pixels
            const image = imgRef.current;
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            // Adjust for scale
            const scaleX = image.naturalWidth / image.width;
            const scaleY = image.naturalHeight / image.height;
            
            canvas.width = completedCrop.width * scaleX;
            canvas.height = completedCrop.height * scaleY;

            ctx.drawImage(
                image,
                completedCrop.x * scaleX,
                completedCrop.y * scaleY,
                completedCrop.width * scaleX,
                completedCrop.height * scaleY,
                0,
                0,
                canvas.width,
                canvas.height
            );

            const base64Data = canvas.toDataURL('image/png');

            // 2. Upload
            await axios.post('/api/charts/snippet', {
                songId: song.id,
                cueIndex: selectedCueIndex,
                role: user?.role || 'default',
                imageData: base64Data
            });

            onSave(); // Notify parent
            alert("Snippet Saved!");

        } catch (e) {
            console.error("Save failed", e);
            alert("Failed to save snippet");
        }
        setIsProcessing(false);
    };

    return (
        <div style={{
            position: 'fixed', top: 0, left: 0, width: '100%', height: '100%',
            background: '#111', zIndex: 3000, display: 'flex', flexDirection: 'column'
        }}>
            {/* Header */}
            <div style={{
                height: '60px', background: '#222', borderBottom: '1px solid #444',
                display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 20px'
            }}>
                <div style={{display:'flex', alignItems:'center', gap:'10px'}}>
                    <Scissors color="#ffaa00"/>
                    <div style={{display:'flex', flexDirection:'column'}}>
                        <span style={{fontWeight:'bold', color:'white'}}>Digital Scissors</span>
                        <span style={{fontSize:'0.6em', color:'#aaa', fontFamily:'monospace', maxWidth:'300px', lineHeight:'1.1'}}>
                            {song ? `${song.title || 'Unknown Song'} (${(song.parts||[]).length} parts)` : `Target: ${targetId}`}
                        </span>
                    </div>
                </div>
                
                <div style={{display:'flex', gap:'15px'}}>
                     {/* Cue Selector */}
                     <select 
                        value={selectedCueIndex} 
                        onChange={(e) => setSelectedCueIndex(Number(e.target.value))}
                        style={{background:'#333', color:'white', border:'1px solid #555', padding:'5px', borderRadius:'4px'}}
                     >
                         {(song && (song.parts || song.cues)) ? (song.parts || song.cues).map((cue, i) => (
                             <option key={i} value={i}>{cue.name}</option>
                         )) : <option value={-1}>No Cues Found</option>}
                     </select>

                     <div style={{display:'flex', alignItems:'center', gap:'10px'}}>
                         {/* Overwrite Warning */}
                         {(() => {
                             if (!song || selectedCueIndex < 0) return null;
                             const cues = song.parts || song.cues;
                             if (!cues || !cues[selectedCueIndex]) return null;
                             
                             const role = user?.role ? user.role.replace(/[^a-z0-9]/gi, '_').toLowerCase() : 'default';
                             const hasSnippet = cues[selectedCueIndex].visualSnippets?.[role] || cues[selectedCueIndex].visualSnippet;
                             
                             if (hasSnippet) {
                                 return <span style={{color:'#ffaa00', fontSize:'0.8em', fontWeight:'bold'}}>⚠️ Will Replace Existing Snippet</span>;
                             }
                             return null;
                         })()}

                        <button disabled={!completedCrop || isProcessing} onClick={handleSave} style={{
                            background: completedCrop ? '#00bb00' : '#444', color:'white', border:'none',
                            padding:'5px 15px', borderRadius:'4px', cursor: completedCrop ? 'pointer' : 'default',
                            display:'flex', alignItems:'center', gap:'5px'
                        }}>
                            <Save size={16}/> {isProcessing ? 'Saving...' : 'Save Snippet'}
                        </button>
                    </div>
                    
                    <button onClick={onClose} style={{background:'transparent', border:'none', color:'#888', cursor:'pointer'}}>
                        <X size={24}/>
                    </button>
                </div>
            </div>

            {/* Toolbar */}
             <div style={{height:'40px', background:'#1a1a1a', display:'flex', justifyContent:'center', alignItems:'center', gap:'20px', color:'#aaa', fontSize:'0.9em'}}>
                <button disabled={pageNumber <= 1} onClick={() => changePage(-1)} style={{background:'transparent', border:'none', color:'white'}}><ArrowLeft size={16}/></button>
                <span>Page {pageNumber} of {numPages}</span>
                <button disabled={pageNumber >= numPages} onClick={() => changePage(1)} style={{background:'transparent', border:'none', color:'white'}}><ArrowRight size={16}/></button>
             </div>

            {/* Workspace */}
            <div style={{flex: 1, overflow: 'auto', background: '#000', padding:'20px', display:'flex', justifySelf:'center'}}>
                {canvasUrl ? (
                    <ReactCrop crop={crop} onChange={c => setCrop(c)} onComplete={c => setCompletedCrop(c)}>
                        <img ref={imgRef} src={canvasUrl} style={{maxWidth: '100%'}} alt="PDF Page" />
                    </ReactCrop>
                ) : (
                    <div style={{color:'#666', marginTop:'50px', fontFamily:'monospace', textAlign:'center'}}>
                        {loadingStatus}
                    </div>
                )}
            </div>
        </div>
    );
};

export default SnippetMaker;
