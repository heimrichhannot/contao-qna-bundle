<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\View;

use Symfony\Component\HttpFoundation\Response;

final readonly class TurboResponseFactory
{
    public const string TURBO_STREAM_CONTENT_TYPE = 'text/vnd.turbo-stream.html';

    public function html(string $content, int $statusCode = Response::HTTP_OK): Response
    {
        return new Response($content, $statusCode, [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function stream(string $content, int $statusCode = Response::HTTP_OK): Response
    {
        return new Response($content, $statusCode, [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => self::TURBO_STREAM_CONTENT_TYPE.'; charset=UTF-8',
        ]);
    }
}
