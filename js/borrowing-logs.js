document.addEventListener("DOMContentLoaded", () => {
  const searchInput = document.getElementById("borrow-search-query");
  const clearButton = document.getElementById("borrow-clear-button");
  const tableBody = document.getElementById("staff-borrowing-logs-body");

  if (!searchInput || !clearButton || !tableBody) return;

  let fullData = [];
  let fuse;

  function renderTable(data, matchResults = []) {
    tableBody.innerHTML = "";

    if (!data || data.length === 0) {
      tableBody.innerHTML = "<tr><td colspan='10'>No matching records found.</td></tr>";
      return;
    }

    const matchMap = new Map(matchResults.map(r => [r.item.id, r.matches]));

// ✅ Date formatter (must be before data.forEach)
function formatDate(dateStr) {
  if (!dateStr) return "-";
  const date = new Date(dateStr);
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  return `${day}/${month}/${year}`;
}

// ✅ Borrowing logs table render
data.forEach(entry => {
  const tr = document.createElement("tr");
  const safe = val => val || "-";
  const matches = matchMap.get(entry.id) || [];

  let actionBtn = "";
  if (entry.status === "pending") {
    actionBtn = `<button class="action-btn" data-id="${entry.id}" data-status="pending">Approve</button>`;
  } else if (entry.status === "borrowed") {
    actionBtn = `<button class="action-btn" data-id="${entry.id}" data-status="borrowed">Returned</button>`;
  } else {
    actionBtn = `<button disabled>Returned</button>`;
  }

  // ✅ Format status with rules
  let statusLabel = "";
  let statusColor = "inherit";

  const now = new Date();
  const dueDate = entry.due_date ? new Date(entry.due_date) : null;
  const returnDate = entry.return_date ? new Date(entry.return_date) : null;
if (entry.status === "borrowed" && dueDate && dueDate < now) {
  tr.style.backgroundColor = "#ffe5e5"; // light red for overdue
}

  if (entry.status === "returned") {
    if (returnDate && dueDate && returnDate > dueDate) {
      statusLabel = "Returned";
      statusColor = "red";
    } else {
      statusLabel = "Returned";
      statusColor = "green";
    }
  } else if (entry.status === "borrowed") {
    if (dueDate && dueDate < now) {
      statusLabel = "Overdue";
      statusColor = "red";
    } else {
      statusLabel = "Borrowed";
      statusColor = "inherit";
    }
  } else if (entry.status === "pending") {
    statusLabel = "Pending";
    statusColor = "inherit";
  }

  tr.innerHTML = `
    <td>${highlight(entry.member_id, "member_id", matches)}</td>
    <td>${highlight(entry.member_name, "member_name", matches)}</td>
    <td>${highlight(entry.book_title, "book_title", matches)}</td>
    <td>${highlight(entry.author, "author", matches)}</td>
    <td>${highlight(entry.isbn, "isbn", matches)}</td>
    <td>${formatDate(entry.approval_date)}</td>
    <td>${formatDate(entry.due_date)}</td>
    <td>${formatDate(entry.return_date)}</td>
    <td class="status-cell" style="color: ${statusColor}">${statusLabel}</td>
    <td>${actionBtn}</td>
  `;

  tableBody.appendChild(tr);
});



    attachActionListeners();
  }

  function highlight(text, key, matches) {
    const match = matches.find(m => m.key === key);
    if (!match || !match.indices) return text;

    let result = "";
    let lastIndex = 0;

    match.indices.forEach(([start, end]) => {
      result += text.slice(lastIndex, start);
      result += `<span style="background-color: orange;">${text.slice(start, end + 1)}</span>`;
      lastIndex = end + 1;
    });

    result += text.slice(lastIndex);
    return result;
  }

  function attachActionListeners() {
    document.querySelectorAll(".action-btn").forEach(button => {
      button.addEventListener("click", async () => {
        const row = button.closest("tr");
        const id = button.getAttribute("data-id");
        const currentStatus = button.getAttribute("data-status");

        const res = await fetch("php/approve-borrowing.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `id=${encodeURIComponent(id)}`
        });

        const result = await res.json();

        if (result.success) {
          const today = new Date();
          const todayStr = today.toLocaleDateString("en-GB");
          const record = fullData.find(item => item.id === id);

          if (record) {
            if (currentStatus === "pending") {
              const dueDate = new Date();
              dueDate.setDate(today.getDate() + 14);

              record.status = "borrowed";
              record.approval_date = todayStr;
              record.due_date = dueDate.toLocaleDateString("en-GB");

              row.querySelector("td:nth-child(6)").textContent = todayStr;
              row.querySelector("td:nth-child(7)").textContent = record.due_date;
              row.querySelector(".status-cell").textContent = "Borrowed";
row.querySelector(".status-cell").style.color = "inherit";

              button.textContent = "Returned";
              button.setAttribute("data-status", "borrowed");

            } else if (currentStatus === "borrowed") {
              record.status = "returned";
              record.return_date = todayStr;

              row.querySelector("td:nth-child(8)").textContent = todayStr;
              // Check if it's late
const dueDateObj = new Date(record.due_date.split("/").reverse().join("-"));
const isLate = today > dueDateObj;

row.querySelector(".status-cell").textContent = "Returned";
row.querySelector(".status-cell").style.color = isLate ? "red" : "green";

              button.textContent = "Approve";
              button.disabled = true;
            }
          }

          showToast("Borrowing updated.");
        } else {
          alert("❌ Action failed: " + (result.error || "Unknown error"));
        }
      });
    });
  }

  fetch("php/get-borrowings.php")
    .then(res => res.json())
    .then(data => {
      if (!Array.isArray(data)) {
        tableBody.innerHTML = "<tr><td colspan='10'>Failed to load data</td></tr>";
        return;
      }

      fullData = data;

      fuse = new Fuse(fullData, {
        keys: ["member_name", "member_id", "book_title", "author", "isbn"],
        threshold: 0.5,
        includeMatches: true
      });

      renderTable(fullData); // initial
    })
    .catch(err => {
      console.error("Error fetching borrowing logs:", err);
      tableBody.innerHTML = "<tr><td colspan='10'>Error loading data</td></tr>";
    });

  searchInput.addEventListener("input", () => {
    const keyword = searchInput.value.trim();

    if (keyword === "") {
      renderTable(fullData);
    } else {
      const results = fuse.search(keyword);
      const filtered = results.map(r => r.item);
      renderTable(filtered, results);
    }
  });

  clearButton.addEventListener("click", () => {
    searchInput.value = "";
    tableBody.innerHTML = "<tr><td colspan='10'>No records to display.</td></tr>";
    searchInput.focus();
  });

  function showToast(message) {
    const toast = document.getElementById("toast");
    if (!toast) return;
    toast.textContent = message;
    toast.className = "show";
    setTimeout(() => {
      toast.className = toast.className.replace("show", "");
    }, 3000);
  }
});
