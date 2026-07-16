import "../../css/components/tables.css";
import * as React from "react";
import { useTable } from "react-table";
import { StatusSelect, normalizeStatusValue } from "./CandidateStatus";

// candidates: [{ id, "#": 1, Name, Email, status, Resume }]
function JobListTable({
  jobTitle = "Job Title",
  candidateCount = 0,
  onEvaluate = () => {},
  onStatusChange,
  updatingId = null,
  candidates = [],
  evaluating,
}) {
  const columns = React.useMemo(
    () => [
      { Header: "#", accessor: "#" },
      { Header: "Name", accessor: "Name" },
      { Header: "Email", accessor: "Email" },
      {
        Header: "Status",
        accessor: "status",
        Cell: ({ row }) =>
          row.original.id && onStatusChange ? (
            <StatusSelect
              value={normalizeStatusValue(row.original.status)}
              onChange={(newStatus) => onStatusChange(row.original.id, newStatus)}
              disabled={updatingId === row.original.id}
            />
          ) : (
            row.original.status
          ),
      },
      {
        Header: "Resume",
        accessor: "Resume",
        Cell: ({ value }) =>
          value ? (
            <a
              href={value}
              target="_blank"
              rel="noopener noreferrer"
              style={{ color: "#1a73e8", textDecoration: "underline" }}
            >
              View Resume
            </a>
          ) : (
            "No Resume"
          ),
      },
    ],
    [onStatusChange, updatingId]
  );

  const { getTableProps, getTableBodyProps, headerGroups, rows, prepareRow } =
    useTable({ columns, data: candidates });

  return (
    <div className="JobListTable">
      <div>
        <div className="table-top-bar">
          <h1 className="job-title">
            {jobTitle} ({candidateCount})
          </h1>
          <button className="evaluate-btn" onClick={onEvaluate}>
            {evaluating ? "Evaluating…" : "Evaluate"}
          </button>
        </div>

        <table {...getTableProps()}>
          <thead>
            {headerGroups.map((headerGroup) => {
              const { key, ...headerGroupProps } = headerGroup.getHeaderGroupProps();
              return (
                <tr key={key} {...headerGroupProps}>
                  {headerGroup.headers.map((column) => {
                    const { key: colKey, ...columnProps } = column.getHeaderProps();
                    return (
                      <th key={colKey} {...columnProps}>{column.render("Header")}</th>
                    );
                  })}
                </tr>
              );
            })}
          </thead>
          <tbody {...getTableBodyProps()}>
            {rows.length === 0 ? (
              <tr>
                <td colSpan={5} style={{ textAlign: "center", padding: "20px" }}>
                  No applicants yet.
                </td>
              </tr>
            ) : (
              rows.map((row) => {
                prepareRow(row);
                return (
                  <tr {...row.getRowProps()}>
                    {row.cells.map((cell) => (
                      <td {...cell.getCellProps()}>{cell.render("Cell")}</td>
                    ))}
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default JobListTable;
