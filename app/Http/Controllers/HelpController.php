<?php

namespace App\Http\Controllers;

use App\Support\SimpleMarkdown;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HelpController extends Controller
{
    public function index()
    {
        $path = base_path('docs/USER_GUIDE.md');
        $md = file_exists($path) ? file_get_contents($path) : '# Инструкция не найдена';
        $html = SimpleMarkdown::toHtml($md);

        return view('help.index', compact('html'));
    }

    public function download(): StreamedResponse
    {
        $path = base_path('docs/USER_GUIDE.md');
        abort_unless(file_exists($path), 404);

        return response()->streamDownload(function () use ($path) {
            echo file_get_contents($path);
        }, 'CRM-Кроссовки-Инструкция.md', ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }
}
