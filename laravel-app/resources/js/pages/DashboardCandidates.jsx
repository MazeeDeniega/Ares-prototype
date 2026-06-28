import DashboardLayout from '../layouts/DashboardLayout';
import CandidatesTable from '../components/CandidatesTable';
import "../../css/components/tables.css";
import * as React from "react";
import { useTable } from "react-table";

const statusStyles = {
  Pending: { backgroundColor: "#FF8C00", color: "#fff" },
  Approved: { backgroundColor: "#28a745", color: "#fff" },
  Rejected: { backgroundColor: "#dc3545", color: "#fff" },
  Interview: { backgroundColor: "#FFEB3B", color: "#000" },
};

function StatusBadge({ status }) {
  const style = statusStyles[status] || {};
  return (
    <span
      style={{
        ...style,
        padding: "4px 12px",
        borderRadius: "12px",
        fontWeight: "bold",
        fontSize: "0.85rem",
        display: "inline-block",
      }}
    >
      {status}
    </span>
  );
}

export default function DashboardCandidates() {
  const [data, setData] = React.useState([]);
  const [loading, setLoading] = React.useState(true);

  React.useEffect(() => {
    fetch('/api/candidates', {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'include',
    })
      .then(res => res.json())
      .then(json => setData(json))
      .catch(err => console.error('Failed to fetch candidates:', err))
      .finally(() => setLoading(false));
  }, []);

  const columns = React.useMemo(
    () => [
      { Header: "Name", accessor: "Name" },
      { Header: "Contact", accessor: "Contact" },
      { Header: "Job Position", accessor: "job_position" },
      {
        Header: "Status",
        accessor: "status",
        Cell: ({ value }) => <StatusBadge status={value} />,
      },
      {
        Header: "Details",
        accessor: "details",
        Cell: ({ value }) => (
          <a href={value} target="_blank" rel="noopener noreferrer" style={{ color: "#1a73e8", textDecoration: "underline" }}>
            View Resume
          </a>
        ),
      },
    ],
    []
  );

  const { getTableProps, getTableBodyProps, headerGroups, rows, prepareRow } =
    useTable({ columns, data });

  return (
    <DashboardLayout
      title="All Candidates"
      subtitle="Applied to all jobs"
      children={
        <div className="candidates-page">

        <div className="themed-table-wrapper">
          <table {...getTableProps()}>
            <thead>
              {headerGroups.map((headerGroup) => (
                <tr {...headerGroup.getHeaderGroupProps()}>
                  {headerGroup.headers.map((column) => (
                    <th {...column.getHeaderProps()}>
                      {column.render("Header")}
                    </th>
                  ))}
                </tr>
              ))}
            </thead>
            <tbody {...getTableBodyProps()}>
              {loading ? (
                Array.from({ length: 5 }).map((_, i) => (
                    <tr className="table-row-rec table-row-skeleton" key={`skeleton-${i}`}>
                      <td><span className="skeleton-cell skeleton-title" /></td>
                      <td><span className="skeleton-cell skeleton-desc" /></td>
                      <td style={{ textAlign: 'center' }}><span className="skeleton-cell skeleton-count" /></td>
                      <td className="action-btns">
                        <span className="skeleton-cell skeleton-btn" />
                        <span className="skeleton-cell skeleton-btn" />
                      </td>
                    </tr>
                  ))
              ) : (
                rows.map((row) => {
                prepareRow(row);
                return (
                  <tr {...row.getRowProps()}>
                    {row.cells.map((cell) => (
                      <td className="candidates-row" {...cell.getCellProps()}>{cell.render("Cell")}</td>
                    ))}
                  </tr>
                );
              })
            )}

            </tbody>
          </table>
        </div>
      </div>

      }
    >
      {/* <CandidatesTable /> */}

      
    </DashboardLayout>
  );
}