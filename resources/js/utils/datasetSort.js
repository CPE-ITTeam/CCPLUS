// resources/js/utils/datasetSort.js

/**
 * Normalizes a dataset's `sortby` config into Vuetify 3's `[{ key, order }]` format.
 * Supports:
 *   - string: "name" -> [{ key: "name", order: "asc" }]
 *   - array of strings: ["type", "group_string", "name"] -> [{ key: "type", order: "asc" }, ...]
 *   - array of descriptors: [{ key: "created", order: "desc" }]
 */
export function normalizeSortBy(sortby) {
  if (!sortby) return [];
  const entries = Array.isArray(sortby) ? sortby : [sortby];
  return entries
    .map((entry) => {
      if (typeof entry === 'string') {
        return { key: entry, order: 'asc' };
      }
      if (entry && typeof entry === 'object' && entry.key) {
        return { key: entry.key, order: entry.order === 'desc' ? 'desc' : 'asc' };
      }
      return null;
    })
    .filter(Boolean);
}

/**
 * Sorts an array of dataset records sequentially using the normalized `sortby` rules.
 * Empty/null values sort last, strings use natural case-insensitive collation, and numbers compare numerically.
 */
export function sortDatasetItems(items, sortby) {
  const rules = normalizeSortBy(sortby);
  if (!Array.isArray(items) || items.length <= 1 || rules.length === 0) {
    return Array.isArray(items) ? [...items] : [];
  }

  return [...items].sort((a, b) => {
    for (const { key, order } of rules) {
      const dir = order === 'desc' ? -1 : 1;
      const aVal = a?.[key];
      const bVal = b?.[key];

      const aEmpty = aVal === undefined || aVal === null || aVal === '';
      const bEmpty = bVal === undefined || bVal === null || bVal === '';
      if (aEmpty && bEmpty) continue;
      if (aEmpty) return 1;
      if (bEmpty) return -1;

      if (typeof aVal === 'number' && typeof bVal === 'number') {
        if (aVal !== bVal) return (aVal - bVal) * dir;
        continue;
      }

      const cmp = String(aVal).localeCompare(String(bVal), undefined, {
        numeric: true,
        sensitivity: 'base',
      });
      if (cmp !== 0) return cmp * dir;
    }
    return 0;
  });
}
