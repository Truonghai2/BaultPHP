<?php

namespace Tests\Unit\ORM;

use Core\ORM\Connection;
use Core\ORM\Model;
use Mockery;
use Mockery\MockInterface;
use PDO;
use PDOStatement;
use Tests\TestCase;

/**
 * A dummy model for testing purposes.
 */
class PostTestModel extends Model
{
    protected static string $table = 'posts';
}

class QueryBuilderTest extends TestCase
{
    /** @var Connection|MockInterface */
    private $connectionMock;

    /** @var PDO|MockInterface */
    private $pdoMock;

    /** @var PDOStatement|MockInterface */
    private $statementMock;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Tạo các đối tượng mock cần thiết
        $this->statementMock = Mockery::mock(PDOStatement::class);
        $this->pdoMock = Mockery::mock(PDO::class);
        $this->connectionMock = Mockery::mock(Connection::class);

        // 2. "Dạy" cho mock cách hoạt động
        // Khi connection->connection() được gọi, nó sẽ trả về pdoMock
        $this->connectionMock->shouldReceive('connection')->andReturn($this->pdoMock);

        // Khi pdo->prepare() được gọi, nó sẽ trả về statementMock
        $this->pdoMock->shouldReceive('prepare')->andReturn($this->statementMock);

        // 3. Thay thế service thật trong container bằng mock của chúng ta
        // Đây là bước quan trọng nhất. Bất cứ khi nào code gọi app(Connection::class),
        // nó sẽ nhận được $this->connectionMock thay vì Connection thật.
        $this->app->instance(Connection::class, $this->connectionMock);
    }

    public function test_get_with_where_clause_builds_correct_sql_and_hydrates_models(): void
    {
        // Arrange: Chuẩn bị dữ liệu và các kỳ vọng
        $expectedSql = 'SELECT * FROM posts WHERE status = ?';
        $expectedBindings = ['published'];
        $fakeDbResult = [
            ['id' => 1, 'title' => 'Test Post 1', 'status' => 'published'],
            ['id' => 2, 'title' => 'Test Post 2', 'status' => 'published'],
        ];

        // Thiết lập kỳ vọng cho các mock
        $this->pdoMock->shouldReceive('prepare')
            ->with($expectedSql) // Kỳ vọng prepare được gọi với đúng câu SQL
            ->andReturn($this->statementMock);

        $this->statementMock->shouldReceive('execute')
            ->with($expectedBindings) // Cải tiến: Kiểm tra xem đúng bindings đã được truyền vào
            ->andReturn(true);
        $this->statementMock->shouldReceive('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->andReturn($fakeDbResult); // Giả lập kết quả trả về từ CSDL

        // Act: Thực thi code cần test
        $posts = PostTestModel::where('status', 'published')->get();

        // Assert: Kiểm tra kết quả
        $this->assertCount(2, $posts);
        $this->assertInstanceOf(PostTestModel::class, $posts[0]);
        $this->assertEquals(1, $posts[0]->id);
        $this->assertEquals('Test Post 2', $posts[1]->title);
    }
}
