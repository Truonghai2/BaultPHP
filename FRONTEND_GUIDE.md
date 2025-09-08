# Frontend Integration Guide for BaultPHP

Welcome, frontend developers! This document will guide you on how to connect and interact with a backend built with BaultPHP.

BaultPHP is a powerful backend that provides RESTful APIs and WebSocket connections for building dynamic and modern web applications.

## Table of Contents

1.  [API Overview](#1-api-overview)
2.  [Authentication](#2-authentication)
3.  [API Interaction Examples (JavaScript Fetch)](#3-api-interaction-examples-javascript-fetch)
    - Get List of Posts
    - Create a New Post
    - Update a Post
    - Delete a Post
4.  [Handling CORS (Cross-Origin Resource Sharing)](#4-handling-cors-cross-origin-resource-sharing)
5.  [Real-time Integration with WebSocket](#5-real-time-integration-with-websocket)

---

## 1. API Overview

The BaultPHP backend provides REST-compliant API endpoints. A typical example is the post management endpoints defined in `CRUD_TUTORIAL.md`:

- **`GET /api/posts`**: Get a list of all posts.
- **`POST /api/posts`**: Create a new post.
- **`GET /api/posts/{id}`**: Get the details of a specific post.
- **`PUT /api/posts/{id}`**: Update a post.
- **`DELETE /api/posts/{id}`**: Delete a post.

All data is exchanged in **JSON** format.

---

## 2. Authentication with OAuth2

The system uses **OAuth2** with the following flow

1.  **Login**: The frontend sends the user's `email` and `password` to a login endpoint, for example, `POST /api/login`.
2.  **Receive Token**: If the login information is correct, the backend will return a JWT.
    ```json
    {
      "access_token": "ey...",
      "token_type": "Bearer",
      "expires_in": 3600
    }
    ```
3.  **Save Token**: The frontend needs to save this `access_token` securely, usually in `localStorage` or `sessionStorage`.
4.  **Send Token with Each Request**: For every subsequent request to protected endpoints, the frontend must attach this token to the `Authorization` header.

    **Header Example:**

    ```
    Authorization: Bearer ey...
    ```

If the token is invalid or missing, the backend will return a `401 Unauthorized` error.

---

## 3. API Interaction Examples (JavaScript Fetch)

Below are examples using the JavaScript `fetch` API to perform CRUD operations with the `Post` module.

Assume the backend is running at `http://localhost:88` (as configured in the docker-compose Nginx setup).

### Get List of Posts

```javascript
async function getPosts(baseUrl = "http://localhost:88") {
  const response = await fetch(`${baseUrl}/api/posts`);
  if (!response.ok) {
    throw new Error("Failed to fetch posts");
  }
  const posts = await response.json();
  console.log(posts);
  return posts;
}
```

### Create a New Post

```javascript
async function createPost(
  title,
  content,
  token,
  baseUrl = "http://localhost:88",
) {
  const response = await fetch(`${baseUrl}/api/posts`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${token}`, // Send authentication token
    },
    body: JSON.stringify({ title, content }),
  });

  if (response.status !== 201) {
    // 201 Created
    throw new Error("Failed to create post");
  }
  const newPost = await response.json();
  console.log("Created post:", newPost);
  return newPost;
}
```

### Update a Post

```javascript
async function updatePost(
  postId,
  title,
  content,
  token,
  baseUrl = "http://localhost:88",
) {
  const response = await fetch(`${baseUrl}/api/posts/${postId}`, {
    method: "PUT",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify({ title, content }),
  });

  if (!response.ok) {
    throw new Error("Failed to update post");
  }
  const updatedPost = await response.json();
  console.log("Updated post:", updatedPost);
  return updatedPost;
}
```

### Delete a Post

```javascript
async function deletePost(postId, token, baseUrl = "http://localhost:88") {
  const response = await fetch(`${baseUrl}/api/posts/${postId}`, {
    method: "DELETE",
    headers: {
      Authorization: `Bearer ${token}`,
    },
  });

  if (response.status !== 204) {
    // 204 No Content
    throw new Error("Failed to delete post");
  }
  console.log("Post deleted successfully");
}
```

---

## 4. Handling CORS (Cross-Origin Resource Sharing)

During development, your frontend (e.g., `http://localhost:5173`) and backend (`http://localhost:88`) often run on two different "origins" (due to different ports). The browser will block requests from the frontend to the backend for security reasons, causing a **CORS** error.

To fix this, the BaultPHP backend needs to be configured to allow requests from the frontend's origin. This is usually done by adding a **Middleware** in BaultPHP to automatically add the necessary HTTP headers to each response.

**Necessary headers:**

- `Access-Control-Allow-Origin`: Specifies the allowed origin (e.g., `http://localhost:5173` or `*` for all).
- `Access-Control-Allow-Methods`: The allowed HTTP methods (e.g., `GET, POST, PUT, DELETE, OPTIONS`).
- `Access-Control-Allow-Headers`: The allowed headers in the request (e.g., `Content-Type, Authorization`).

Contact the backend team to ensure this middleware is enabled.

---

## 5. Real-time Integration with WebSocket

BaultPHP uses **Centrifuge** to provide real-time features via WebSocket. This is very useful for notifications, chat, or live data updates.

**Frontend Library:**

You can use the `centrifuge-js` library to connect.

```bash
npm install centrifuge
```

**Example of connecting and listening for events:**

```javascript
import { Centrifuge } from "centrifuge";

// Get the connection URL and token from a dedicated backend API endpoint
const connectionUrl = "ws://localhost:8000/connection/websocket"; // URL of Centrifugo
const connectionToken = "..."; // This token must be generated by the backend for each user

const centrifuge = new Centrifuge(connectionUrl, {
  token: connectionToken,
});

// Listen for the successful connection event
centrifuge.on("connected", function (ctx) {
  console.log("Connected to WebSocket server", ctx);
});

// Listen to a specific channel, e.g., the 'notifications' channel
const sub = centrifuge.newSubscription("notifications");

// Listen for messages published to this channel
sub.on("publication", function (ctx) {
  console.log("Received new notification:", ctx.data);
  // Example: Display a notification to the user
});

sub.subscribe();

centrifuge.connect();
```

This document provides the basic information to get you started. Happy building a great user interface with BaultPHP!
