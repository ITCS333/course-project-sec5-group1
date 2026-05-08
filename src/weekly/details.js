/*
  details.js — populates the weekly detail page and handles the discussion forum.

  HTML entry point: src/weekly/details.html
    <script src="details.js" defer></script>

  Required element ids:
    #week-title          — <h1>
    #week-start-date     — <p>
    #week-description    — <p>
    #week-links-list     — <ul>
    #comment-list        — <div>
    #comment-form        — <form>
    #new-comment         — <textarea>

  API base URL: ./api/index.php

  Week object shape:
    { id, title, start_date, description, links: string[] }

  Comment object shape (from comments_week):
    { id, week_id, author, text, created_at }
*/

// --- Global Data Store ---
let currentWeekId   = null;
let currentComments = [];

// --- Element Selections ---
const weekTitle       = document.getElementById("week-title");
const weekStartDate   = document.getElementById("week-start-date");
const weekDescription = document.getElementById("week-description");
const weekLinksList   = document.getElementById("week-links-list");
const commentList     = document.getElementById("comment-list");
const commentForm     = document.getElementById("comment-form");
const newCommentInput = document.getElementById("new-comment");

// --- Functions ---

/**
 * Read ?id=<value> from the URL query string.
 * Returns the string id (e.g. "5") or null if not present.
 */
function getWeekIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get("id");
}

/**
 * Populate the week information section from a week object.
 */
function renderWeekDetails(week) {
  weekTitle.textContent       = week.title;
  weekStartDate.textContent   = "Starts on: " + week.start_date;
  weekDescription.textContent = week.description;

  // Clear and rebuild the links list.
  weekLinksList.innerHTML = "";

  const links = Array.isArray(week.links) ? week.links : [];
  links.forEach(url => {
    const li = document.createElement("li");
    const a  = document.createElement("a");
    a.href        = url;
    a.textContent = url;
    a.target      = "_blank";
    li.appendChild(a);
    weekLinksList.appendChild(li);
  });
}

/**
 * Build one <article> element for a single comment.
 *   <article>
 *     <p>{text}</p>
 *     <footer>Posted by: {author}</footer>
 *   </article>
 */
function createCommentArticle(comment) {
  const article = document.createElement("article");

  const p = document.createElement("p");
  p.textContent = comment.text;

  const footer = document.createElement("footer");
  footer.textContent = "Posted by: " + comment.author;

  article.appendChild(p);
  article.appendChild(footer);

  return article;
}

/**
 * Render every comment in currentComments into #comment-list.
 */
function renderComments() {
  commentList.innerHTML = "";
  currentComments.forEach(comment => {
    commentList.appendChild(createCommentArticle(comment));
  });
}

/**
 * Handle the comment form submission.
 * Sends POST ./api/index.php?action=comment with {week_id, author, text}.
 */
async function handleAddComment(event) {
  event.preventDefault();

  const commentText = newCommentInput.value.trim();
  if (commentText === "") {
    return;
  }

  try {
    const response = await fetch("./api/index.php?action=comment", {
      method:  "POST",
      headers: { "Content-Type": "application/json" },
      body:    JSON.stringify({
        week_id: currentWeekId,
        author:  "Student",
        text:    commentText,
      }),
    });

    const result = await response.json();

    if (result && result.success) {
      // Add the new comment to the local cache and re-render.
      if (result.data) {
        currentComments.push(result.data);
      } else {
        currentComments.push({
          id:         result.id,
          week_id:    currentWeekId,
          author:     "Student",
          text:       commentText,
          created_at: "",
        });
      }
      renderComments();
      newCommentInput.value = "";
    }
  } catch (err) {
    console.error("Failed to post comment:", err);
  }
}

/**
 * Load the week and its comments in parallel, then wire up the form.
 */
async function initializePage() {
  currentWeekId = getWeekIdFromURL();

  if (!currentWeekId) {
    weekTitle.textContent = "Week not found.";
    return;
  }

  try {
    const [weekRes, commentsRes] = await Promise.all([
      fetch("./api/index.php?id=" + encodeURIComponent(currentWeekId)),
      fetch("./api/index.php?action=comments&week_id=" + encodeURIComponent(currentWeekId)),
    ]);

    const weekJson     = await weekRes.json();
    const commentsJson = await commentsRes.json();

    currentComments = (commentsJson && Array.isArray(commentsJson.data))
      ? commentsJson.data
      : [];

    if (weekJson && weekJson.success && weekJson.data) {
      renderWeekDetails(weekJson.data);
      renderComments();
      commentForm.addEventListener("submit", handleAddComment);
    } else {
      weekTitle.textContent = "Week not found.";
    }
  } catch (err) {
    console.error("Failed to initialize page:", err);
    weekTitle.textContent = "Week not found.";
  }
}

// --- Initial Page Load ---
initializePage();
