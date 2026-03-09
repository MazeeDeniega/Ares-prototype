import { useState } from "react";
import "./styles/JobApplicationForm.css";

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
  );
}
