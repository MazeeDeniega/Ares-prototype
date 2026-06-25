import "./styles/Tables.css";
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

function EvaluateButton({ onClick }) {
  return (
    <button
      onClick={onClick}
      style={{
        backgroundColor: "#4a7dff",
        color: "#fff",
        border: "none",
        borderRadius: "8px",
        padding: "10px 24px",
        fontSize: "1rem",
        fontWeight: "600",
        cursor: "pointer",
      }}
    >
      Evaluate
    </button>
  );
}

// candidates: [{ "#": 1, Name, Email, status, Resume }]
function JobListTable({
  jobTitle = "Job Title",
  candidateCount = 0,
  onEvaluate = () => {},
  candidates = [],
}) {
  const data = React.useMemo(() => candidates, [candidates]);
  const columns = React.useMemo(
    () => [
      { Header: "#", accessor: "#" },
      { Header: "Name", accessor: "Name" },
      { Header: "Email", accessor: "Email" },
      {
        Header: "Status",
        accessor: "status",
        Cell: ({ value }) => <StatusBadge status={value} />,
      },
      {
        Header: "Resume",
        accessor: "Resume",
        Cell: ({ value }) =>
          value ? (
            <a href={value} 
              target="_blank" 
              rel="noopener noreferrer"
              style={{ color: "#1a73e8", textDecoration: "underline" }}>
              View Resume
            </a>
          ) : (
            "No Resume"
          ),
      },
    ],
    []
  );

  const { getTableProps, getTableBodyProps, headerGroups, rows, prepareRow } =
    useTable({ columns, data });

  return (
    <div className="JobListTable">
      <div className="container">
        <div
          style={{
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
            marginBottom: "16px",
          }}
        >
          <h1 style={{ margin: 0 }}>
            {jobTitle} ({candidateCount})
          </h1>
          <EvaluateButton onClick={onEvaluate} />
        </div>

        <table {...getTableProps()}>
          <thead>
            {headerGroups.map((headerGroup) => (
              <tr {...headerGroup.getHeaderGroupProps()}>
                {headerGroup.headers.map((column) => (
                  <th {...column.getHeaderProps()}>{column.render("Header")}</th>
                ))}
              </tr>
            ))}
          </thead>
          <tbody {...getTableBodyProps()}>
            {rows.map((row) => {
              prepareRow(row);
              return (
                <tr {...row.getRowProps()}>
                  {row.cells.map((cell) => (
                    <td {...cell.getCellProps()}> {cell.render("Cell")} </td>
                  ))}
                </tr>
              );
            })}
            {rows.length === 0 && (
              <tr>
                <td colSpan={5} style={{ textAlign: "center", padding: "20px" }}>
                  No applicants yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
export default JobListTable;