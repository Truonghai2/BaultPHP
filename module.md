Modules/
└── Post/
├── Application/
│ ├── Commands/ # (Use Cases) Data writing commands (CUD)
│ │ └── CreatePostCommand.php
│ ├── Handlers/ # Handles Commands
│ │ └── CreatePostHandler.php
│ ├── Queries/ # (Use Cases) Data reading commands (R)
│ ├── Policies/ # Authorization Policy classes
│ │ └── PostPolicy.php
│ └── Listeners/ # Handles Events
│
├── Domain/
│ ├── Entities/ # Core business objects (with identity)
│ │ └── Post.php # (Can be a Model if using Active Record)
│ ├── Events/ # Business events
│ │ └── PostWasCreated.php
│ ├── Repositories/ # Interfaces for data access
│ │ └── PostRepositoryInterface.php
│ └── Services/ # Complex business logic that doesn't belong to any Entity
│
├── Infrastructure/
│ ├── Migrations/ # Database migrations
│ │ └── 2025_07_16_120000_create_posts_table.php
│ ├── Models/ # ORM Models (implementation of Entity)
│ │ └── Post.php # (If separate from Entity)
│ └── Repositories/ # Concrete implementation of Repository Interfaces
│ └── EloquentPostRepository.php
│
├── Http/
│ ├── Controllers/ # Only dispatches requests, calls Use Case
│ │ └── PostController.php
│ └── Requests/ # FormRequests for validation
│ └── CreatePostRequest.php
│
└── Providers/
└── PostServiceProvider.php # Registers everything for this module
