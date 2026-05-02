let users = [];
const userTableBody = document.getElementById("user-table-body");
const addUserForm = document.getElementById("add-user-form");
const changePasswordForm = document.getElementById("password-form");
const searchInput = document.getElementById("search-input");
const tableHeaders = document.querySelectorAll("#user-table thead th");
function createUserRow(user) {
 const row = document.createElement("tr");
 const nameCell = document.createElement("td");
 nameCell.textContent = user.name;
 const emailCell = document.createElement("td");
 emailCell.textContent = user.email;
 const adminCell = document.createElement("td");
 adminCell.textContent = Number(user.is_admin) === 1 ? "Yes" : "No";
 const actionsCell = document.createElement("td");
 const editButton = document.createElement("button");
 editButton.textContent = "Edit";
 editButton.className = "edit-btn";
editButton.dataset.id = user.id;
 editButton.type = "button";
 const deleteButton = document.createElement("button");
 deleteButton.textContent = "Delete";
 deleteButton.className = "delete-btn";
deleteButton.dataset.id = user.id;
 deleteButton.type = "button";
 actionsCell.appendChild(editButton);
 actionsCell.appendChild(deleteButton);
 row.appendChild(nameCell);
 row.appendChild(emailCell);
 row.appendChild(adminCell);
 row.appendChild(actionsCell);
 return row;
}
function renderTable(userArray) {
 userTableBody.innerHTML = "";
 userArray.forEach((user) => {
   const row = createUserRow(user);
   userTableBody.appendChild(row);
 });
}
async function handleChangePassword(event) {
 event.preventDefault();
 const currentPassword = document.getElementById("current-password").value.trim();
 const newPassword = document.getElementById("new-password").value.trim();
 const confirmPassword = document.getElementById("confirm-password").value.trim();
 if (newPassword !== confirmPassword) {
   alert("Passwords do not match.");
   return;
 }
 if (newPassword.length < 8) {
   alert("Password must be at least 8 characters.");
   return;
 }
 try {
   const response = await fetch("../api/index.php?action=change_password", {
     method: "POST",
     headers: {
       "Content-Type": "application/json"
     },
     body: JSON.stringify({
       id: 1,
       current_password: currentPassword,
       new_password: newPassword
     })
   });
   const result = await response.json();
   if (response.ok && result.success) {
     alert("Password updated successfully!");
     document.getElementById("current-password").value = "";
     document.getElementById("new-password").value = "";
     document.getElementById("confirm-password").value = "";
   } else {
     alert(result.message);
   }
 } catch (error) {
   console.error(error);
   alert("An error occurred while updating the password.");
 }
}
async function handleAddUser(event) {
 event.preventDefault();
 const name = document.getElementById("user-name").value.trim();
 const email = document.getElementById("user-email").value.trim();
 const password = document.getElementById("default-password").value.trim();
 const is_admin = document.getElementById("is-admin").value;
 if (!name || !email || !password) {
   alert("Please fill out all required fields.");
   return;
 }
 if (password.length < 8) {
   alert("Password must be at least 8 characters.");
   return;
 }
 try {
   const response = await fetch("../api/index.php", {
     method: "POST",
     headers: {
       "Content-Type": "application/json"
     },
     body: JSON.stringify({
       name,
       email,
       password,
       is_admin
     })
   });
   const result = await response.json();
   if (response.status === 201 && result.success) {
     await loadUsersAndInitialize();
     addUserForm.reset();
   } else {
     alert(result.message);
   }
 } catch (error) {
   console.error(error);
   alert("An error occurred while adding the user.");
 }
}
async function handleTableClick(event) {
 const target = event.target;
 if (target.classList.contains("delete-btn")) {
   const id = target.dataset.id;
   try {
     const response = await fetch("../api/index.php?id=" + id, {
       method: "DELETE"
     });
     const result = await response.json();
     if (response.ok && result.success) {
       users = users.filter((user) => String(user.id) !== String(id));
       renderTable(users);
     } else {
       alert(result.message);
     }
   } catch (error) {
     console.error(error);
     alert("An error occurred while deleting the user.");
   }
 }
 if (target.classList.contains("edit-btn")) {
   const id = target.dataset.id;
   const currentUser = users.find((user) => String(user.id) === String(id));
   if (!currentUser) {
     return;
   }
   const updatedName = prompt("Edit name:", currentUser.name);
   if (updatedName === null) {
     return;
   }
   const updatedEmail = prompt("Edit email:", currentUser.email);
   if (updatedEmail === null) {
     return;
   }
   const updatedIsAdmin = prompt("Is admin? Enter 1 for Admin or 0 for Student:", currentUser.is_admin);
   if (updatedIsAdmin === null) {
     return;
   }
   try {
     const response = await fetch("../api/index.php", {
       method: "PUT",
       headers: {
         "Content-Type": "application/json"
       },
       body: JSON.stringify({
         id,
         name: updatedName.trim(),
         email: updatedEmail.trim(),
         is_admin: Number(updatedIsAdmin)
       })
     });
     const result = await response.json();
     if (response.ok && result.success) {
       await loadUsersAndInitialize();
     } else {
       alert(result.message);
     }
   } catch (error) {
     console.error(error);
     alert("An error occurred while updating the user.");
   }
 }
}
function handleSearch(event) {
 const searchTerm = searchInput.value.toLowerCase();
 if (searchTerm === "") {
   renderTable(users);
   return;
 }
 const filteredUsers = users.filter((user) => {
   return (
     user.name.toLowerCase().includes(searchTerm) ||
     user.email.toLowerCase().includes(searchTerm)
   );
 });
 renderTable(filteredUsers);
}
function handleSort(event) {
 const columnIndex = event.currentTarget.cellIndex;
 const propertyMap = {
   0: "name",
   1: "email",
   2: "is_admin"
 };
 const property = propertyMap[columnIndex];
 if (!property) {
   return;
 }
 const currentSortDir = event.currentTarget.dataset.sortDir || "asc";
 const newSortDir = currentSortDir === "asc" ? "desc" : "asc";
 event.currentTarget.dataset.sortDir = newSortDir;
 users.sort((a, b) => {
   let comparison;
   if (property === "name" || property === "email") {
     comparison = a[property].localeCompare(b[property]);
   } else {
     comparison = Number(a[property]) - Number(b[property]);
   }
   return newSortDir === "asc" ? comparison : -comparison;
 });
 renderTable(users);
}
let listenersAttached = false;
async function loadUsersAndInitialize() {
 try {
   const response = await fetch("../api/index.php");
   if (!response.ok) {
     console.error("Failed to fetch users.");
     alert("Failed to load users.");
     return;
   }
   const result = await response.json();
   users = result.data;
   renderTable(users);
   if (!listenersAttached) {
     changePasswordForm.addEventListener("submit", handleChangePassword);
     addUserForm.addEventListener("submit", handleAddUser);
     userTableBody.addEventListener("click", handleTableClick);
     searchInput.addEventListener("input", handleSearch);
     tableHeaders.forEach((th) => th.addEventListener("click", handleSort));
     listenersAttached = true;
   }
 } catch (error) {
   console.error(error);
   alert("An error occurred while loading users.");
 }
}
loadUsersAndInitialize();