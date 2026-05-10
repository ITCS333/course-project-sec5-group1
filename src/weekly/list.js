/*
  list.js — populates the "Weekly Course Breakdown" list page.

  HTML entry point: src/weekly/list.html
    <script src="list.js" defer></script>
    <section id="week-list-section"></section>   <-- populated below

  API endpoint: ./api/index.php
    GET  → { success: true, data: [ ...week objects ] }

  Week object shape returned by the API:
    {
      id:          number,
      title:       string,
      start_date:  string,   // "YYYY-MM-DD"
      description: string,
      links:       string[]  // decoded array of URL strings
    }
*/

// --- Element Selections ---
const listSection = document.getElementById("week-list-section");

// --- Functions ---

/**
 * Build one <article> element for a single week.
 *
 * Structure:
 *   <article>
 *     <h2>{title}</h2>
 *     <p>Starts on: {start_date}</p>
 *     <p>{description}</p>
 *     <a href="details.html?id={id}">View Details & Discussion</a>
 *   </article>
 */
function createWeekArticle(week) {
  const article = document.createElement("article");

  const heading = document.createElement("h2");
  heading.textContent = week.title;

  const startP = document.createElement("p");
  startP.textContent = "Starts on: " + week.start_date;

  const descP = document.createElement("p");
  descP.textContent = week.description;

  const link = document.createElement("a");
  link.href = "details.html?id=" + week.id;
  link.textContent = "View Details & Discussion";

  article.appendChild(heading);
  article.appendChild(startP);
  article.appendChild(descP);
  article.appendChild(link);

  return article;
}

/**
 * Fetch all weeks from the API and render them into #week-list-section.
 */
async function loadWeeks() {
  try {
    const response = await fetch("./api/index.php");
    const result   = await response.json();

    // Clear any existing content.
    listSection.innerHTML = "";

    if (result && result.success && Array.isArray(result.data)) {
      result.data.forEach(week => {
        const article = createWeekArticle(week);
        listSection.appendChild(article);
      });
    }
  } catch (err) {
    console.error("Failed to load weeks:", err);
  }
}

// --- Initial Page Load ---
loadWeeks();
