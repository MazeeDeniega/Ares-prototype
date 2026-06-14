import React, { useState, useEffect, useRef, useCallback } from 'react';
import '../../css/components/addjobmodal.css'

/* A modal for creating a new job posting.
 * Uses Quill (loaded from CDN via useEffect) for rich-text description editing.
 * The description is stored as HTML so JobDescriptionPage can render it faithfully.
 *
 * Props:
 *  isOpen   {boolean}   — controls visibility
 *  onClose  {Function}  — called when the user dismisses the modal
 *  onSave   {Function}  — (jobData: { title, description_html }) => Promise<void>
 */

const QUILL_CSS = 'https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css';
const QUILL_JS  = 'https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js';

const TOOLBAR_OPTIONS = [
  [{ header: [1, 2, 3, false] }],
  ['link'],
  ['bold', 'italic', 'underline'],
  [{ list: 'ordered' }, { list: 'bullet' }],
];

/** Dynamically inject Quill CSS + JS from CDN if not already present. */
const loadQuill = () =>
  new Promise((resolve) => {
    if (window.Quill) { resolve(); return; }

    // CSS
    if (!document.querySelector(`link[href="${QUILL_CSS}"]`)) {
      const link = document.createElement('link');
      link.rel  = 'stylesheet';
      link.href = QUILL_CSS;
      document.head.appendChild(link);
    }

    // JS
    const script = document.createElement('script');
    script.src  = QUILL_JS;
    script.onload = resolve;
    document.head.appendChild(script);
  });

export default function AddJobModal({ isOpen, onClose, onSave }) {
  const [title,   setTitle]   = useState('');
  const [saving,  setSaving]  = useState(false);
  const [quillReady, setQuillReady] = useState(false);

  const editorRef  = useRef(null); // DOM node Quill mounts into
  const quillRef   = useRef(null); // Quill instance

  /* Load Quill from CDN when modal first opens */
  useEffect(() => {
    if (!isOpen) return;
    loadQuill().then(() => setQuillReady(true));
  }, [isOpen]);

  /* Initialise (or re-initialise) Quill once the editor div is in the DOM */
  useEffect(() => {
    if (!quillReady || !editorRef.current) return;
    if (quillRef.current) return; // already initialised

    quillRef.current = new window.Quill(editorRef.current, {
      theme:   'snow',
      placeholder: 'Job description…',
      modules: { toolbar: TOOLBAR_OPTIONS },
    });
  }, [quillReady, isOpen]);

  /* Reset form when modal closes */
  useEffect(() => {
    if (!isOpen) {
      setTitle('');
      setSaving(false);
      if (quillRef.current) {
        quillRef.current.setContents([]);
        quillRef.current = null; // force re-init on next open
      }
      setQuillReady(false);
    }
  }, [isOpen]);

  /* Close on Escape */
  useEffect(() => {
    const handler = (e) => { 
      if (e.key === 'Escape') 
        onClose(); 
      };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [onClose]);

  /* Undo / Redo helpers for mobile toolbar */
  const handleUndo = () => quillRef.current?.history.undo();
  const handleRedo = () => quillRef.current?.history.redo();

  const handleSave = useCallback(async () => {
    const html = quillRef.current?.root.innerHTML ?? '';
    if (!title.trim()) { 
      alert('Please enter a job title.'); 
      return; 
    }
    setSaving(true);
    try {
      await onSave({ title: title.trim(), description: html });
      onClose();
    } catch (err) {
      console.error('Save failed:', err);
    } finally {
      setSaving(false);
    }
  }, [title, onSave, onClose]);

  if (!isOpen) return null;

  return (
    <div
      className="modal-backdrop"
      onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
      role="dialog"
      aria-modal="true"
      aria-label="Add new job"
    >
      <div className="modal-card">

        {/* ── Mobile top bar ── */}
        <div className="modal-mobile-bar">
          <button className="modal-mobile-bar__back" onClick={onClose} aria-label="Back">
            <ArrowLeftIcon />
          </button>
          <div className="modal-mobile-bar__actions">
            <button className="modal-mobile-bar__icon-btn" onClick={handleUndo} aria-label="Undo">
              <UndoIcon />
            </button>
            <button className="modal-mobile-bar__icon-btn" onClick={handleRedo} aria-label="Redo">
              <RedoIcon />
            </button>
            <button
              className="modal-mobile-bar__icon-btn"
              onClick={handleSave}
              disabled={saving}
              aria-label="Save"
            >
              <SaveIcon />
            </button>
          </div>
        </div>

        {/* ── Job title field ── */}
        <input
          className="modal-title-input"
          type="text"
          placeholder="Job Title"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          autoFocus
        />

        {/* ── Quill rich-text editor ── */}
        <div className="modal-editor-wrap">
          {/* Quill mounts here */}
          <div ref={editorRef} />
        </div>

        {/* ── Desktop footer ── */}
        <div className="modal-footer">
          <button
            className="modal-save-btn"
            onClick={handleSave}
            disabled={saving}
          >
            {saving ? 'Saving…' : 'Save'}
          </button>
        </div>

      </div>
    </div>
  );
};

/* ── Inline SVG icons ── */
const ArrowLeftIcon = () => (
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
    <path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/>
  </svg>
);
const UndoIcon = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>
  </svg>
);
const RedoIcon = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M15 14l5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/>
  </svg>
);
const SaveIcon = () => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
       stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
    <polyline points="17 21 17 13 7 13 7 21"/>
    <polyline points="7 3 7 8 15 8"/>
  </svg>
);