import { useState, useEffect } from "react";

const getCookie = (name) => {
  const match = document.cookie.match(new RegExp("(^| )" + name + "=([^;]+)"));
  return match ? decodeURIComponent(match[2]) : null;
};

const apiFetch = (url, options = {}) =>
  fetch(url, {
    credentials: "include",
    headers: { Accept: "application/json", "X-XSRF-TOKEN": getCookie("XSRF-TOKEN"), ...options.headers },
    ...options,
  });

export default function DashboardRecruiter() {
  const [user, setUser] = useState(null);
  const [jobs, setJobs] = useState([]);
  const [title, setTitle] = useState("");
  const [desc, setDesc] = useState("");
  const [flash, setFlash] = useState({ success: null, error: null });

  // fetch auth user
  useEffect(() => {
    apiFetch("/api/user")
      .then((r) => r.json())
      .then(setUser)
      .catch(() => setFlash({ success: null, error: "Could not load user." }));
  }, []);

  // fetch jobs
  const fetchJobs = () => {
    apiFetch("/api/jobs")
      .then((r) => r.json())
      .then(setJobs)
      .catch(() => setFlash({ success: null, error: "Could not load jobs." }));
  };

  useEffect(() => { fetchJobs(); }, []);

  // add job 
  const handleAddJob = async (e) => {
    e.preventDefault();
    const res = await apiFetch("/jobs", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ title, description: desc }),
    });
    if (res.ok) {
      setFlash({ success: "Job added successfully.", error: null });
      setTitle(""); setDesc("");
      fetchJobs();
    } else {
      setFlash({ success: null, error: "Failed to add job." });
    }
  };

  // delete job
  const handleDelete = async (e, jobId) => {
    e.stopPropagation();
    if (!confirm("Delete this job?")) return;
    const res = await apiFetch(`/jobs/${jobId}`, { method: "DELETE" });
    if (res.ok) {
      setFlash({ success: "Job deleted.", error: null });
      fetchJobs();
    } else {
      setFlash({ success: null, error: "Failed to delete job." });
    }
  };

  return (
    <div style={s.page}>
      <title>Recruiter Dashboard</title>

      <h2>Recruiter Dashboard from react</h2>

      <div style={s.nav}>
        <span>Welcome, <strong>{user ? user.name : "Loading…"}</strong></span>
        <a href="/preferences/edit">Preferences</a>
      </div>

      <hr />

      {/* ── flash messages ── */}
      {flash.success && <p style={s.success}>{flash.success}</p>}
      {flash.error   && <p style={s.error}>{flash.error}</p>}

      {/* ── add job form ── */}
      <h3>Add New Job</h3>
      <form onSubmit={handleAddJob} style={{ maxWidth: "500px", display: "flex", flexDirection: "column", gap: "10px" }}>
        <input
          style={s.input}
          type="text"
          placeholder="Job Title (e.g. Backend Dev)"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          required
        />
        <textarea
          style={s.textarea}
          rows={4}
          placeholder="Job Description"
          value={desc}
          onChange={(e) => setDesc(e.target.value)}
          required
        />
        <button type="submit" style={s.btn}>Save Job</button>
      </form>

      <hr />

      {/* ── jobs table ── */}
      <h3>Your Jobs (Click to Screen Applicants)</h3>
      <table style={s.table}>
        <thead>
          <tr>
            <th style={s.th}>Job Title</th>
            <th style={s.th}>Description</th>
            <th style={s.th}>Applicants</th>
            <th style={s.th}>Action</th>
          </tr>
        </thead>
        <tbody>
          {jobs.map((job) => (
            <tr
              key={job.id}
              style={s.trHover}
              onClick={() => (window.location.href = `/screening/${job.id}`)}
            >
              <td style={s.td}>{job.title}</td>
              <td style={s.td}>
                {job.description?.length > 50
                  ? job.description.slice(0, 50) + "…"
                  : job.description}
              </td>
              <td style={s.td}>{job.applications_count ?? job.applications?.length ?? 0}</td>
              <td style={s.td} onClick={(e) => e.stopPropagation()}>
                <a href={`/jobs/${job.id}/preferences`} style={{ marginRight: "8px" }}>
                  Edit Preference
                </a>
                <button style={s.btnDanger} onClick={(e) => handleDelete(e, job.id)}>
                  Delete
                </button>
              </td>
            </tr>
          ))}
          {jobs.length === 0 && (
            <tr>
              <td style={s.td} colSpan={4}>No jobs posted yet.</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}