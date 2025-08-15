<?php

namespace Http\Controllers;

use Core\Routing\Attributes\Route;
use Http\View\DataTransferObjects\TeamMemberData;
use Psr\Http\Message\ResponseInterface;

class AboutController extends \Core\Http\Controller
{
    #[Route('/about', method: 'GET', name: 'about')]
    public function __invoke(): ResponseInterface
    {
        $companyData = [
            'name' => 'Bault Technologies',
            'mission' => 'Xây dựng các giải pháp phần mềm hiệu năng cao, đáng tin cậy và dễ bảo trì, giúp các nhà phát triển tập trung vào việc tạo ra giá trị.',
            'founded' => '2024',
            'team' => [
                new TeamMemberData(name: 'Alice', role: 'Lead Developer', avatar: 'https://i.pravatar.cc/150?u=alice'),
                new TeamMemberData(name: 'Bob', role: 'Backend Engineer', avatar: 'https://i.pravatar.cc/150?u=bob'),
                new TeamMemberData(name: 'Charlie', role: 'Frontend Specialist', avatar: 'https://i.pravatar.cc/150?u=charlie'),
            ],
        ];

        return response(view('about', ['company' => $companyData]));
    }
}
