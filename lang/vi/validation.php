<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Các Dòng Ngôn Ngữ Validation Mặc Định
    |--------------------------------------------------------------------------
    |
    | Các dòng sau chứa các thông báo lỗi mặc định được sử dụng bởi
    | lớp validator. Một số quy tắc có nhiều phiên bản như size.
    |
    */

    'required' => 'Trường :attribute là bắt buộc.',
    'min' => [
        'string' => 'Trường :attribute phải có ít nhất :min ký tự.',
    ],
    'max' => [
        'string' => 'Trường :attribute không được vượt quá :max ký tự.',
    ],
    'slug' => 'Trường :attribute không phải là một slug hợp lệ.',
    'no_profanity' => 'Trường :attribute chứa các từ ngữ không cho phép.',

    'Invalid credentials.' => 'Thông tin đăng nhập không chính xác.',

    /*
    |--------------------------------------------------------------------------
    | Các Dòng Ngôn Ngữ Validation Tùy Chỉnh
    |--------------------------------------------------------------------------
    |
    | Tại đây bạn có thể chỉ định các thông báo lỗi tùy chỉnh cho các thuộc tính
    | bằng cách sử dụng quy ước "attribute.rule" để đặt tên cho các dòng.
    | Điều này giúp bạn nhanh chóng chỉ định một thông báo tùy chỉnh cụ thể.
    |
    */

    'custom' => [
        // Bạn có thể thêm các thông báo tùy chỉnh cụ thể ở đây nếu cần
    ],

    /*
    |--------------------------------------------------------------------------
    | Tên Thuộc Tính Validation Tùy Chỉnh
    |--------------------------------------------------------------------------
    |
    | Các dòng sau được sử dụng để thay thế placeholder :attribute
    | bằng một cái tên thân thiện hơn như "Địa chỉ E-Mail" thay vì "email".
    |
    */

    'attributes' => [
        'title' => 'tiêu đề',
        'content' => 'nội dung',
    ],

];
