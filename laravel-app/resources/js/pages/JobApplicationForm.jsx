import { useState } from "react";

const API_URL = "/api/applications"; //  Laravel endpoint?

const initialForm = {
  first_name: "",
  last_name: "",
  email: "",
  contact_number: "",
  city: "",
  province: "",
  postal_code: "",
  country: "",
  desired_pay: "50000",
  engagement_type: "",
  date_available: "",
  highest_education: "",
  college_university: "",
  referred_by: "",
  references: "",
  tor_path: null,
  cert_path: null,
};

const styles = `
  @import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap');

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'DM Sans', sans-serif;
    background: #eef0fb;
    min-height: 100vh;
    color: #1a1c2e;
  }

  .app-wrap {
    min-height: 100vh;
    background: #eef0fb;
    padding: 48px 16px;
  }

  .form-card {
    max-width: 720px;
    margin: 0 auto;
    background: #ffffff;
    border: 1px solid #d5d8f0;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 4px 28px rgba(57,67,183,0.10);
  }

  .form-header {
    background: #3943B7;
    padding: 40px 48px 36px;
    position: relative;
    overflow: hidden;
  }

  .form-header::before {
    content: '';
    position: absolute;
    top: -60px; right: -60px;
    width: 200px; height: 200px;
    border: 1px solid rgba(255,255,255,0.10);
    border-radius: 50%;
  }

  .form-header::after {
    content: '';
    position: absolute;
    bottom: -40px; left: 40px;
    width: 120px; height: 120px;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 50%;
  }

  .form-header h1 {
    font-family: 'DM Sans', sans-serif;
    font-weight: 600;
    font-size: 2rem;
    color: #ffffff;
    letter-spacing: -0.02em;
    line-height: 1.15;
    position: relative;
    z-index: 1;
  }

  .form-header h1 em {
    font-style: normal;
    color: #a8b0f0;
  }

  .form-header p {
    margin-top: 8px;
    font-size: 0.85rem;
    color: rgba(255,255,255,0.55);
    font-weight: 300;
    letter-spacing: 0.02em;
    position: relative;
    z-index: 1;
  }

  .form-body {
    padding: 48px;
  }

  .section {
    margin-bottom: 44px;
  }

  .section-label {
    font-family: 'DM Sans', sans-serif;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #3943B7;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #d5d8f0;
  }

  .row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
  }

  .row.single { grid-template-columns: 1fr; }
  .row.triple { grid-template-columns: 1fr 1fr 1fr; }

  .field { display: flex; flex-direction: column; gap: 6px; }

  .field label {
    font-family: 'DM Sans', sans-serif;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #4a4f7a;
  }

  .field label span { color: #c0392b; margin-left: 2px; }

  .field input, .field select, .field textarea {
    background: #f4f5fc;
    border: 1px solid #d5d8f0;
    border-radius: 4px;
    padding: 10px 14px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem;
    color: #1a1c2e;
    outline: none;
    transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
    width: 100%;
  }

  .field input:focus, .field select:focus, .field textarea:focus {
    border-color: #3943B7;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(57,67,183,0.10);
  }

  .field input::placeholder, .field textarea::placeholder {
    color: #a0a5c8;
  }

  .field textarea { resize: vertical; min-height: 88px; }

  .field select { appearance: none; cursor: pointer; }

  /* Salary slider */
  .salary-display {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 12px;
  }

  .salary-value {
    font-family: 'DM Sans', sans-serif;
    font-size: 1.7rem;
    font-weight: 600;
    color: #3943B7;
  }

  .salary-value small {
    font-family: 'DM Sans', sans-serif;
    font-size: 0.75rem;
    color: #7a80b8;
    margin-left: 4px;
    font-weight: 400;
  }

  .salary-range {
    font-size: 0.72rem;
    color: #a0a5c8;
    font-family: 'DM Sans', sans-serif;
  }

  .slider-wrap { position: relative; padding: 8px 0; }

  input[type="range"] {
    -webkit-appearance: none;
    width: 100%;
    height: 3px;
    background: linear-gradient(to right, #3943B7 0%, #3943B7 var(--pct, 20%), #d5d8f0 var(--pct, 20%), #d5d8f0 100%);
    border-radius: 2px;
    outline: none;
    cursor: pointer;
    border: none;
    padding: 0;
  }

  input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 18px; height: 18px;
    border-radius: 50%;
    background: #3943B7;
    cursor: pointer;
    border: 3px solid #ffffff;
    box-shadow: 0 0 0 1px #3943B7;
    transition: transform 0.15s;
  }

  input[type="range"]::-webkit-slider-thumb:hover { transform: scale(1.2); }

  /* Employment type */
  .radio-group { display: flex; gap: 12px; flex-wrap: wrap; }

  .radio-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    border: 1px solid #d5d8f0;
    border-radius: 100px;
    cursor: pointer;
    transition: all 0.18s;
    font-size: 0.82rem;
    font-family: 'DM Sans', sans-serif;
    color: #4a4f7a;
    background: #f4f5fc;
    user-select: none;
  }

  .radio-pill input { display: none; }
  .radio-pill.selected { background: #3943B7; color: #ffffff; border-color: #3943B7; }

  /* File upload */
  .upload-zone {
    border: 1.5px dashed #a8b0f0;
    border-radius: 4px;
    padding: 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: #f4f5fc;
    position: relative;
    overflow: hidden;
  }

  .upload-zone:hover { border-color: #3943B7; background: #eef0fb; }
  .upload-zone.has-file { border-color: #3943B7; background: #eef0fb; }

  .upload-zone input[type="file"] {
    position: absolute; inset: 0;
    opacity: 0; cursor: pointer;
    width: 100%; height: 100%;
  }

  .upload-icon {
    width: 36px; height: 36px;
    margin: 0 auto 10px;
    color: #7a80b8;
  }

  .upload-zone p { font-size: 0.8rem; color: #7a80b8; font-family: 'DM Sans', sans-serif; }
  .upload-zone p strong { color: #3943B7; display: block; margin-bottom: 2px; font-size: 0.85rem; font-family: 'DM Sans', sans-serif; }
  .upload-zone.has-file p strong { color: #3943B7; }

  /* Submit */
  .submit-btn {
    width: 100%;
    padding: 16px;
    background: #3943B7;
    color: #ffffff;
    border: none;
    border-radius: 4px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 8px;
    position: relative;
    overflow: hidden;
  }

  .submit-btn:hover:not(:disabled) { background: #2d35a0; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(57,67,183,0.35); }
  .submit-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

  .alert {
    padding: 14px 18px;
    border-radius: 3px;
    font-size: 0.85rem;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .alert-success { background: #eef6f1; color: #2d6a45; border: 1px solid #b8dfc7; }
  .alert-error { background: #fdf1f1; color: #892b2b; border: 1px solid #f0b8b8; }

  @media (max-width: 600px) {
    .form-body { padding: 28px 20px; }
    .form-header { padding: 28px 20px; }
    .row { grid-template-columns: 1fr; }
    .row.triple { grid-template-columns: 1fr 1fr; }
  }
`;

