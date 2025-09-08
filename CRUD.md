# Guide: Building a Complete CRUD with BaultPHP

This document will guide you through the steps to create a complete post management module (`Post`), including Create, Read, Update, and Delete (CRUD) functionalities through an API.

## Objective

We will build the following API endpoints:

- `GET /api/posts`: Get a list of all posts.
- `POST /api/posts`: Create a new post.
- `GET /api/posts/{id}`: Get the details of a specific post.
- `PUT /api/posts/{id}`: Update a post.
- `DELETE /api/posts/{id}`: Delete a post.

## Step 1: Create the `Post` Module

First, let's use the CLI tool to create a new module named `Post`. Open your terminal and run the command:

```bash
php cli ddd:make-module Post
```

This command will create the entire necessary directory structure inside `Modules/Post`, ready for us to develop.

## Step 2: Create the Database Migration

Next, we need to define the structure for the `posts` table in the database.

1.  Create a new migration file. The filename should follow the format `YYYY_MM_DD_His_create_posts_table.php`.
    **Create file:** `Modules/Post/Infrastructure/Migrations/2025_07_16_120000_create_posts_table.php`

2.  Add the following content to the newly created migration file:

    ```php
    <?php

    use Core\Database\Migration;
    use Illuminate\Database\Schema\Blueprint;

    return new class extends Migration
    {
        public function up(): void
        {
            $this->schema->create('posts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('content');
                $table->timestamps(); // Automatically creates created_at and updated_at columns
            });
        }

        public function down(): void
        {
            $this->schema->dropIfExists('posts');
        }
    };
    ```

## Step 3: Create the Model

Now, let's create the ORM Model to interact with the `posts` table.

1.  **Create file:** `Modules/Post/Infrastructure/Models/Post.php`
2.  Add content:

    ```php
    <?php

    namespace Modules\Post\Infrastructure\Models;

    use Core\ORM\Model;

    class Post extends Model
    {
        // Table name in the database
        protected static string $table = 'posts';

        // The attributes that are mass assignable.
        protected $fillable = [
            'title',
            'content',
        ];
    }
    ```

## Step 4: Run the Migration

Once you have the migration file and the model, run the following command to create the `posts` table in the database:

```bash
php cli ddd:migrate
```

You will see a message indicating that the migration was run successfully.

## Step 5: Create the Controller and Define Routes

We will create a `PostController` and use **Attribute-based Routing** to define the endpoints.

1.  **Create file:** `Modules/Post/Http/Controllers/PostController.php`
2.  Add the initial content for the controller. We will fill in the logic for the methods in the next step.

    ```php
    <?php

    namespace Modules\Post\Http\Controllers;

    use Core\Routing\Attributes\Route;
    use Http\Request;
    use Core\Events\EventDispatcherInterface;
    use Http\Response;
    use Modules\Post\Infrastructure\Models\Post;

    class PostController
    {
        #[Route('/api/posts', method: 'GET')]
        public function index(): Response
        {
            // Logic to get the list of posts
        }

        #[Route('/api/posts', method: 'POST')]
        public function store(Request $request, EventDispatcherInterface $dispatcher): Response
        {
            // Logic to create a new post
        }

        #[Route('/api/posts/{id}', method: 'GET')]
        public function show(int $id): Response
        {
            // Logic to view a single post
        }

        #[Route('/api/posts/{id}', method: 'PUT')]
        public function update(Request $request, int $id): Response
        {
            // Logic to update a post
        }

        #[Route('/api/posts/{id}', method: 'DELETE')]
        public function destroy(int $id): Response
        {
            // Logic to delete a post
        }
    }
    ```

**Note:** The framework will automatically scan and register routes defined by Attributes. To speed this up in a production environment, you can run `php cli route:cache`.

## Step 6: Complete the CRUD Logic in the Controller

Now, we will fill in the logic for each method in the `PostController`.

### a. Get List (Read - Index)

Update the `index()` method:

```php
    #[Route('/api/posts', method: 'GET')]
    public function index(): Response
    {
        $posts = Post::all();
        return response()->json($posts);
    }
```

### b. Create New (Create - Store)

Update the `store()` method:

```php
    #[Route('/api/posts', method: 'POST')]
    public function store(Request $request, EventDispatcherInterface $dispatcher): Response
    {
        // For simplicity, we will validate directly here.
        // In a real application, you should create a separate FormRequest.
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $post = Post::create($validated);

        // Dispatch an event so other systems can handle it
        $dispatcher->dispatch(new \Modules\Post\Domain\Events\PostWasCreated($post));

        return response()->json($post, 201); // 201 Created
    }
```

### c. View Details (Read - Show)

Update the `show()` method:

```php
    #[Route('/api/posts/{id}', method: 'GET')]
    public function show(int $id): Response
    {
        $post = Post::findOrFail($id);
        return response()->json($post);
    }
```

### d. Update

Update the `update()` method:

```php
    #[Route('/api/posts/{id}', method: 'PUT')]
    public function update(Request $request, int $id): Response
    {
        $post = Post::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $post->update($validated);

        return response()->json($post);
    }
```

### e. Delete (Destroy)

Update the `destroy()` method:

```php
    #[Route('/api/posts/{id}', method: 'DELETE')]
    public function destroy(int $id): Response
    {
        $post = Post::findOrFail($id);
        $post->delete();

        return response()->json(null, 204); // 204 No Content
    }
```

## Step 7: Test the API

Your CRUD module is now complete! Start the server:

```bash
php cli serve
```

You can use a tool like **Postman**, **Insomnia**, or `curl` to test the endpoints:

**Create a new post:**

```bash
curl -X POST http://localhost:8080/api/posts \
     -H "Content-Type: application/json" \
     -d '{"title": "My First Post", "content": "This is the content of my first post."}'
```

**Get the list of posts:**

```bash
curl http://localhost:8080/api/posts
```

**Update the post with ID 1:**

```bash
curl -X PUT http://localhost:8080/api/posts/1 \
     -H "Content-Type: application/json" \
     -d '{"title": "My Updated Post", "content": "Content has been updated."}'
```

**Delete the post with ID 1:**

```bash
curl -X DELETE http://localhost:8080/api/posts/1
```

## Summary

Congratulations! You have successfully built a complete CRUD module in BaultPHP. From here, you can apply more advanced concepts such as:

- Creating separate `FormRequest` classes to handle complex validation.
- Separating business logic into `Use Case` classes within the module's `Application` directory.
- Using the `Repository Pattern` to abstract data access.
- Adding authentication and authorization mechanisms to the endpoints.
