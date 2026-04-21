/*
  Requirement: Populate the "Course Assignments" list page.

  Instructions:
  1. This file is already linked to `list.html` via:
         <script src="list.js" defer></script>

  2. In `list.html`, the <section id="assignment-list-section"> is the
     container that this script populates.

  3. Implement the TODOs below.

  API base URL: ./api/index.php
  Successful list response shape: { success: true, data: [ ...assignment objects ] }
  Each assignment object shape:
    {
      id:          number,   // integer primary key from the assignments table
      title:       string,
      due_date:    string,   // "YYYY-MM-DD" — matches the SQL column name
      description: string,
      files:       string[]  // already decoded array of URL strings
    }
*/

// --- Element Selections ---
// TODO: Select the section for the assignment list using its
//       id 'assignment-list-section'.
let section = document.getElementById('assignment-list-section'); 
// --- Functions ---

/**
 * TODO: Implement createAssignmentArticle.
 *
 * Parameters:
 *   assignment — one object from the API response with the shape:
 *     {
 *       id:          number,
 *       title:       string,
 *       due_date:    string,   // "YYYY-MM-DD" — use due_date, not dueDate
 *       description: string,
 *       files:       string[]
 *     }
 *
 * Returns:
 *   An <article> element matching the structure shown in list.html:
 *     <article>
 *       <h2>{title}</h2>
 *       <p>Due: {due_date}</p>
 *       <p>{description}</p>
 *       <a href="details.html?id={id}">View Details &amp; Discussion</a>
 *     </article>
 *
 * Important: the href MUST be "details.html?id=<id>" (integer id from
 * the assignments table) so that details.js can read the id from the URL.
 */
function createAssignmentArticle(assignment) {
  // ... your implementation here ...

  let article = document.createElement('articl'); 
  let title = document.createElement('h1'); 
 
  title.tectContent = assignment.title; 

  let due = document.createElement('p'); 
  due.textContent = 'Due: ' + assignment.due_date; 

  let desc = document.createElement('p'); 
  desc.textContent = assignment.description;

  let link = document.createElement('a');
  link.href = 'details.html?id=' + assignment.id; 
  link.textContent = 'View Details & Discussion'; 
  
  article.appendChild(title);
  article.appendChild(due);
  article.appendChild(desc);
  article.appendChild(link);
  return article;
}

/**
 * TODO: Implement loadAssignments (async).
 *
 * It should:
 * 1. Use fetch() to GET data from './api/index.php'.
 *    The API returns JSON in the shape:
 *      { success: true, data: [ ...assignment objects ] }
 * 2. Parse the JSON response.
 * 3. Clear any existing content from the list section.
 * 4. Loop through the data array. For each assignment object:
 *    - Call createAssignmentArticle(assignment).
 *    - Append the returned <article> to the list section.
 */
async function loadAssignments() {
  // ... your implementation here ...
let section = document.getElementById('assignment-list-section');

   let response = await fetch('/api/index.php');

  let result = await response.json();
   section.innerHTML = '';
   if (result.success) {
 result.data.forEach(function (assignment) {
 let article = createAssignmentArticle(assignment);
section.appendChild(article);
     });
      }
}

// --- Initial Page Load ---
loadAssignments();
