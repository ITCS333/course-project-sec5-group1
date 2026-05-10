let currentResourceId = null;
let currentComments = [];

const titleElement = document.querySelector('#resource-title');
const descriptionElement = document.querySelector('#resource-description');
const linkElement = document.querySelector('#resource-link');
const commentListElement = document.querySelector('#comment-list');
const commentFormElement = document.querySelector('#comment-form');
const newCommentElement = document.querySelector('#new-comment');

function getResourceIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

function renderResourceDetails(resource) {
  titleElement.textContent = resource.title;
  descriptionElement.textContent = resource.description || '';
  linkElement.setAttribute('href', resource.link);
}

function createCommentArticle(comment) {
  const article = document.createElement('article');

  const text = document.createElement('p');
  text.textContent = comment.text;

  const footer = document.createElement('footer');
  footer.textContent = `Posted by: ${comment.author}`;

  article.appendChild(text);
  article.appendChild(footer);
  return article;
}

function renderComments() {
  commentListElement.innerHTML = '';
  currentComments.forEach(comment => {
    commentListElement.appendChild(createCommentArticle(comment));
  });
}

function handleAddComment(event) {
  event.preventDefault();

  const commentText = newCommentElement.value.trim();
  if (!commentText) return;

  fetch('./api/index.php?action=comment', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      resource_id: currentResourceId,
      author: 'Student',
      text: commentText
    })
  })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        const newComment = result.data || {
          id: result.id,
          resource_id: currentResourceId,
          author: 'Student',
          text: commentText,
          created_at: ''
        };
        currentComments.push(newComment);
        renderComments();
        newCommentElement.value = '';
      }
    });
}

async function initializePage() {
  currentResourceId = getResourceIdFromURL();

  if (!currentResourceId) {
    titleElement.textContent = 'Resource not found.';
    return;
  }

  try {
    const [resourceResponse, commentsResponse] = await Promise.all([
      fetch(`./api/index.php?id=${currentResourceId}`),
      fetch(`./api/index.php?resource_id=${currentResourceId}&action=comments`)
    ]);

    const resourceResult = await resourceResponse.json();
    const commentsResult = await commentsResponse.json();

    if (resourceResult.success && resourceResult.data) {
      currentComments = Array.isArray(commentsResult.data) ? commentsResult.data : [];
      renderResourceDetails(resourceResult.data);
      renderComments();
      commentFormElement.addEventListener('submit', handleAddComment);
    } else {
      titleElement.textContent = 'Resource not found.';
    }
  } catch (error) {
    titleElement.textContent = 'Resource not found.';
  }
}

initializePage();
