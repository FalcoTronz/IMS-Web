// js/reports.js

// Helper: fetch JSON safely
async function fetchJSON(url) {
  const res = await fetch(url, { cache: "no-store" });
  try { return await res.json(); } catch { return null; }
}

// 1) Top Borrowed (existing)
async function loadTopBooksChart() {
  try {
    const data = await fetchJSON("php/top-books-proxy.php");
    if (!Array.isArray(data)) return;
    const labels = data.map(d => d.name);
    const counts = data.map(d => Number(d.borrow_count || d.count || 0));

    const ctx = document.getElementById("topBooksChart");
    if (!ctx || typeof Chart === "undefined") return;

    new Chart(ctx, {
      type: "bar",
      data: { labels, datasets: [{ label: "Top Borrowed", data: counts }] },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  } catch (e) {
    console.error("Top books error:", e);
  }
}

// 2) Borrowings trend (last 30 days)
async function loadBorrowingsTrend() {
  try {
    const data = await fetchJSON("php/borrowings-trend-proxy.php?days=30");
    if (!Array.isArray(data)) return;

    const labels = data.map(d => d.day); // already yyyy-mm-dd from API
    const counts = data.map(d => Number(d.count || 0));

    const ctx = document.getElementById("borrowingsTrendChart");
    if (!ctx || typeof Chart === "undefined") return;

    new Chart(ctx, {
      type: "line",
      data: { labels, datasets: [{ label: "Borrowings", data: counts, tension: 0.3, fill: false }] },
      options: { responsive: true, plugins: { legend: { display: false } } }
    });
  } catch (e) {
    console.error("Trend error:", e);
  }
}

// 3) Top Categories (doughnut)
async function loadTopCategories() {
  try {
    const data = await fetchJSON("php/top-categories-proxy.php");
    if (!Array.isArray(data)) return;

    const labels = data.map(d => d.category);
    const counts = data.map(d => Number(d.count || 0));

    const ctx = document.getElementById("topCategoriesChart");
    if (!ctx || typeof Chart === "undefined") return;

    new Chart(ctx, {
      type: "doughnut",
      data: { labels, datasets: [{ data: counts }] },
      options: { responsive: true, plugins: { legend: { position: "bottom" } } }
    });
  } catch (e) {
    console.error("Categories error:", e);
  }
}

// 4) KPI cards
async function loadKpis() {
  try {
    const d = await fetchJSON("php/overdue-stats-proxy.php");
    if (!d || typeof d !== "object") return;

    const $ = id => document.getElementById(id);
    if ($("kpi-overdue"))  $("kpi-overdue").textContent  = d.overdue_now ?? "0";
    if ($("kpi-borrowed")) $("kpi-borrowed").textContent = d.borrowed_now ?? "0";
    if ($("kpi-returned")) $("kpi-returned").textContent = d.returned_this_month ?? "0";
  } catch (e) {
    console.error("KPIs error:", e);
  }
}

document.addEventListener("DOMContentLoaded", () => {
  loadTopBooksChart();
  loadBorrowingsTrend();
  loadTopCategories();
  loadKpis();
});
