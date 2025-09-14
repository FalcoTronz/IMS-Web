// Show one dashboard section at a time (sidebar links)
function showSection(id) {
  document.querySelectorAll('.dashboard-section').forEach(section => {
    section.classList.remove('active');
  });
  document.getElementById(id).classList.add('active');

  document.querySelectorAll('.sidebar li').forEach(li => {
    li.classList.remove('active');
  });
  const clickedLi = [...document.querySelectorAll('.sidebar li')].find(li =>
    li.getAttribute("onclick")?.includes(id)
  );
  if (clickedLi) clickedLi.classList.add('active');
}

// Delete item from database using custom confirm modal
function deleteRow(button) {
  const row = button.closest("tr");
  const itemId = row.getAttribute("data-id");

  showConfirm("Are you sure you want to delete this item?", () => {
    fetch("php/delete-item.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `id=${encodeURIComponent(itemId)}`
    })
      .then(res => res.text())
      .then(response => {
        if (response.trim() === "success") {
          showToast("Item deleted.");
          const isSearch = row.closest("tbody").id === "search-results";

          if (isSearch) {
            const query = document.getElementById("search-query")?.value.trim();
            if (query) {
              fetch("php/smart-search.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `query=${encodeURIComponent(query)}`
              })
                .then(res => res.json())
                .then(data => updateItemTable(data))
                .catch(err => {
                  console.error("Search refresh failed after delete:", err);
                  showToast("Deleted, but could not refresh table.");
                });
            }
          } else {
            loadItems();
          }
        } else {
          showToast("Failed to delete item.");
        }
      })
      .catch(error => {
        console.error("Delete error:", error);
        showToast("An error occurred.");
      });
  });
}





function editRow(button) {
  const row = button.closest("tr");

  // Prevent re-editing if already in edit mode
  if (row.classList.contains("editing")) return;

  row.classList.add("editing");

  const cells = row.querySelectorAll("td");
  const values = [...cells].slice(1, 8).map(cell => cell.textContent.trim());

  cells[1].innerHTML = `<input type="text" value="${values[0]}">`; // Book Name
  cells[2].innerHTML = `<input type="text" value="${values[1]}">`; // Author(s)
  cells[3].innerHTML = `<input type="text" value="${values[2]}">`; // Category
  cells[4].innerHTML = `<input type="number" value="${values[3]}">`; // Quantity
  cells[5].innerHTML = `<input type="text" value="${values[4]}">`; // ISBN
  cells[6].innerHTML = `<input type="number" value="${values[5]}">`; // Year
  cells[7].innerHTML = `<input type="text" value="${values[6]}">`; // Location

  cells[cells.length - 1].innerHTML = `
    <button class="save-btn" onclick="saveRow(this)">Save</button>
    <button class="cancel-btn" onclick="cancelEdit(this)">Cancel</button>
  `;
}



function saveRow(button) {
  const row = button.closest("tr");
  const itemId = row.getAttribute("data-id");
  const inputs = row.querySelectorAll("input");

  const updatedData = {
    id: itemId,
    name: inputs[0].value.trim(),
    details: inputs[1].value.trim(),
    category: inputs[2].value.trim(),
    quantity: inputs[3].value.trim(),
    item_code: inputs[4].value.trim(),
    year: inputs[5].value.trim(),
    location: inputs[6].value.trim()
  };

  // ✅ Validation
  for (const [key, value] of Object.entries(updatedData)) {
    if (!value && key !== "id") {
      showToast("Please fill in all fields.");
      return;
    }
  }

  const quantity = parseInt(updatedData.quantity);
  const year = parseInt(updatedData.year);

  if (
    updatedData.name.length < 2 || updatedData.name.length > 100 ||
    updatedData.details.length < 2 || updatedData.details.length > 100 ||
    updatedData.category.length < 2 || updatedData.category.length > 50 ||
    isNaN(quantity) || quantity < 1 || quantity > 1000 ||
    !/^[A-Za-z0-9\-]{4,20}$/.test(updatedData.item_code) ||
    isNaN(year) || year < 1000 || year > 2100 ||
    updatedData.location.length < 1 || updatedData.location.length > 50
  ) {
    showToast("Validation error. Please check all fields.");
    return;
  }

  // ✅ Send update to server
  const formData = new URLSearchParams();
  for (const key in updatedData) {
    formData.append(key, updatedData[key]);
  }

  fetch("php/update-item.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: formData.toString()
  })
    .then(res => res.text())
    .then(response => {
      if (response.trim() === "success") {
        showToast("Item updated.");

        const isSearch = row.closest("tbody").id === "search-results";

        if (isSearch) {
          const query = document.getElementById("search-query")?.value.trim();
          if (query) {
            fetch("php/get-items.php")
              .then(res => res.json())
              .then(items => {
                const fuse = new Fuse(items, {
                  keys: ["name", "details", "category", "item_code", "year"],
                  threshold: 0.5,
                  includeMatches: true
                });
                const results = fuse.search(query);
                updateItemTable(results, query); // Rebuilds the table
              });
          }
        } else {
          loadItems(); // Refresh Recently Added section
        }

      } else {
        showToast("Update failed.");
      }
    })
    .catch(err => {
      console.error("Error:", err);
      showToast("An error occurred.");
    });
}














