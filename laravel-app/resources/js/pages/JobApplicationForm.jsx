import { useState } from "react";
import "./styles/jobapplicationform.css";

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
  resume_path: null,
  cert_path: null,
};

export default function JobApplicationForm() {
  const { csrf, job, flash } = window.__LARAVEL__ ?? {};
  const [form, setForm] = useState(initialForm);
  const [loading, setLoading] = useState(false);
  const [status, setStatus] = useState(null);
  const [errors, setErrors] = useState({});

  console.log('Job data:', job); 
  console.log('Job ID:', job?.id); 

  const set = (key, value) => {
    setForm(f => ({ ...f, [key]: value }));
    if (errors[key]) {
      setErrors(prev => ({ ...prev, [key]: null }));
    }
  };

  const salaryMin = 10000, salaryMax = 200000;
  const salaryNum = parseInt(form.desired_pay) || salaryMin;
  const salaryPct = ((salaryNum - salaryMin) / (salaryMax - salaryMin)) * 100;

  const formatPeso = n => "₱" + parseInt(n).toLocaleString("en-PH");

  const handleFile = (key, file) => set(key, file || null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setStatus(null);
    setErrors({});

    try {
      const data = new FormData();

      const textFields = [
        "first_name","last_name","email","city","province",
        "postal_code","country","desired_pay","engagement_type",
        "date_available","highest_education","college_university",
        "referred_by","references"
      ];
      textFields.forEach(k => {
        data.append(k, form[k] || "");
      });

      if (form.tor_path instanceof File) {
        data.append('tor_path', form.tor_path);
      }
      if (form.resume_path instanceof File) {
        data.append('resume_path', form.resume_path);
      }

      const res = await fetch(`/apply/${job.id}`, {
        method: "POST",
        headers: {
          "X-CSRF-TOKEN": csrf,
          "Accept": "application/json",
        },
        body: data,
      });

      if (!res.ok) {
        // FIX: Define contentType before using it
        const contentType = res.headers.get("content-type");
        
        if (contentType && contentType.includes("application/json")) {
          const errData = await res.json();
          if (errData.errors) {
            setErrors(errData.errors);
            setStatus({
              type: "error",
              message: "Please fix the highlighted errors and try again."
            });
            setLoading(false);
            return;
          }
        }
        
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || `Server error ${res.status}`);
      }

      const result = await res.json();
      setStatus({ type: "success", message: "Application submitted successfully!" });
      setForm(initialForm);
    } catch (e) {
      console.error('Submission error:', e);
      setStatus({ type: "error", message: e.message || "Submission failed. Please try again." });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="app-wrap">
      <div className="form-card">
        <div className="form-header">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <h1>Job Application Form</h1>
            <a href="/jobs" style={{ textDecoration: 'none', color: '#333' }}>
              ← Back to Jobs
            </a>
          </div>
          <p>Please fill out all required information below</p>
        </div>

        <div className="form-body">
          {status && (
            <div className={`alert alert-${status.type}`}>
              <span>{status.type === "success" ? "✓" : "✕"}</span>
              {status.message}
            </div>
          )}

          {/* {Object.keys(errors).length > 0 && (
            <div className="alert alert-error" style={{ marginTop: '10px' }}>
              <strong>Errors:</strong>
              <ul style={{ margin: '5px 0 0 20px' }}>
                {Object.entries(errors).map(([field, messages]) => (
                  <li key={field}>{messages[0]}</li>
                ))}
              </ul>
            </div>
          )} */}

          <form onSubmit={handleSubmit}>

          {/* Personal Details */}
          <div className="section">
            <div className="section-label">Personal Details</div>

            <div className="row">
              <div className="field">
                <label>First Name <span>*</span></label>
                <input 
                  value={form.first_name} 
                  onChange={e => set("first_name", e.target.value)} 
                  placeholder="Juan" 
                  style={{ borderColor: errors.first_name ? 'red' : '#ccc' }}
                />
                {errors.first_name && <small style={{ color: 'red' }}>{errors.first_name[0]}</small>}
              </div>
              <div className="field">
                <label>Last Name <span>*</span></label>
                <input 
                  value={form.last_name} 
                  onChange={e => set("last_name", e.target.value)} 
                  placeholder="dela Cruz" 
                  style={{ borderColor: errors.last_name ? 'red' : '#ccc' }}
                />
                {errors.last_name && <small style={{ color: 'red' }}>{errors.last_name[0]}</small>}
              </div>
            </div>

            <div className="row">
              <div className="field">
                <label>Contact Number <span>*</span></label>
                <input 
                  value={form.contact_number} 
                  onChange={e => set("contact_number", e.target.value)} 
                  placeholder="+63 9XX XXX XXXX" 
                  style={{ borderColor: errors.contact_number ? 'red' : '#ccc' }}
                />
                {errors.contact_number && <small style={{ color: 'red' }}>{errors.contact_number[0]}</small>}
              </div>
              <div className="field">
                <label>Email Address <span>*</span></label>
                <input 
                  type="email" 
                  value={form.email} 
                  onChange={e => set("email", e.target.value)} 
                  placeholder="juan@email.com" 
                  style={{ borderColor: errors.email ? 'red' : '#ccc' }}
                />
                {errors.email && <small style={{ color: 'red' }}>{errors.email[0]}</small>}
              </div>
            </div>

            <div className="row triple">
              <div className="field">
                <label>City</label>
                <input 
                  value={form.city} 
                  onChange={e => set("city", e.target.value)} 
                  placeholder="Manila" 
                  style={{ borderColor: errors.city ? 'red' : '#ccc' }}
                />
                {errors.city && <small style={{ color: 'red' }}>{errors.city[0]}</small>}
              </div>
              <div className="field">
                <label>Province</label>
                <input 
                  value={form.province} 
                  onChange={e => set("province", e.target.value)} 
                  placeholder="Metro Manila" 
                  style={{ borderColor: errors.province ? 'red' : '#ccc' }}
                />
                {errors.province && <small style={{ color: 'red' }}>{errors.province[0]}</small>}
              </div>
              <div className="field">
                <label>Postal Code</label>
                <input 
                  value={form.postal_code} 
                  onChange={e => set("postal_code", e.target.value)} 
                  placeholder="1000" 
                  style={{ borderColor: errors.postal_code ? 'red' : '#ccc' }}
                />
                {errors.postal_code && <small style={{ color: 'red' }}>{errors.postal_code[0]}</small>}
              </div>
            </div>

            <div className="row single">
              <div className="field">
                <label>Country</label>
                <input 
                  value={form.country} 
                  onChange={e => set("country", e.target.value)} 
                  placeholder="Philippines" 
                  style={{ borderColor: errors.country ? 'red' : '#ccc' }}
                />
                {errors.country && <small style={{ color: 'red' }}>{errors.country[0]}</small>}
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
                <input 
                  type="date" 
                  value={form.date_available} 
                  onChange={e => set("date_available", e.target.value)} 
                  style={{ borderColor: errors.date_available ? 'red' : '#ccc' }}
                />
                {errors.date_available && <small style={{ color: 'red' }}>{errors.date_available[0]}</small>}
              </div>
            </div>
          </div>

          {/* Education */}
          <div className="section">
            <div className="section-label">Education</div>

            <div className="row">
              <div className="field">
                <label>Highest Education</label>
                <select 
                  value={form.highest_education} 
                  onChange={e => set("highest_education", e.target.value)}
                  style={{ borderColor: errors.highest_education ? 'red' : '#ccc' }}
                >
                  <option value="">Select level</option>
                  <option>High School</option>
                  <option>Vocational</option>
                  <option>Bachelor's Degree</option>
                  <option>Master's Degree</option>
                  <option>Doctorate</option>
                </select>
                {errors.highest_education && <small style={{ color: 'red' }}>{errors.highest_education[0]}</small>}
              </div>
              <div className="field">
                <label>College / University</label>
                <input 
                  value={form.college_university} 
                  onChange={e => set("college_university", e.target.value)} 
                  placeholder="University name" 
                  style={{ borderColor: errors.college_university ? 'red' : '#ccc' }}
                />
                {errors.college_university && <small style={{ color: 'red' }}>{errors.college_university[0]}</small>}
              </div>
            </div>
          </div>

          {/* References */}
          <div className="section">
            <div className="section-label">References</div>

            <div className="row single" style={{ marginBottom: 16 }}>
              <div className="field">
                <label>Referred By</label>
                <input 
                  value={form.referred_by} 
                  onChange={e => set("referred_by", e.target.value)} 
                  placeholder="Name of referrer (if any)" 
                  style={{ borderColor: errors.referred_by ? 'red' : '#ccc' }}
                />
                {errors.referred_by && <small style={{ color: 'red' }}>{errors.referred_by[0]}</small>}
              </div>
            </div>

            <div className="row single">
              <div className="field">
                <label>References</label>
                <textarea 
                  value={form.references} 
                  onChange={e => set("references", e.target.value)} 
                  placeholder="Name, Position, Contact — one per line" 
                  style={{ borderColor: errors.references ? 'red' : '#ccc' }}
                />
                {errors.references && <small style={{ color: 'red' }}>{errors.references[0]}</small>}
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
                <label>Resume</label>
                <div className={`upload-zone ${form.resume_path ? "has-file" : ""}`}>
                  <input type="file" accept=".pdf,.doc,.docx,.jpg,.png" onChange={e => handleFile("resume_path", e.target.files[0])} />
                  <svg className="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                    <path d="M12 16V8m0 0l-3 3m3-3l3 3M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                  <p>
                    <strong>{form.resume_path ? form.resume_path.name : "Upload Resume"}</strong>
                    {!form.resume_path && "PDF, DOC, JPG · Max 4MB"}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <button className="submit-btn" type="submit" disabled={loading}>
            {loading ? "Submitting…" : "Submit Application"}
          </button>
          </form>
        </div>
      </div>
    </div>
  );
}