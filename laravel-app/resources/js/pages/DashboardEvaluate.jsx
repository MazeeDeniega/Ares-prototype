import DashboardLayout from "../layouts/DashboardLayout";
import JobListTable from "../components/JobListTable";
import { useState, useCallback } from "react";

function mapApplications(applications) {
  return applications.map((app, index) => ({
    id: app.id,
    "#": index + 1,
    Name: `${app.first_name ?? ""} ${app.last_name ?? ""}`.trim(),
    Email: app.email,
    status: app.status ?? "pending",
    Resume: app.resume_path ? `/files/${app.id}/resume` : null,
  }));
}

export default function DashboardEvaluate() {
  const { job, csrf } = window.__LARAVEL__ ?? {};
  const applications = job?.applications ?? [];
  const [evaluating, setEvaluating] = useState(false);
  const [candidates, setCandidates] = useState(() => mapApplications(applications));
  const [updatingId, setUpdatingId] = useState(null);

  document.title = `Evaluate - ${job?.title ?? "Job"}`;

  const handleStatusChange = useCallback(
    async (candidateId, newStatus) => {
      setUpdatingId(candidateId);
      setCandidates((current) =>
        current.map((row) =>
          row.id === candidateId ? { ...row, status: newStatus } : row
        )
      );

      try {
        const res = await fetch(`/api/candidates/${candidateId}`, {
          method: "PATCH",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...(csrf ? { "X-CSRF-TOKEN": csrf } : {}),
          },
          credentials: "include",
          body: JSON.stringify({ status: newStatus }),
        });

        if (!res.ok) {
          throw new Error(`Failed to update status (${res.status})`);
        }

        const updated = await res.json();
        setCandidates((current) =>
          current.map((row) =>
            row.id === candidateId ? { ...row, status: updated.status } : row
          )
        );
      } catch (err) {
        console.error("Failed to update candidate status:", err);
        setCandidates(mapApplications(applications));
      } finally {
        setUpdatingId(null);
      }
    },
    [applications, csrf]
  );

  const handleEvaluate = () => {
    if (!job) return;
    setEvaluating(true);
    window.location.href = `/screening/${job.id}/evaluate`;
  };

  return (
    <DashboardLayout title={job?.title ?? "Job"} subtitle="Applicants for this job">
      <JobListTable
        jobTitle={job?.title ?? "Job"}
        candidateCount={applications.length}
        candidates={candidates}
        onStatusChange={handleStatusChange}
        updatingId={updatingId}
        onEvaluate={handleEvaluate}
        evaluating={evaluating}
      />
    </DashboardLayout>
  );
}
