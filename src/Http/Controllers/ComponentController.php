<?php

namespace App\Http\Controllers;

use App\Http\ResponseFactory;
use Core\Frontend\Attributes\CallableMethod;
use Core\Frontend\ChecksumService;
use Core\Routing\Attributes\Route;
use Core\Validation\ValidationException;
use Psr\Http\Message\ServerRequestInterface as Request;

class ComponentController
{
    #[Route('/bault/update', method: 'POST', middleware: ['web'])]
    public function __invoke(Request $request, ResponseFactory $responseFactory, ChecksumService $checksumService): \App\Http\JsonResponse
    {
        $snapshot = json_decode($request->input('snapshot'), true);
        $updates = $request->input('updates');
        $calls = $request->input('calls');

        $componentClass = $snapshot['class'];

        if (!class_exists($componentClass)) {
            return $responseFactory->json(['error' => 'Component not found'], 404);
        }

        if (!is_subclass_of($componentClass, \Core\Frontend\Component::class)) {
            return $responseFactory->json(['error' => 'Component is not allowed.'], 403);
        }

        if (!$checksumService->verify($componentClass, $snapshot['data'], $snapshot['checksum'] ?? '')) {
            return $responseFactory->json(['error' => 'The component snapshot has been tampered with.'], 419);
        }

        /** @var \Core\Frontend\Component $component */
        $component = app($componentClass);

        $component->hydrateState($snapshot['data']);

        try {
            if ($calls) {
                $method = $calls['method'];
                $params = $calls['params'];

                if (method_exists($component, $method)) {
                    $reflectionMethod = new \ReflectionMethod($component, $method);
                    $attributes = $reflectionMethod->getAttributes(CallableMethod::class);

                    if (empty($attributes)) {
                        return $responseFactory->json(['error' => 'The method is not callable.'], 403);
                    }
                    app()->call([$component, $method], $params);
                } else {
                    return $responseFactory->json(['error' => 'Method not found on component.'], 404);
                }
            }
        } catch (ValidationException $e) {
            return $responseFactory->json(['errors' => $e->errors()], 422);
        }

        $html = $component->render();

        $dispatches = $component->getDispatchQueue();

        $newState = $component->getState();
        $newSnapshot = [
            'class' => $componentClass,
            'data' => $newState,
            'checksum' => $checksumService->generate($componentClass, $newState),
        ];

        return $responseFactory->json([
            'snapshot' => json_encode($newSnapshot),
            'html' => $html,
            'dispatches' => $dispatches,
        ]);
    }
}