function cancelEdit(button) {
  const row = button.closest("tr");
  row.classList.remove("editing");

  const isSearch = row.closest("tbody").id === "search-results";

  if (isSearch) {
    // Refresh the entire search table
    const query = document.getElementById("search-query")?.value.trim();
    if (query) {
      fetch("php/smart-search.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `query=${encodeURIComponent(query)}`
      })
        .then(res => res.json())
        .then(data => {
          updateItemTable(data); // rebuilds table from search results
        })
        .catch(err => {
          console.error("Search cancel error:", err);
          showToast("Could not cancel properly.");
        });
    }
  } else {
    loadItems();
  }
}





// Add item form handler
document.getElementById("add-item-form").addEventListener("submit", function (e) {
  e.preventDefault();

  const formData = new FormData(e.target);

  fetch("php/add-item.php", {
    method: "POST",
    body: formData
  })
    .then(res => res.text())
    .then(response => {
      if (response === "success") {
        showToast("Item added successfully.");
        e.target.reset();
        loadItems();
      } else {
        showToast("Failed to add item.");
      }
    })
    .catch(error => {
      console.error("Error:", error);
      showToast("An error occurred.");
    });
});

function loadItems() {
  fetch("php/get-items.php")
    .then(res => res.json())
    .then(data => {
      const tbody = document.getElementById("item-body");
      tbody.innerHTML = "";

      data.forEach(item => {
        const row = document.createElement("tr");
        row.setAttribute("data-id", item.id);
// Add this for the Status column in the block below if required: <td>${item.status || ""}</td>
        row.innerHTML = `
          <td>${item.id}</td>
          <td>${item.name}</td>
          <td>${item.details}</td>
          <td>${item.category}</td>
          <td>${item.quantity}</td>
          <td>${item.item_code || ""}</td>
          <td>${item.year || ""}</td>
          <td>${item.location || ""}</td>
          
          <td>${item.last_update || ""}</td>
          <td>
            <button class="edit-btn" onclick="editRow(this)">Edit</button>
            <button class="delete-btn" onclick="deleteRow(this)">Delete</button>
          </td>
        `;
        tbody.appendChild(row);
      });
    });
}

loadItems();

function showToast(message) {
  const toast = document.getElementById("toast");
  toast.textContent = message;
  toast.className = "show";
  setTimeout(() => {
    toast.className = toast.className.replace("show", "");
  }, 3000);
}

function showConfirm(message, onConfirm) {
  const overlay = document.getElementById("confirm-box");
  document.getElementById("confirm-message").textContent = message;
  overlay.style.display = "block";

  document.getElementById("confirm-yes").onclick = () => {
    overlay.style.display = "none";
    onConfirm();
  };
  document.getElementById("confirm-no").onclick = () => {
    overlay.style.display = "none";
  };
}

