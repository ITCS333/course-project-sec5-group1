/*
  admin.js — interactive "Manage Weekly Breakdown" page.

  HTML entry point: src/weekly/admin.html
    <script src="admin.js" defer></script>

  Required ids on the page:
    #week-form         — the form
    #week-title        — <input type="text">
    #week-start-date   — <input type="date">
    #week-description  — <textarea>
    #week-links        — <textarea> (one URL per line)
    #add-week          — submit <button>
    #weeks-tbody       — table body

  API base URL: ./api/index.php
    GET    → list all weeks   { success: true, data: [ ...weeks ] }
    POST   → create a week    body: { title, start_date, description, links }
    PUT    → update a week    body: { id, ...fields }
    DELETE ?id=<id> → delete a week

  Week object shape:
    { id, title, start_date, description, links: string[] }
*/

// --- Global Data Store ---
let weeks = [];

// --- Element Selections ---
const weekForm   = document.getElementById("week-form");
const weeksTbody = document.getElementById("weeks-tbody");

// --- Functions ---

/**
 * Build one <tr> element for a single week.
 * Columns: title | start_date | description | Actions (Edit, Delete)
 */
function createWeekRow(week) {
  const tr = document.createElement("tr");

  const tdTitle = document.createElement("td");
  tdTitle.textContent = week.title;

  const tdDate = document.createElement("td");
  tdDate.textContent = week.start_date;

  const tdDesc = document.createElement("td");
  tdDesc.textContent = week.description;

  const tdActions = document.createElement("td");

  const editBtn = document.createElement("button");
  editBtn.className     = "edit-btn";
  editBtn.dataset.id    = String(week.id);
  editBtn.textContent   = "Edit";

  const deleteBtn = document.createElement("button");
  deleteBtn.className   = "delete-btn";
  deleteBtn.dataset.id  = String(week.id);
  deleteBtn.textContent = "Delete";

  tdActions.appendChild(editBtn);
  tdActions.appendChild(document.createTextNode(" "));
  tdActions.appendChild(deleteBtn);

  tr.appendChild(tdTitle);
  tr.appendChild(tdDate);
  tr.appendChild(tdDesc);
  tr.appendChild(tdActions);

  return tr;
}

/**
 * Re-render the whole table body from the global `weeks` array.
 */
function renderTable() {
  weeksTbody.innerHTML = "";
  weeks.forEach(week => {
    weeksTbody.appendChild(createWeekRow(week));
  });
}

/**
 * Form submit handler — either creates a new week or updates one in edit mode.
 */
async function handleAddWeek(event) {
  event.preventDefault();

  const title       = document.getElementById("week-title").value.trim();
  const startDate   = document.getElementById("week-start-date").value.trim();
  const description = document.getElementById("week-description").value.trim();
  const linksRaw    = document.getElementById("week-links").value;

  const links = linksRaw
    .split("\n")
    .map(s => s.trim())
    .filter(s => s.length > 0);

  const fields = {
    title:       title,
    start_date:  startDate,
    description: description,
    links:       links,
  };

  const addBtn = document.getElementById("add-week");
  const editId = addBtn ? addBtn.dataset.editId : null;

  // Edit mode
  if (editId) {
    await handleUpdateWeek(editId, fields);
    return;
  }

  // Create mode
  try {
    const response = await fetch("./api/index.php", {
      method:  "POST",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify(fields),
    });

    const result = await response.json();

    if (result && result.success) {
      weeks.push({
        id:          result.id,
        title:       title,
        start_date:  startDate,
        description: description,
        links:       links,
      });
      renderTable();
      weekForm.reset();
    }
  } catch (err) {
    console.error("Failed to create week:", err);
  }
}

/**
 * Send PUT ./api/index.php with { id, ...fields } and update local state.
 */
async function handleUpdateWeek(id, fields) {
  try {
    const response = await fetch("./api/index.php", {
      method:  "PUT",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify({ id: id, ...fields }),
    });

    const result = await response.json();

    if (result && result.success) {
      const idx = weeks.findIndex(w => String(w.id) === String(id));
      if (idx !== -1) {
        weeks[idx] = {
          id:          weeks[idx].id,
          title:       fields.title,
          start_date:  fields.start_date,
          description: fields.description,
          links:       fields.links,
        };
      }
      renderTable();
      weekForm.reset();

      const addBtn = document.getElementById("add-week");
      if (addBtn) {
        addBtn.textContent = "Add Week";
        delete addBtn.dataset.editId;
      }
    }
  } catch (err) {
    console.error("Failed to update week:", err);
  }
}

/**
 * Delegated click handler on the tbody — handles Edit and Delete buttons.
 */
async function handleTableClick(event) {
  const target = event.target;

  // DELETE
  if (target && target.classList && target.classList.contains("delete-btn")) {
    const id = target.dataset.id;

    try {
      const response = await fetch(
        "./api/index.php?id=" + encodeURIComponent(id),
        { method: "DELETE" }
      );
      const result = await response.json();

      if (result && result.success) {
        weeks = weeks.filter(w => String(w.id) !== String(id));
        renderTable();
      }
    } catch (err) {
      console.error("Failed to delete week:", err);
    }
    return;
  }

  // EDIT
  if (target && target.classList && target.classList.contains("edit-btn")) {
    const id   = target.dataset.id;
    const week = weeks.find(w => String(w.id) === String(id));
    if (!week) return;

    document.getElementById("week-title").value       = week.title || "";
    document.getElementById("week-start-date").value  = week.start_date || "";
    document.getElementById("week-description").value = week.description || "";
    document.getElementById("week-links").value       =
      Array.isArray(week.links) ? week.links.join("\n") : "";

    const addBtn = document.getElementById("add-week");
    if (addBtn) {
      addBtn.textContent    = "Update Week";
      addBtn.dataset.editId = String(id);
    }
  }
}

/**
 * Initial load — fetches weeks, renders the table, wires up event listeners.
 */
async function loadAndInitialize() {
  try {
    const response = await fetch("./api/index.php");
    const result   = await response.json();

    weeks = (result && Array.isArray(result.data)) ? result.data : [];
    renderTable();
  } catch (err) {
    console.error("Failed to load weeks:", err);
    weeks = [];
    renderTable();
  }

  weekForm.addEventListener("submit", handleAddWeek);
  weeksTbody.addEventListener("click", handleTableClick);
}

// --- Initial Page Load ---
loadAndInitialize();
