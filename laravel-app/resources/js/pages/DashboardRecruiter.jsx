import { useState, useEffect } from "react";
import './styles/dashboardadmin.css';
import NavBar from "../components/NavBar";

export default function DashboardRecruiter() {
  const csrf = window.__LARAVEL__?.csrf;
  const [jobs, setJobs] = useState(window.__LARAVEL__?.jobs || []);
  const [flash, setFlash] = useState({ success: null, error: null });
  // const [error, setError] = useState('');

  // add job 
  const handleAddJob = async (e) => {
    e.preventDefault();
    const form = e.target;
    
    const response = await apiFetch("/jobs", {
      method: "POST",
      headers: { 
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrf
      },
      body: JSON.stringify({ 
        title: form.title.value, 
        description: form.desc.value }),
    });

    if (response.ok) {
      const newJob = await response.json();
      setJobs([...jobs, newJob]);
      form.reset();
      setFlash({ success: "Job added successfully.", error: null });
    } else {
      const data = await response.json();
      setFlash({ success: null, error: data.message || "Failed to add job." });
    }
  };

  // delete job
  const handleDelete = async (jobId) => {
    if (!confirm("Delete this job?")) return;

    const response = await apiFetch(`/jobs/${jobId}`, { 
      method: "DELETE",
      headers: {'X-CSRF-TOKEN': csrf }
    });

    if (response.ok) {
      setJobs(jobs.filter(job => job.id !== jobId));
      setFlash({ success: "Job deleted.", error: null });
    } else {
      setFlash({ success: null, error: "Failed to delete job." });
    }
  };

  const trunc = (text, length = 50) =>
    text.length > length ? text.slice(0, length) + '...' : text;

  return (
    <div>
      <title>Recruiter Dashboard</title>

      <NavBar name='Recruiter'/>

      {/* ── flash messages ── */}
      {flash.success && <p>{flash.success}</p>}
      

      {/* ── add job form ── */}
      <h3>Add New Job</h3>
      {flash.error && <p>{flash.error}</p>}
      <form onSubmit={handleAddJob}>
        <input
          type="text"
          placeholder="Job Title (e.g. Backend Dev)"
          required
        />
        <textarea
          rows={4}
          placeholder="Job Description"
          required
        />
        <button type="submit">Save Job</button>
      </form>

      <hr />

      {/* ── jobs table ── */}
      <h3>Your Jobs (Click to Screen Applicants)</h3>
      <table>
        <thead>
          <tr>
            <th>Job Title</th>
            <th>Description</th>
            <th>Applicants</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          {jobs.map((job) => (
            <tr
              key={job.id}
              onClick={() => (window.location.href = `/screening/${job.id}`)}
            >
              <td>{job.title}</td>
              <td>
                {trunc(job.description)}
              </td>
              <td>{job.applications_count ?? job.applications?.length ?? 0}</td>
              <td onClick={(e) => e.stopPropagation()}>
                <a href={`/jobs/${job.id}/preferences`}>
                  Edit Preference
                </a>
                <button onClick={() => handleDelete(job.id)}>
                  Delete
                </button>
              </td>
            </tr>
          ))}
          {jobs.length === 0 && (
            <tr>
              <td colSpan={4}>No jobs posted yet.</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}