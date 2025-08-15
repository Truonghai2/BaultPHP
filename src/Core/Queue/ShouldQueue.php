<?php

namespace Core\Queue;

/**
 * Interface này chỉ đóng vai trò là một "marker".
 * Bất kỳ Event Listener nào implement interface này sẽ tự động
 * được đẩy vào hàng đợi thay vì thực thi ngay lập tức.
 */
interface ShouldQueue
{
}
