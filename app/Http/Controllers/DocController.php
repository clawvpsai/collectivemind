<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class DocController extends Controller
{
    public function getStarted(): Response
    {
        $content = view('docs.get-started')->render();

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function debuggingSkill(): Response
    {
        $content = view('docs.collectivemind-debugging')->render();

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