export default function JobApplicationForm() {
  const [form, setForm] = useState(initialForm);
  const [loading, setLoading] = useState(false);
  const [status, setStatus] = useState(null); // { type: 'success'|'error', message }

  const set = (key, value) => setForm(f => ({ ...f, [key]: value }));

  const salaryMin = 10000, salaryMax = 200000;
  const salaryNum = parseInt(form.desired_pay) || salaryMin;
  const salaryPct = ((salaryNum - salaryMin) / (salaryMax - salaryMin)) * 100;

  const formatPeso = n => "₱" + parseInt(n).toLocaleString("en-PH");

  const handleFile = (key, file) => set(key, file || null);

  const handleSubmit = async () => {
    setLoading(true);
    setStatus(null);

    try {
      const data = new FormData();

      // Append all text fields
      const textFields = [
        "first_name","last_name","email","city","province",
        "postal_code","country","desired_pay","engagement_type",
        "date_available","highest_education","college_university",
        "referred_by","references"
      ];
      textFields.forEach(k => data.append(k, form[k] || ""));

      // Append contact_number separately (not in migration but in form)
      data.append("contact_number", form.contact_number || "");

      // Append files
      if (form.tor_path instanceof File) data.append("tor_path", form.tor_path);
      if (form.cert_path instanceof File) data.append("cert_path", form.cert_path);

      const res = await fetch(API_URL, {
        method: "POST",
        headers: {
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "",
          "Accept": "application/json",
        },
        body: data,
      });

      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || `Server error ${res.status}`);
      }

      setStatus({ type: "success", message: "Application submitted successfully! We'll be in touch soon." });
      setForm(initialForm);
    } catch (e) {
      setStatus({ type: "error", message: e.message || "Submission failed. Please try again." });
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <style>{styles}</style>
      <div className="app-wrap">
        <div className="form-card">
          <div className="form-header">
            <h1>Job <em>Application</em> Form</h1>
            <p>Please fill out all required information below</p>
          </div>

          <div className="form-body">
            {status && (
              <div className={`alert alert-${status.type}`}>
                <span>{status.type === "success" ? "✓" : "✕"}</span>
                {status.message}
              </div>
            )}

            {/* Personal Details */}
            <div className="section">
              <div className="section-label">Personal Details</div>

              <div className="row">
                <div className="field">
                  <label>First Name <span>*</span></label>
                  <input value={form.first_name} onChange={e => set("first_name", e.target.value)} placeholder="Juan" />
                </div>
                <div className="field">
                  <label>Last Name <span>*</span></label>
                  <input value={form.last_name} onChange={e => set("last_name", e.target.value)} placeholder="dela Cruz" />
                </div>
              </div>

              <div className="row">
                <div className="field">
                  <label>Contact Number <span>*</span></label>
                  <input value={form.contact_number} onChange={e => set("contact_number", e.target.value)} placeholder="+63 9XX XXX XXXX" />
                </div>
                <div className="field">
                  <label>Email Address <span>*</span></label>
                  <input type="email" value={form.email} onChange={e => set("email", e.target.value)} placeholder="juan@email.com" />
                </div>
              </div>

              <div className="row triple">
                <div className="field">
                  <label>City</label>
                  <input value={form.city} onChange={e => set("city", e.target.value)} placeholder="Manila" />
                </div>
                <div className="field">
                  <label>Province</label>
                  <input value={form.province} onChange={e => set("province", e.target.value)} placeholder="Metro Manila" />
                </div>
                <div className="field">
                  <label>Postal Code</label>
                  <input value={form.postal_code} onChange={e => set("postal_code", e.target.value)} placeholder="1000" />
                </div>
              </div>

              <div className="row single">
                <div className="field">
                  <label>Country</label>
                  <input value={form.country} onChange={e => set("country", e.target.value)} placeholder="Philippines" />
                </div>
              </div>
            </div>

            {/* Desired Pay */}
            <div className="section">
              <div className="section-label">Desired Pay</div>
              <div className="salary-display">
                <div className="salary-value">
                  {formatPeso(form.desired_pay)}<small>/ year</small>
                </div>
                <div className="salary-range">{formatPeso(salaryMin)} — {formatPeso(salaryMax)}</div>
              </div>
              <div className="slider-wrap">
                <input
                  type="range"
                  min={salaryMin} max={salaryMax} step={1000}
                  value={form.desired_pay}
                  style={{ "--pct": salaryPct + "%" }}
                  onChange={e => set("desired_pay", e.target.value)}
                />
              </div>
            </div>

            {/* Availability */}
            <div className="section">
              <div className="section-label">Availability</div>

              <div className="field" style={{ marginBottom: 16 }}>
                <label>Employment Type <span>*</span></label>
                <div className="radio-group">
                  {["Full-time", "Part-time"].map(t => (
                    <label key={t} className={`radio-pill ${form.engagement_type === t ? "selected" : ""}`}>
                      <input type="radio" name="engagement_type" value={t} checked={form.engagement_type === t} onChange={() => set("engagement_type", t)} />
                      {t}
                    </label>
                  ))}
                </div>
              </div>

              <div className="row single">
                <div className="field">
                  <label>Date Available</label>
                  <input type="date" value={form.date_available} onChange={e => set("date_available", e.target.value)} />
                </div>
              </div>
            </div>

            {/* Education */}
            <div className="section">
              <div className="section-label">Education</div>

              <div className="row">
                <div className="field">
                  <label>Highest Education</label>
                  <select value={form.highest_education} onChange={e => set("highest_education", e.target.value)}>
                    <option value="">Select level</option>
                    <option>High School</option>
                    <option>Vocational</option>
                    <option>Bachelor's Degree</option>
                    <option>Master's Degree</option>
                    <option>Doctorate</option>
                  </select>
                </div>
                <div className="field">
                  <label>College / University</label>
                  <input value={form.college_university} onChange={e => set("college_university", e.target.value)} placeholder="University name" />
                </div>
              </div>
            </div>

            {/* References */}
            <div className="section">
              <div className="section-label">References</div>

              <div className="row single" style={{ marginBottom: 16 }}>
                <div className="field">
                  <label>Referred By</label>
                  <input value={form.referred_by} onChange={e => set("referred_by", e.target.value)} placeholder="Name of referrer (if any)" />
                </div>
              </div>

              <div className="row single">
                <div className="field">
                  <label>References</label>
                  <textarea value={form.references} onChange={e => set("references", e.target.value)} placeholder="Name, Position, Contact — one per line" />
                </div>
              </div>
            </div>

            {/* File Uploads */}
            <div className="section">
              <div className="section-label">Additional Files</div>

              <div className="row" style={{ marginBottom: 0 }}>
                <div className="field">
                  <label>Transcript of Records</label>
                  <div className={`upload-zone ${form.tor_path ? "has-file" : ""}`}>
                    <input type="file" accept=".pdf,.doc,.docx,.jpg,.png" onChange={e => handleFile("tor_path", e.target.files[0])} />
                    <svg className="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                      <path d="M12 16V8m0 0l-3 3m3-3l3 3M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p>
                      <strong>{form.tor_path ? form.tor_path.name : "Upload TOR"}</strong>
                      {!form.tor_path && "PDF, DOC, JPG · Max 4MB"}
                    </p>
                  </div>
                </div>

                <div className="field">
                  <label>Certificates / Resume</label>
                  <div className={`upload-zone ${form.cert_path ? "has-file" : ""}`}>
                    <input type="file" accept=".pdf,.doc,.docx,.jpg,.png" onChange={e => handleFile("cert_path", e.target.files[0])} />
                    <svg className="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                      <path d="M12 16V8m0 0l-3 3m3-3l3 3M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p>
                      <strong>{form.cert_path ? form.cert_path.name : "Upload Certificate"}</strong>
                      {!form.cert_path && "PDF, DOC, JPG · Max 4MB"}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <button className="submit-btn" onClick={handleSubmit} disabled={loading}>
              {loading ? "Submitting…" : "Submit Application"}
            </button>
          </div>
        </div>
      </div>
    </>
  );
}

