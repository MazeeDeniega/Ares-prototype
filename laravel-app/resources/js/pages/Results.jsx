import DashboardLayout from '../layouts/DashboardLayout';
import RankingTable from '../components/RankingTable';

export default function Results() {
  const { job, results, pref } = window.__LARAVEL__ ?? {};

  document.title = `Results - ${job?.title ?? 'Job'}`;

  return (
    <DashboardLayout title="Ranking Results" subtitle={job?.title ?? ''}>
      <RankingTable
        jobTitle={job?.title ?? 'Job'}
        rankings={results ?? []}
        pref={pref ?? {}}
        onEditPreferences={() => {
          if (job) window.location.href = `/jobs/${job.id}/preferences`;
        }}
      />
    </DashboardLayout>
  );
};