function showDashboardSection(type) {
  document.querySelectorAll('.dashboard-section').forEach(section => {
    section.classList.remove('active');
  });

  if (type === 'add') {
    document.getElementById('add-section').classList.add('active');
  } else if (type === 'search') {
    document.getElementById('search-section').classList.add('active');
  }

  document.querySelectorAll('.sidebar ul li').forEach(li => {
    li.classList.remove('active');
  });
  const clickedLi = [...document.querySelectorAll('.sidebar ul li')].find(li =>
    li.getAttribute("onclick")?.includes(type)
  );
  if (clickedLi) clickedLi.classList.add('active');
}

// ✅ Fuse.js fuzzy search implementation
document.addEventListener("DOMContentLoaded", function () {
  const searchBox = document.getElementById("search-query");
  const clearBtn = document.getElementById("clear-button");

  if (clearBtn) {
    clearBtn.addEventListener("click", function () {
      if (searchBox) searchBox.value = "";
      const tbody = document.getElementById("search-results");
      if (tbody) {
        tbody.innerHTML = "<tr><td colspan='11'>Please enter a search term.</td></tr>";
      }
    });
  }

  if (searchBox) {
    searchBox.addEventListener("input", function () {
      const query = searchBox.value.trim();
      const lowerQuery = query.toLowerCase();
      const isNumeric = /^\d+$/.test(query);

      if (!query) {
        document.getElementById("search-results").innerHTML = "<tr><td colspan='11'>Please enter a search term.</td></tr>";
        return;
      }

      fetch("php/get-items.php")
        .then(res => res.json())
        .then(items => {
          let results = [];

          if (isNumeric) {
            results = items.map(item => {
              const matches = [];

              if (item.item_code?.startsWith(query)) {
                matches.push({ key: "item_code", value: item.item_code, indices: [[0, query.length - 1]] });
              }
              if (item.year?.startsWith(query)) {
                matches.push({ key: "year", value: item.year, indices: [[0, query.length - 1]] });
              }

              return matches.length > 0 ? { item, matches } : null;
            }).filter(Boolean);
          } else if (["available", "unavailable"].includes(lowerQuery)) {
            results = items.map(item => {
              if (item.status?.toLowerCase() === lowerQuery) {
                return {
                  item,
                  matches: [{ key: "status", value: item.status, indices: [[0, query.length - 1]] }]
                };
              }
              return null;
            }).filter(Boolean);
          } else {
            const fuse = new Fuse(items, {
              keys: ["name", "details", "category", "item_code", "year"],
              threshold: 0.5,
              includeScore: true,
              includeMatches: true
            });
            results = fuse.search(query);
          }

          updateItemTable(results, query);
        })
        .catch(err => {
          console.error("Fuzzy search error:", err);
          showToast("Search failed.");
        });
    });
  }
});






function updateItemTable(results, query = "") {
  const tbody = document.getElementById("search-results");
  if (!tbody) return;

  tbody.innerHTML = "";

  if (results.length === 0) {
    tbody.innerHTML = "<tr><td colspan='10'>No items found.</td></tr>";
    return;
  }

  const highlightFuseMatch = (text, match) => {
    if (!match || !match.indices || match.indices.length === 0) return text;
    let highlighted = "", lastIndex = 0;
    match.indices.sort((a, b) => a[0] - b[0]);
    match.indices.forEach(([start, end]) => {
      highlighted += text.slice(lastIndex, start);
      highlighted += `<span class="highlight">${text.slice(start, end + 1)}</span>`;
      lastIndex = end + 1;
    });
    highlighted += text.slice(lastIndex);
    return highlighted;
  };

  results.forEach(result => {
    const item = result.item || result;
    const matches = result.matches || [];
    const getMatch = (key) => matches.find(m => m.key === key);

    const row = document.createElement("tr");
    row.setAttribute("data-id", item.id);

    row.innerHTML = `
      <td>${item.id}</td>
      <td>${highlightFuseMatch(item.name, getMatch("name"))}</td>
      <td>${highlightFuseMatch(item.details, getMatch("details"))}</td>
      <td>${highlightFuseMatch(item.category, getMatch("category"))}</td>
      <td>${item.quantity}</td>
      <td>${highlightFuseMatch(item.item_code, getMatch("item_code"))}</td>
      <td>${highlightFuseMatch(item.year, getMatch("year"))}</td>
      <td>${item.location}</td>
      <td>${item.last_update || "-"}</td>
      <td>
        <button class="edit-btn" onclick="editRow(this)">Edit</button>
        <button class="delete-btn" onclick="deleteRow(this)">Delete</button>
      </td>
    `;

    tbody.appendChild(row);
  });
}



