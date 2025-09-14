// js/audit-log.js (only display change for action_type formatting)
document.addEventListener("DOMContentLoaded", () => {
  const tbody = document.getElementById("audit-log-body");
  const q = document.getElementById("audit-filter-text");
  const from = document.getElementById("audit-filter-from");
  const to = document.getElementById("audit-filter-to");
  const clear = document.getElementById("audit-clear");

  if (!tbody) return;

  let fullData = [];
  let linkedData = [];

  function linkValidate(data) {
    let prev = "";
    return data.map((row) => {
      const ok = (row.prev_hash || "") === prev;
      prev = row.curr_hash || "";
      return { ...row, __link_ok: ok };
    });
  }

  function highlightText(text, needle) {
    if (!needle) return text;
    const regex = new RegExp(`(${needle.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")})`, "gi");
    return text.replace(regex, '<span class="hl">$1</span>');
  }

  function formatActionType(actionType) {
    if (!actionType) return "";
    return actionType
      .toLowerCase()
      .replace(/_/g, " ")
      .replace(/(^\w|\s\w)/g, (m) => m.toUpperCase());
  }

  function textMatch(row, needle) {
    if (!needle) return true;
    const hay = [
      row.action_type, row.user_name, row.user_email, row.user_role,
      row.item_name, row.item_code, row.item_category
    ].filter(Boolean).join(" ").toLowerCase();
    return hay.includes(needle.toLowerCase());
  }

  function render(rows) {
    tbody.innerHTML = "";
    if (!rows.length) {
      tbody.innerHTML = "<tr><td colspan='8'>No records.</td></tr>";
      return;
    }
    const needle = (q && q.value) ? q.value.trim() : "";
    rows.forEach((row, i) => {
      const tr = document.createElement("tr");
      const badge = row.__link_ok
        ? "<span class='valid-badge'>✓ Valid</span>"
        : "<span class='tampered-badge'>✗ Tampered</span>";
      tr.innerHTML = `
        <td>${i + 1}</td>
        <td>${highlightText(row.timestamp || "", needle)}</td>
        <td>${highlightText(formatActionType(row.action_type), needle)}</td>
        <td>${highlightText((row.user_name ? row.user_name + " — " : "") + (row.user_email || ""), needle)}</td>
        <td>${highlightText((row.item_name || "") + (row.item_code ? " (" + row.item_code + ")" : ""), needle)}</td>
        <td>${highlightText(row.borrow_date || "", needle)}</td>
        <td>${highlightText(row.return_date || "", needle)}</td>
        <td>${badge}</td>
      `;
      tbody.appendChild(tr);
    });
  }

  function applyTextFilter() {
    const needle = (q && q.value) ? q.value.trim() : "";
    const filtered = linkedData.filter((r) => textMatch(r, needle));
    render(filtered);
  }

  function buildQuery() {
    const params = new URLSearchParams();
    if (from && from.value) params.set("from", from.value);
    if (to && to.value) params.set("to", to.value);
    const qs = params.toString();
    return qs ? `?${qs}` : "";
  }

  function loadFromBackend() {
    tbody.innerHTML = "<tr><td colspan='8'>Loading…</td></tr>";
    const url = "php/get-audit-log.php" + buildQuery();
    fetch(url)
      .then(res => res.json())
      .then(data => {
        if (!Array.isArray(data)) {
          tbody.innerHTML = "<tr><td colspan='8'>Failed to load audit log.</td></tr>";
          return;
        }
        fullData = data;
        linkedData = linkValidate(fullData);
        applyTextFilter();
      })
      .catch(() => {
        tbody.innerHTML = "<tr><td colspan='8'>Error loading audit log.</td></tr>";
      });
  }

  if (q) q.addEventListener("input", applyTextFilter);
  if (from) from.addEventListener("change", loadFromBackend);
  if (to) to.addEventListener("change", loadFromBackend);

  if (clear) {
    clear.addEventListener("click", () => {
      if (q) q.value = "";
      if (from) from.value = "";
      if (to) to.value = "";
      tbody.innerHTML = "<tr><td colspan='8'>No records.</td></tr>";
    });
  }

  loadFromBackend();
});
