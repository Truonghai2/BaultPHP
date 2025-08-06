<?php

namespace App\Rules\PHPStan;

use Core\ORM\Model;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<StaticCall>
 */
class NoModelsInControllersRule implements Rule
{
    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    /**
     * Trả về loại Node mà chúng ta muốn phân tích.
     * Ở đây, chúng ta muốn "lắng nghe" tất cả các lệnh gọi phương thức tĩnh (StaticCall).
     * Ví dụ: User::find(), Product::all(), ...
     */
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * Xử lý Node và trả về các lỗi nếu có.
     *
     * @param StaticCall $node
     * @param Scope $scope
     * @return array<string|\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // 1. Chỉ quan tâm đến code đang chạy bên trong một class.
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }

        // 2. Chỉ áp dụng quy tắc này cho các class có tên kết thúc bằng "Controller".
        if (!str_ends_with($classReflection->getName(), 'Controller')) {
            return [];
        }

        // 3. Lấy ra tên class mà phương thức tĩnh đang được gọi.
        if (!$node->class instanceof Node\Name) {
            return [];
        }
        $calledOnClassName = $scope->resolveName($node->class);

        // 4. Kiểm tra xem class đó có tồn tại và có phải là một Model hay không.
        if (!$this->reflectionProvider->hasClass($calledOnClassName)) {
            return [];
        }

        $calledOnClassReflection = $this->reflectionProvider->getClass($calledOnClassName);
        if (!$calledOnClassReflection->isSubclassOf(Model::class)) {
            return [];
        }

        // 5. Nếu tất cả điều kiện trên đều đúng, tạo một lỗi.
        $error = RuleErrorBuilder::message(sprintf(
            'Controller không được gọi trực tiếp Model [%s]. Hãy sử dụng một Use Case hoặc Service.',
            $calledOnClassReflection->getName(),
        ))->build();

        return [$error];
    }
}
