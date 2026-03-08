import { useState, useEffect } from "react";
import { Link } from "react-router";
import './styles/dashboardadmin.css';
import NavBar from "../components/NavBar";

export default function DashboardRecruiter() {
  document.title= "Recruiter Dashboard";
  const csrf = window.__LARAVEL__?.csrf;
  const [jobs, setJobs] = useState(window.__LARAVEL__?.jobs || []);
  const [flash, setFlash] = useState({ success: null, error: null });
  const [showModal, setShowModal] = useState(false)
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

    const response = await fetch(`/jobs/${jobId}`, { 
      method: "DELETE",
      headers: {'X-CSRF-TOKEN': csrf }
    });

    if (response.ok) {
      setJobs(jobs.filter(job => job.id !== jobId));
      setFlash({ success: "Job deleted.", error: null });
      console.log("Job deleted");
    } else {
      setFlash({ success: null, error: "Failed to delete job." });
    }
  };

  const trunc = (text, length = 50) =>
    text.length > length ? text.slice(0, length) + '...' : text;

  return (
    <>
    <NavBar name='Recruiter'/>

    <div className="main-cont">
      <br />
      <div className="heading-rec-cont">
        <h3>Your Jobs</h3>
        <button className="add-job-btn" type="button" onClick={() => setShowModal(true)}>+ New Job</button>        
      </div>

      {/* ── modal to add job ── */}
      {showModal && (                                      // closes modal when clicked outside
        <div className="modal-main-cont" onClick={(e) => { if (e.target === e.currentTarget) setShowModal(false); }}>
          <div className="modal-inner-cont">
            
            <div className="modal-header">
            <h3>Add New Job</h3>
            <hr />
            {flash.success && <p>{flash.success}</p>}
            {flash.error && <p>{flash.error}</p>}
            </div>

            <div className="add-job-form">
              
              <form onSubmit={handleAddJob}>
                <input
                  type="text"
                  placeholder="Job Title (e.g. Backend Dev)"
                  required
                />
                <textarea
                  placeholder="Job Description"
                  required
                />
                <button type="submit">Save Job</button>
                <button type="button" onClick={() => setShowModal(false)}>Cancel</button>
              </form>
            </div>
          </div>
        </div>  
      )}
      
      <hr />

      {/* ── jobs table ── */}
      <div className="table-cont">
        <table className='table-main'>
          <thead>
            <tr className="table-heading">
              <th>Job Title</th>
              <th>Description</th>
              <th>Applicants</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody className='table-body'>
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
                 <td className='action-btns' onClick={(e) => e.stopPropagation()}>  
                  <a  href={`/jobs/${job.id}/preferences`}><button className="edit-pref-btn" type="button"> 
                    Preference {/* Don't forget to use <Link> Tag  */}
                  </button></a>
                  <button className='delete-btn' onClick={() => handleDelete(job.id)}>
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
    </div>
    </>
  );
}