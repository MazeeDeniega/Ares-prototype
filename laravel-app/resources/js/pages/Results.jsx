import DashboardLayout from "../layouts/DashboardLayout";
import RankingTable from "../components/RankingTable";
import { useState, useCallback } from "react";

export default function Results() {
  const { job, results, pref, csrf } = window.__LARAVEL__ ?? {};
  const [rankings, setRankings] = useState(results ?? []);
  const [updatingId, setUpdatingId] = useState(null);

  document.title = `Results - ${job?.title ?? ""}`;

  const handleStatusChange = useCallback(
    async (applicationId, newStatus) => {
      setUpdatingId(applicationId);
      setRankings((current) =>
        current.map((row) =>
          row.application_id === applicationId ? { ...row, status: newStatus } : row
        )
      );

      try {
        const res = await fetch(`/api/candidates/${applicationId}`, {
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
        setRankings((current) =>
          current.map((row) =>
            row.application_id === applicationId
              ? { ...row, status: updated.status }
              : row
          )
        );
      } catch (err) {
        console.error("Failed to update candidate status:", err);
        setRankings(results ?? []);
      } finally {
        setUpdatingId(null);
      }
    },
    [csrf, results]
  );

  return (
    <DashboardLayout title="Ranking Results" subtitle={job?.title ?? ""}>
      <RankingTable
        jobTitle={job?.title ?? "Job"}
        rankings={rankings}
        pref={pref ?? {}}
        onStatusChange={handleStatusChange}
        updatingId={updatingId}
        onEditPreferences={() => {
          if (job) window.location.href = `/jobs/${job.id}/preferences`;
        }}
      />
    </DashboardLayout>
  );
}
