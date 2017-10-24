<?php

namespace App\Http\Controllers;

use App\Services\DistService;
use Symfony\Component\HttpFoundation\Response;

class DistController extends Controller
{
    /** @var \App\Services\DistService */
    protected $distService;

    public function __construct(DistService $distService)
    {
        $this->distService = $distService;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function download(string $site, string $path): Response
    {
        return response()
            ->stream(function () use ($site, $path) {
                $this->distService->createDistCache($site, $path, function (string $data) {
                    echo $data;
                });
            }, 200, ['Content-Type' => 'application/zip']);
    }
}
