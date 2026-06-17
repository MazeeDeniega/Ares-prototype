import React, { useState } from 'react';
import Sidebar from '../components/Sidebar';
import { BsList } from 'react-icons/bs';
import '../../css/layouts/dashboardlayout.css';
import CandidatesTable from 'laravel-app/resources/js/components/CandidatesTable.jsx';

/** This is used for the base layout of ALL recruiter related pages
 *
 *  title    (string)    - Page heading shown in desktop header & mobile topbar
 *  subtitle (string)    - Small subtitle line (e.g. "Applied to all jobs")
 *  actions  (ReactNode) - Optional right-side actions (e.g. Save, edit preference, New job button (Refer to Figma for visualization))
 *  children (ReactNode) - Page content (e.g. Candidate page, evaluate page, ranking page etc.)
 */
export default function DashboardLayout({ title, subtitle, actions, children }) {
  const [drawerOpen, setDrawerOpen] = useState(false);

  return (
    <div className="dashboard-layout">

      {/* Desktop: static sidebar */}
      <div className="dashboard-layout__sidebar">
        <Sidebar isOpen={false} onClose={() => {}} />
      </div>

      {/* Mobile: overlay drawer */}
      <Sidebar
        isOpen={drawerOpen}
        onClose={() => setDrawerOpen(false)}
      />

      {/* Main content */}
      <main className="dashboard-layout__main">

        {/* Mobile top bar */}
        <div className="dashboard-topbar">
          <div className="dashboard-topbar__row">
            <button
              className="sidebar-hamburger"
              onClick={() => setDrawerOpen(true)}
              aria-label="Open menu"
            >
              <BsList />
            </button>
            <div>
              <span className="dashboard-topbar__title">{All Candidates}</span>
              {subtitle && (
                <span className="dashboard-topbar__subtitle">{subtitle}</span>
              )}
            </div>
          </div>
        </div>

        <CandidatesTable />
      </main>
    </div>
  );
};
