<?php

namespace Http\View\Components;

use Core\Contracts\View\View;
use Core\Frontend\Component;
use Http\View\DataTransferObjects\TeamMemberData;

class TeamMemberCard extends Component
{
    /**
     * Create a new component instance.
     *
     * @param TeamMemberData $member Dữ liệu của thành viên đội ngũ.
     */
    public function __construct(
        public TeamMemberData $member,
    ) {
    }

    /**
     * Tạo URL ảnh đại diện động từ tên của thành viên.
     * Đây là một ví dụ về logic được xử lý trong class thay vì trong view.
     */
    public function avatarUrl(): string
    {
        // Không cần kiểm tra null nữa vì DTO đã đảm bảo $name luôn tồn tại.
        $name = $this->member->name;
        return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=4f46e5&color=fff&size=128';
    }

    /**
     * Lấy view hoặc nội dung đại diện cho component.
     * Trả về một đối tượng View tuân thủ contract của Core framework.
     */
    public function render(): View
    {
        return view('components.team-member-card');
    }
}
