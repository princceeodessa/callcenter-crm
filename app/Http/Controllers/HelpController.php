<?php

namespace App\Http\Controllers;

use App\Support\SimpleMarkdown;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HelpController extends Controller
{
    public function index()
    {
        return $this->render('docs/USER_GUIDE.md', 'full', 'полное руководство сотрудника кроссовочного отдела');
    }

    public function simple()
    {
        return $this->render('docs/SELLER_GUIDE.md', 'simple', 'самое нужное — простыми словами, по шагам');
    }

    private function render(string $relPath, string $active, string $subtitle)
    {
        $path = base_path($relPath);
        $md = file_exists($path) ? file_get_contents($path) : '# Инструкция не найдена';
        $html = SimpleMarkdown::toHtml($md);

        return view('help.index', compact('html', 'active', 'subtitle'));
    }

    public function download(Request $request): StreamedResponse
    {
        $doc = $request->query('doc') === 'simple' ? 'simple' : 'full';
        $path = base_path($doc === 'simple' ? 'docs/SELLER_GUIDE.md' : 'docs/USER_GUIDE.md');
        abort_unless(file_exists($path), 404);
        $name = $doc === 'simple' ? 'CRM-Гайд-продавца.md' : 'CRM-Кроссовки-Инструкция.md';

        return response()->streamDownload(function () use ($path) {
            echo file_get_contents($path);
        }, $name, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }
}
