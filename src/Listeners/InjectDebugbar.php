<?php

namespace App\Listeners;

use App\Events\ResponsePrepared;
use Core\Debug\DebugManager;
use DebugBar\DebugBar;
use Symfony\Component\HttpFoundation\Response;

class InjectDebugbar
{
    /**
     * The DebugBar instance.
     *
     * @var \DebugBar\DebugBar
     */
    protected DebugBar $debugbar;
    protected DebugManager $debugManager;

    /**
     * Create the event listener.
     *
     * @param \DebugBar\DebugBar $debugbar
     * @param \Core\Debug\DebugManager $debugManager
     */
    public function __construct(DebugBar $debugbar, DebugManager $debugManager)
    {
        $this->debugbar = $debugbar;
        $this->debugManager = $debugManager;
        // Đảm bảo DebugManager được kích hoạt để nó có thể thu thập dữ liệu
        $this->debugManager->enable();
    }

    /**
     * Handle the event.
     *
     * @param \App\Events\ResponsePrepared $event
     * @return void
     */
    public function handle(ResponsePrepared $event): void
    {
        $response = $event->response;

        $this->debugbar->collect();

        if ($response->isRedirection()
            || ($response->headers->has('Content-Type') && strpos($response->headers->get('Content-Type'), 'html') === false)
            || $event->request->getRequestFormat() !== 'html'
            || $response->getContent() === false
        ) {
            return;
        }

        $this->inject($response);
    }

    /**
     * Injects the debug bar into the given Response.
     */
    protected function inject(Response $response): void
    {
        $content = $response->getContent();
        $pos = strripos($content, '</body>');

        if ($pos === false) {
            return;
        }

        $this->debugManager->setData($this->debugbar->getData());

        $renderedContent = view('debug.bar')->render();

        $response->setContent(substr($content, 0, $pos) . $renderedContent . substr($content, $pos));
    }
}
