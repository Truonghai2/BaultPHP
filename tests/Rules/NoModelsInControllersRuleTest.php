<?php

namespace Tests\Rules;

use App\Rules\PHPStan\NoModelsInControllersRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Reflection\ReflectionProvider;

/**
 * @extends RuleTestCase<NoModelsInControllersRule>
 */
class NoModelsInControllersRuleTest extends RuleTestCase
{
    /**
     * Trả về một instance của quy tắc cần được test.
     * PHPStan sẽ tự động inject các dependency cần thiết.
     */
    protected function getRule(): Rule
    {
        // Lấy ReflectionProvider từ container của test case.
        $reflectionProvider = self::getContainer()->getByType(ReflectionProvider::class);

        return new NoModelsInControllersRule($reflectionProvider);
    }

    /**
     * Đây là phương thức test chính.
     */
    public function testRule(): void
    {
        // 1. Phân tích file vi phạm quy tắc
        //    Và khẳng định rằng nó tạo ra một lỗi cụ thể ở một dòng cụ thể.
        $this->analyse(
            [__DIR__ . '/Data/ControllerWithModelCall.php'],
            [
                [
                    'Controller không được gọi trực tiếp Model [Data\User]. Hãy sử dụng một Use Case hoặc Service.',
                    12, // Số dòng mà lỗi xảy ra trong file ControllerWithModelCall.php
                ],
            ]
        );

        // 2. Phân tích file hợp lệ
        //    Và khẳng định rằng nó KHÔNG tạo ra lỗi nào (mảng lỗi rỗng).
        $this->analyse([__DIR__ . '/Data/ServiceWithModelCall.php'], []);
    }
}