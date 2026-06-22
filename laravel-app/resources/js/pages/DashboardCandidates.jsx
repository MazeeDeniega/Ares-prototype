import React from 'react';
import DashboardLayout from '../layouts/DashboardLayout';
import CandidatesTable from '../components/CandidatesTable';

export default function DashboardCandidates() {
  return (
    <DashboardLayout
      title="All Candidates"
      subtitle="Applied to all jobs"
    >
      <CandidatesTable />
    </DashboardLayout>
  );
}