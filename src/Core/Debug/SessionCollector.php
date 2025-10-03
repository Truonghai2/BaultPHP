<?php

namespace Core\Debug;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class SessionCollector
 *
 * Thu thập dữ liệu từ session hiện tại để hiển thị trong Debugbar.
 */
class SessionCollector extends DataCollector implements Renderable
{
    protected SessionInterface $session;

    /**
     * @param SessionInterface $session
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(): array
    {
        $data = $this->session->all();

        return $this->getDataFormatter()->formatVar($data);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'session';
    }

    /**
     * {@inheritdoc}
     */
    public function getWidgets(): array
    {
        return [
            'session' => [
                'icon' => 'archive',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'session',
                'default' => '{}',
            ],
        ];
    }
}
