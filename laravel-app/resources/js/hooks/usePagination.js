import { useEffect, useMemo, useState } from "react";

/**
 * Paginates a list client-side into fixed-size pages.
 *
 * @param {Array} items     full list to paginate
 * @param {number} pageSize items per page (default 10)
 * @returns {{
 *   page: number,
 *   pageCount: number,
 *   pageItems: Array,
 *   setPage: Function,
 *   offset: number,
 * }}
 */
export default function usePagination(items, pageSize = 10) {
  const [page, setPage] = useState(0);

  const pageCount = Math.max(1, Math.ceil(items.length / pageSize));
  const offset = page * pageSize;

  // Stay in range if the list shrinks (e.g. filtering) while on a later page.
  useEffect(() => {
    if (page > pageCount - 1) setPage(pageCount - 1);
  }, [pageCount, page]);

  const pageItems = useMemo(
    () => items.slice(offset, offset + pageSize),
    [items, offset, pageSize]
  );

  return { page, pageCount, pageItems, setPage, offset };
}