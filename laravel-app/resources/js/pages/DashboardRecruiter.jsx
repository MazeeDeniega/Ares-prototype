import { useState } from "react";
import './styles/dashboardadmin.css';
import AddJobModal from "../components/AddJobModal";
import DashboardLayout from "../layouts/DashboardLayout";
import { BsPencilSquare, BsTrash } from 'react-icons/bs';

export default function DashboardRecruiter() {
  document.title = "Recruiter Dashboard";
  const csrf = window.__LARAVEL__?.csrf;
  const [jobs, setJobs] = useState(window.__LARAVEL__?.jobs || []);
  const [flash, setFlash] = useState({ success: null, error: null });
  const [showModal, setShowModal] = useState(false);

  const stripHtml = (html) => {
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent || div.innerText || '';
  };

  const handleAddJob = async ({ title, description }) => {
    const response = await fetch("/jobs", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Accept": 'application/json',
        "X-CSRF-TOKEN": csrf
      },
      body: JSON.stringify({ title, description }),
    });

    if (response.ok || response.redirected) {
      window.location.reload();
    } else {
      const err = await response.json().catch(() => ({}));
      throw new Error(err.message ?? 'Failed to save');
    }
  };

  const handleDelete = async (jobId) => {
    if (!confirm("Delete this job?")) return;
    await fetch(`/jobs/${jobId}`, {
      method: "DELETE",
      headers: { 'X-CSRF-TOKEN': csrf }
    });
    window.location.reload();
  };

  return (
    <DashboardLayout title="Your Jobs">
      <div className="rec-main-cont">

        {/* ── page header: title + New Job button ── */}
        <div className="rec-page-header">
          <h2 className="rec-page-title">Your Jobs</h2>
          <button className="add-job-btn" type="button" onClick={() => setShowModal(true)}>
            + New Job
          </button>
        </div>

        {/* ── jobs table ── */}
        <div className="rec-inner-cont">
          <div className="table-top">
            <div className="flash-message">
              {flash.success && <p style={{ color: 'green' }}>{flash.success}</p>}
              {flash.error   && <p style={{ color: 'red'   }}>{flash.error}</p>}
            </div>
            <div className="header-btn">
              <a href="/preferences/edit">
                <button className="edit-pref-btn" type="button">Default Preferences</button>
              </a>
            </div>
          </div>

          <table className="table-main">
            <thead>
              <tr className="table-heading">
                <th>Job Title</th>
                <th>Description</th>
                <th>Applicants</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody className="table-body-rec">
              {jobs.map((job) => (
                <tr
                  className="table-row-rec"
                  key={job.id}
                  onClick={() => (window.location.href = `/screening/${job.id}`)}
                >
                  <td>{job.title}</td>
                  <td className="table-job-desc">{stripHtml(job.description)}</td>
                  <td style={{ textAlign: "center" }}>
                    {job.applications_count ?? job.applications?.length ?? 0}
                  </td>
                  <td className="action-btns" onClick={(e) => e.stopPropagation()}>
                    <a href={`/jobs/${job.id}/preferences`}>
                      <button className="icon-btn edit-icon-btn" type="button" title="Edit preferences">
                        <BsPencilSquare />
                      </button>
                    </a>
                    <button
                      className="icon-btn delete-icon-btn"
                      type="button"
                      title="Delete job"
                      onClick={() => handleDelete(job.id)}
                    >
                      <BsTrash />
                    </button>
                  </td>
                </tr>
              ))}
              {jobs.length === 0 && (
                <tr>
                  <td colSpan={4} style={{ textAlign: 'center', padding: '24px', color: '#888' }}>
                    No jobs posted yet.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* ── add job modal ── */}
        <AddJobModal
          isOpen={showModal}
          onClose={() => setShowModal(false)}
          onSave={handleAddJob}
        />
      </div>
    </DashboardLayout>
  );
}