let allUsers = [];

// Load only pending users on page load
async function loadUsers() {
  try {
    const res = await fetch("php/get-users.php");
    const data = await res.json();
    allUsers = data;
    const pendingOnly = data.filter(user => user.status === "pending");
    renderUserTable(pendingOnly);
  } catch (err) {
    console.error("Failed to load users:", err);
  }
}


function renderUserTable(users, matchMap = new Map()) {
  const tbody = document.getElementById("member-table-body");
  tbody.innerHTML = "";

  users.forEach(user => {
    const tr = document.createElement("tr");

    // Color coding
    if (user.status === "pending" || user.status === "suspended") {
      tr.style.backgroundColor = "#ffe5e5";
    } else if (user.role === "staff") {
      tr.style.backgroundColor = "#e2f7e2";
    }

    const actionText =
      user.status === "pending" ? "Approve" :
      user.status === "suspended" ? "Unsuspend" : "Suspend";

    tr.innerHTML = `
      <td>${highlightMatch(user.id, "id", user.id)}</td>
      <td>${highlightMatch(user.id, "full_name", user.full_name)}</td>
      <td>${highlightMatch(user.id, "email", user.email)}</td>
      <td>${highlightMatch(user.id, "address", user.address)}</td>
      <td>${highlightMatch(user.id, "phone", user.phone)}</td>

      <td>
        <select class="role-select" data-id="${user.id}">
          <option value="member" ${user.role === "member" ? "selected" : ""}>Member</option>
          <option value="staff" ${user.role === "staff" ? "selected" : ""}>Staff</option>
        </select>
        <button class="apply-role-btn" data-id="${user.id}">Apply</button>
      </td>

      <td>${new Date(user.created_at).toLocaleDateString()}</td>

      <td class="status-cell">${user.status}</td>
      <td>
        <button class="status-btn" data-id="${user.id}" data-status="${user.status}">
          ${actionText}
        </button>
      </td>
    `;

    tbody.appendChild(tr);
  });

  setupStatusButtons();
  setupApplyRoleButtons();
}








function setupStatusButtons() {
  document.querySelectorAll(".status-btn").forEach(button => {
    button.addEventListener("click", async () => {
      const userId = button.getAttribute("data-id");
      const currentStatus = button.getAttribute("data-status");
      const dropdown = document.querySelector(`.role-select[data-id="${userId}"]`);
      const selectedRole = dropdown?.value || "member";

      const res = await fetch("php/toggle-status.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `id=${encodeURIComponent(userId)}&status=${encodeURIComponent(currentStatus)}&role=${encodeURIComponent(selectedRole)}`
      });

      const result = await res.json();

      if (result.success) {
        // ✅ Update user in memory
        const user = allUsers.find(u => u.id == userId);
        if (user) {
          user.status = (currentStatus === "pending" || currentStatus === "suspended") ? "approved" :
                        currentStatus === "approved" ? "suspended" : user.status;
          user.role = selectedRole;
        }

        // ✅ Keep showing only pending users + this user
        const visibleUsers = allUsers.filter(u => u.status === "pending" || u.id == userId);
        renderUserTable(visibleUsers);
      } else {
        alert("❌ Failed to update user status.");
      }
    });
  });
}


