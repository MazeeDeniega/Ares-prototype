import { useState, useEffect } from 'react';
import './styles/preference.css';

export default function DefaultPreferences() {
    const { csrf, pref, flash, job } = window.__LARAVEL__ ?? {};

    const postUrl = job ? `/jobs/${job.id}/preferences` : `/preferences`;
    const subtitle = job ?
      'Overrides your default preferences for this job only.' :
      'Applied to all jobs unless overridden by a job-specific preference.';


    const [qualWeight,       setQualWeight]       = useState(pref?.qual_weight        ?? 100);
    const [keywordWeight,    setKeywordWeight]    = useState(pref?.keyword_weight     ?? 40);
    const [semanticWeight,   setSemanticWeight]   = useState(pref?.semantic_weight    ?? 60);
    const [skillsWeight,     setSkillsWeight]     = useState(pref?.skills_weight      ?? 35);
    const [experienceWeight, setExperienceWeight] = useState(pref?.experience_weight  ?? 20);
    const [educationWeight,  setEducationWeight]  = useState(pref?.education_weight   ?? 25);
    const [certWeight,       setCertWeight]       = useState(pref?.cert_weight        ?? 10);

    const [prefFormatting,   setPrefFormatting]   = useState(!!pref?.pref_formatting);
    const [prefLanguage,     setPrefLanguage]     = useState(!!pref?.pref_language);
    const [prefConciseness,  setPrefConciseness]  = useState(!!pref?.pref_conciseness);
    const [prefOrganization, setPrefOrganization] = useState(!!pref?.pref_organization);

    const [error,   setError]   = useState('');
    const [success, setSuccess] = useState(flash?.success || '');

    const presWeight   = 100 - qualWeight;
    const blendTotal   = keywordWeight + semanticWeight;
    const qualTotal    = skillsWeight + experienceWeight + educationWeight + certWeight;

    const getPresNote = () => {
        const labels = {
            pref_formatting:   { label: 'Formatting',   checked: prefFormatting },
            pref_language:     { label: 'Language',     checked: prefLanguage },
            pref_conciseness:  { label: 'Conciseness',  checked: prefConciseness },
            pref_organization: { label: 'Organization', checked: prefOrganization },
        };
        const checked = Object.values(labels).filter(v => v.checked);
        if (checked.length === 0)
            return 'No selection — all four categories will be weighted equally at 25% each.';
        const each = Math.floor(100 / checked.length);
        return 'Active split → ' + checked.map((v, i) =>
            `${v.label}: ${each + (i === 0 ? 100 % checked.length : 0)}%`
        ).join(', ');
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setSuccess('');

        const response = await fetch(postUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf
            },
            body: JSON.stringify({
                qual_weight:        qualWeight,
                keyword_weight:     keywordWeight,
                semantic_weight:    semanticWeight,
                skills_weight:      skillsWeight,
                experience_weight:  experienceWeight,
                education_weight:   educationWeight,
                cert_weight:        certWeight,
                pref_formatting:    prefFormatting  ? 1 : 0,
                pref_language:      prefLanguage    ? 1 : 0,
                pref_conciseness:   prefConciseness ? 1 : 0,
                pref_organization:  prefOrganization? 1 : 0,
            })
        });

        if (response.ok) {
            setSuccess('Preferences saved successfully!');
            console.log('Preferences saved successfully!');
        } else {
          try {
            const data = await response.json();
            setError(data.message || 'Failed to save preferences.');
            console.log('Failed to save');
          } catch {
              setError(`Server error (${response.status}) — check Laravel logs.`);
          }
        }
    };

    // Reusable slider + number input row
    const WeightRow = ({ label, value, onChange }) => (
        <>
            <label style={styles.label}>{label} (%)</label>
            <div style={styles.fieldRow}>
                <input type="range" min="0" max="100" value={value}
                    onChange={e => onChange(parseInt(e.target.value))}
                    style={{ flex: 1, accentColor: '#2563eb' }} />
                <input type="number" min="0" max="100" value={value}
                    onChange={e => onChange(parseInt(e.target.value) || 0)}
                    style={styles.numberInput} />
            </div>
        </>
    );

    const TotalRow = ({ total }) => (
        <p style={styles.totalRow}>
            Total: <span style={total === 100 ? styles.ok : styles.bad}>
                {total}% {total === 100 ? '✓' : '(must equal 100%)'}
            </span>
        </p>
    );

  return (
    <>
    <div className='main-pref-cont'>
      <div className="inner-pref-cont">
        <div className="upper-pref-cont">
          <p><a href="/recruiter">← Back</a></p>
          <div className="header-pref-cont">
            <h2>{job ? `Job Preferences — ${job.title}` : 'Default Preferences'}</h2>
            <p>{subtitle}</p>
          </div>
          {error   && <div style={styles.error}>{error}</div>}
          {success && <div style={styles.success}>{success}</div>}
        </div>

        <form onSubmit={handleSubmit}>
          <div className="middle-pref-cont">
            <div className="left-pref-cont">
              {/* ── FINAL SCORE WEIGHTS ── */}

              <div className="final-score-cont">

                <div className="middle-pref-header">
                  <h3>Final Score Weights</h3>
                  <p>How much each component contributes to the final ranking score (must total 100%).</p>
                </div>
                
                <div className="pref-body">
                  <WeightRow label="Qualifications" value={qualWeight}
                  onChange={val => setQualWeight(Math.min(100, Math.max(0, val)))} />
              
                  <label>Presentation (%)</label>
                  <div>
                    <input type="range" min="0" max="100" value={presWeight} disabled
                      style={{ flex: 1, opacity: 0.5 }} />
                    <div>{presWeight}</div>
                  </div>
                  <p>Presentation = 100 − Qualifications (auto-set).</p>
                </div>

              </div>

              <div className="presentation-cont">
                {/* ── PRESENTATION ── */}
                <div className="middle-pref-header">
                  <h3>Presentation</h3>
                  <p>Check which categories to score. Checked categories split 100% equally. If none are checked, all four share 25% each.</p>
                </div>

                <div className='presentation-grid'>
                  {[
                    { label: 'Formatting & Visuals',     desc: 'Section spacing, B&W layout',                  value: prefFormatting,   setter: setPrefFormatting },
                    { label: 'Language Quality',         desc: 'Action verbs, formal tone, no typos',           value: prefLanguage,     setter: setPrefLanguage },
                    { label: 'Conciseness',              desc: 'Word count, page length, minimal repetition',   value: prefConciseness,  setter: setPrefConciseness },
                    { label: 'Organization & Structure', desc: 'Sections, margins, reverse-chronological order',value: prefOrganization, setter: setPrefOrganization },
                    
                  ].map(({ label, desc, value, setter }) => (
                  <label key={label} style={{ ...styles.checkCard, ...(value ? styles.checkCardActive : {}) }}>
                      <input type="checkbox" checked={value}
                          onChange={e => setter(e.target.checked)}
                          style={{ marginRight: 7, width: 15, height: 15, accentColor: '#2563eb', verticalAlign: 'middle' }} />
                      <strong style={{ fontSize: '0.88em', verticalAlign: 'middle' }}>{label}</strong>
                      <small style={{ display: 'block', color: '#6b7280', fontSize: '0.76em', marginTop: 3 }}>{desc}</small>
                  </label>
                  ))}
                  
                </div>
                <div style={styles.presNote}>{getPresNote()}</div>
              </div>

            </div>

            <div className="right-pref-cont">

              {/* ── QUALIFICATIONS ── */}
              <div className="qualification-cont">
                <div className="middle-pref-header">
                  <h3>Qualifications</h3>
                  <h4>
                    Skills Matching — TF-IDF + Semantic{' '}
                    <small style={{ fontWeight: 'normal', color: '#6b7280' }}>(must total 100%)</small>
                  </h4>
                </div>
                
                <div className="pref-body">
                  <WeightRow label="TF-IDF (Keyword)" value={keywordWeight}  onChange={setKeywordWeight} />
                  <WeightRow label="Semantic (AI)"    value={semanticWeight} onChange={setSemanticWeight} />
                  <TotalRow total={blendTotal} />
                
                </div>
                
                
                <div className="qualification-sub-cont">
                  <div className="middle-pref-header">
                    <h4 style={{ marginTop: 16 }}>
                      Qualification Sub-weights{' '}
                      <small style={{ fontWeight: 'normal', color: '#6b7280' }}>(must total 100%)</small>
                    </h4>
                  </div>
                  
                  <div className='pref-body' style={styles.sectionIndent}>
                    <WeightRow label="Skills Match"  value={skillsWeight}     onChange={setSkillsWeight} />
                    <WeightRow label="Experience"    value={experienceWeight} onChange={setExperienceWeight} />
                    <WeightRow label="Education"     value={educationWeight}  onChange={setEducationWeight} />
                    <WeightRow label="Certification" value={certWeight}       onChange={setCertWeight} />
                    <TotalRow total={qualTotal} />
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div className="save-pref">
            <button type="submit">Save Preferences</button>
          </div>
          

        </form>
      </div>
    </div>
    </>
  );
}

const styles = {
  checkCard:     { border: '2px solid #e5e7eb', borderRadius: 8, padding: '10px 12px', cursor: 'pointer', transition: 'border-color .15s, background .15s', display: 'block' },
  checkCardActive:{ borderColor: '#2563eb', background: '#eff6ff' },
  presNote:      { fontSize: '0.8em', color: '#2563eb', background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 6, padding: '6px 10px', margin: '6px 0 0' },
  error:         { color: '#dc2626', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: 6, padding: '8px 12px', margin: '10px 20px 16px', fontSize: '0.88em' },
  success:       { color: '#16a34a', background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: 6, padding: '8px 12px', margin: '10px 20px 16px', fontSize: '0.88em' },
};