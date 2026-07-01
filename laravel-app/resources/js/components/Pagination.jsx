import "../../css/components/tables.css";

/**
 * Prev/Next pager. Renders nothing when there's only one page.
 */
export default function Pagination({ page, pageCount, onPageChange }) {
  if (pageCount <= 1) return null;

  return (
    <div className="ranking-pagination">
      <button
        type="button"
        className="ranking-pagination__btn"
        onClick={() => onPageChange(page - 1)}
        disabled={page === 0}
      >
        ‹ Prev
      </button>

      <span className="ranking-pagination__status">
        Page {page + 1} of {pageCount}
      </span>

      <button
        type="button"
        className="ranking-pagination__btn"
        onClick={() => onPageChange(page + 1)}
        disabled={page === pageCount - 1}
      >
        Next ›
      </button>
    </div>
  );
}