function setupApplyRoleButtons() {
  document.querySelectorAll(".apply-role-btn").forEach(button => {
    button.addEventListener("click", async () => {
      const userId = button.getAttribute("data-id");
      const dropdown = document.querySelector(`.role-select[data-id="${userId}"]`);
      const selectedRole = dropdown?.value || "member";

      button.disabled = true;
      try {
        const res = await fetch("php/update-role.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `id=${encodeURIComponent(userId)}&role=${encodeURIComponent(selectedRole)}`
        });
        const result = await res.json();

        if (!result.success) throw new Error();

        // reflect change locally (do NOT change status here)
        const user = allUsers.find(u => u.id == userId);
        if (user) user.role = selectedRole;

        // re-render (your current pattern shows pending + this user)
        const visibleUsers = allUsers.filter(u => u.status === "pending" || u.id == userId);
        renderUserTable(visibleUsers);
      } catch {
        alert("❌ Failed to apply role change.");
      } finally {
        button.disabled = false;
      }
    });
  });
}



// Fuse.js search
let fuse;
document.getElementById("member-search").addEventListener("input", function () {
  const searchVal = this.value.trim();
  if (searchVal === "") {
    renderUserTable(allUsers);
    return;
  }


  if (!fuse) {
  // Convert id to string for better search match
  allUsers.forEach(user => user.id = user.id.toString());

fuse = new Fuse(allUsers, {
  keys: ["id", "full_name", "email", "phone", "address"],
  threshold: 0.5,
  includeMatches: true
});

}



const fuseResult = fuse.search(searchVal);
const matchedUsers = fuseResult.map(r => r.item);
const matchMap = new Map(fuseResult.map(r => [r.item.id, r.matches]));
renderUserTable(matchedUsers, matchMap); // pass matches to the table

});

// Clear button
document.getElementById("member-clear-button").addEventListener("click", () => {
  document.getElementById("member-search").value = "";
  const tbody = document.getElementById("member-table-body");
  tbody.innerHTML = "";
});

document.addEventListener("DOMContentLoaded", loadUsers);


function highlightMatch(userId, key, original) {
  const match = fuse && fuse.search(document.getElementById("member-search").value.trim()).find(r => r.item.id === userId);
  const matchData = match?.matches?.find(m => m.key === key);
  
  if (!matchData || !matchData.indices) return original;

  let result = "";
  let lastIndex = 0;

  matchData.indices.forEach(([start, end]) => {
    result += original.slice(lastIndex, start);
    result += `<span style="background-color: orange;">${original.slice(start, end + 1)}</span>`;
    lastIndex = end + 1;
  });

  result += original.slice(lastIndex);
  return result;
}


// === Add Items: show only the most recently added item (double-submit guard + UK date) ===
function toUKDate(v) {
  if (!v) return '';
  // already dd/mm/yyyy?
  if (/^\d{1,2}\/\d{1,2}\/\d{2,4}$/.test(v)) return v;
  const d = new Date(v);
  return isNaN(d)
    ? v
    : d.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('add-item-form');
  if (!form) return;

  let adding = false;

  // Capture phase so we beat any other submit handlers
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    if (adding) return;
    adding = true;

    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn?.setAttribute('disabled', 'disabled');

    try {
      const res = await fetch(form.action || 'php/add-item.php', {
        method: 'POST',
        body: new FormData(form)
      });

      const text = await res.text();
      let data;
      try { data = JSON.parse(text); } catch { data = { success: false, error: text || 'Invalid server response' }; }

      if (data.success && data.item) {
        renderLatestAddedItem(data.item);
        form.reset();
        alert('Item added.');
      } else {
        alert(data.error || 'Add failed.');
      }
    } catch {
      alert('Network error. Please try again.');
    } finally {
      adding = false;
      submitBtn?.removeAttribute('disabled');
    }
  }, true);
});

function renderLatestAddedItem(item) {
  const tbody = document.getElementById('add-latest-tbody');
  if (!tbody) return;

  tbody.innerHTML = ''; // only the latest row

  const tr = document.createElement('tr');
  const td = (v) => { const t = document.createElement('td'); t.textContent = v ?? ''; return t; };

  // Match the Add Items table (no Actions column)
  tr.append(
    td(item.id),
    td(item.name),
    td(item.details),
    td(item.category),
    td(item.quantity),
    td(item.item_code),
    td(item.year),
    td(item.location),
    td(toUKDate(item.last_update || item.updated_at || item.created_at || ''))
  );

  tbody.appendChild(tr);
}






