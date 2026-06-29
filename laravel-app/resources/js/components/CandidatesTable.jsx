import "../../css/components/tables.css";
import * as React from "react";
import { useTable } from "react-table";
import { StatusSelect, normalizeStatusValue } from "./CandidateStatus";

function CandidatesTable() {
  const csrf = window.__LARAVEL__?.csrf;
  const [data, setData] = React.useState([]);
  const [loading, setLoading] = React.useState(true);
  const [updatingId, setUpdatingId] = React.useState(null);

  const fetchCandidates = React.useCallback(() => {
    return fetch("/api/candidates", {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "include",
    })
      .then((res) => res.json())
      .then((json) => setData(json));
  }, []);

  React.useEffect(() => {
    fetchCandidates()
      .catch((err) => console.error("Failed to fetch candidates:", err))
      .finally(() => setLoading(false));
  }, [fetchCandidates]);

  const handleStatusChange = React.useCallback(
    async (candidateId, newStatus) => {
      setUpdatingId(candidateId);
      setData((current) =>
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
        setData((current) =>
          current.map((row) => (row.id === candidateId ? { ...row, ...updated } : row))
        );
      } catch (err) {
        console.error("Failed to update candidate status:", err);
        fetchCandidates().catch(() => {});
      } finally {
        setUpdatingId(null);
      }
    },
    [csrf, fetchCandidates]
  );

  const columns = React.useMemo(
    () => [
      { Header: "Name", accessor: "Name" },
      { Header: "Contact", accessor: "Contact" },
      { Header: "Job Position", accessor: "job_position" },
      {
        Header: "Status",
        accessor: "status",
        Cell: ({ row }) => (
          <StatusSelect
            value={normalizeStatusValue(row.original.status)}
            onChange={(newStatus) => handleStatusChange(row.original.id, newStatus)}
            disabled={updatingId === row.original.id}
          />
        ),
      },
      {
        Header: "Details",
        accessor: "details",
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
    [handleStatusChange, updatingId]
  );

  const { getTableProps, getTableBodyProps, headerGroups, rows, prepareRow } =
    useTable({ columns, data });

  return (
    <div className="candidates-page">
      <div className="themed-table-wrapper">
        <table {...getTableProps()}>
          <thead>
            {headerGroups.map((headerGroup) => {
              const { key, ...headerGroupProps } = headerGroup.getHeaderGroupProps();
              return (
                <tr key={key} {...headerGroupProps}>
                  {headerGroup.headers.map((column) => {
                    const { key: colKey, ...columnProps } = column.getHeaderProps();
                    return (
                      <th key={colKey} {...columnProps}>
                        {column.render("Header")}
                      </th>
                    );
                  })}
                </tr>
              );
            })}
          </thead>
          <tbody {...getTableBodyProps()}>
            {loading ? (
              Array.from({ length: 5 }).map((_, i) => (
                <tr className="table-row-rec table-row-skeleton" key={`skeleton-${i}`}>
                  <td><span className="skeleton-cell skeleton-title" /></td>
                  <td><span className="skeleton-cell skeleton-desc" /></td>
                  <td style={{ textAlign: "center" }}><span className="skeleton-cell skeleton-count" /></td>
                  <td className="action-btns">
                    <span className="skeleton-cell skeleton-btn" />
                    <span className="skeleton-cell skeleton-btn" />
                  </td>
                </tr>
              ))
            ) : rows.length === 0 ? (
              <tr>
                <td colSpan={5} style={{ textAlign: "center", padding: "20px" }}>
                  No candidates yet.
                </td>
              </tr>
            ) : (
              rows.map((row) => {
                prepareRow(row);
                return (
                  <tr {...row.getRowProps()}>
                    {row.cells.map((cell) => (
                      <td className="candidates-row" {...cell.getCellProps()}>
                        {cell.render("Cell")}
                      </td>
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

export default CandidatesTable;
