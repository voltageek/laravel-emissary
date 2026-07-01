<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->title ? $page->title . ' — ' : '' }}{{ $page->siteName }}</title>
    <meta name="description" content="{{ $page->description ?? $page->siteDescription }}">
    <link rel="stylesheet" href="{{ Illuminate\Support\Str::finish($page->baseUrl, '/') }}assets/build/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <link href="/pagefind/pagefind-ui.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/php.min.js"></script>
    <script>hljs.highlightAll();</script>
    <style>
        :root {
            --sidebar-width: 260px;
            --header-height: 60px;
            --accent: #6366f1;
            --accent-light: #e0e7ff;
            --bg: #ffffff;
            --bg-alt: #f8fafc;
            --border: #e2e8f0;
            --text: #1e293b;
            --text-muted: #64748b;
            --code-bg: #1e293b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: var(--text); line-height: 1.6; display: flex; min-height: 100vh; }
        .sidebar { width: var(--sidebar-width); position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; border-right: 1px solid var(--border); background: var(--bg); padding: 1rem 0; z-index: 10; }
        .sidebar .site-title { padding: 0.5rem 1.5rem 1rem; font-size: 1.25rem; font-weight: 700; color: var(--accent); border-bottom: 1px solid var(--border); margin-bottom: 0.5rem; }
        .sidebar nav { padding: 0 0.5rem; }
        .sidebar nav ul { list-style: none; }
        .sidebar nav li { margin: 0; }
        .sidebar nav a { display: block; padding: 0.35rem 1rem; color: var(--text-muted); text-decoration: none; font-size: 0.9rem; border-radius: 4px; transition: background 0.15s, color 0.15s; }
        .sidebar nav a:hover { background: var(--accent-light); color: var(--accent); }
        .sidebar nav a.active { background: var(--accent-light); color: var(--accent); font-weight: 600; }
        .sidebar nav .section-title { padding: 0.75rem 1rem 0.25rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
        .sidebar nav .section-items { padding-left: 0; }
        .content { margin-left: var(--sidebar-width); flex: 1; padding: 2rem 3rem; max-width: 900px; }
        .breadcrumbs { margin-bottom: 1.5rem; font-size: 0.85rem; color: var(--text-muted); }
        .breadcrumbs a { color: var(--text-muted); text-decoration: none; }
        .breadcrumbs a:hover { color: var(--accent); }
        .breadcrumbs span { margin: 0 0.4rem; }
        h1 { font-size: 2rem; margin-bottom: 0.5rem; }
        h2 { font-size: 1.5rem; margin: 2rem 0 0.75rem; border-bottom: 1px solid var(--border); padding-bottom: 0.25rem; }
        h3 { font-size: 1.15rem; margin: 1.5rem 0 0.5rem; }
        h4 { font-size: 1rem; margin: 1rem 0 0.5rem; }
        p { margin: 0.75rem 0; }
        pre { background: var(--code-bg); border-radius: 6px; padding: 1rem; overflow-x: auto; margin: 1rem 0; }
        code { font-family: 'Fira Code', 'Consolas', monospace; font-size: 0.875rem; }
        :not(pre) > code { background: var(--bg-alt); padding: 0.15em 0.4em; border-radius: 3px; color: var(--accent); font-size: 0.85em; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 0.5rem 0.75rem; text-align: left; border: 1px solid var(--border); }
        th { background: var(--bg-alt); font-weight: 600; }
        .tldr-box { background: var(--accent-light); border-left: 4px solid var(--accent); padding: 1rem 1.25rem; border-radius: 0 6px 6px 0; margin: 1.5rem 0; }
        .tldr-box h4 { margin: 0 0 0.5rem; color: var(--accent); }
        .next-link { margin-top: 3rem; padding-top: 1rem; border-top: 1px solid var(--border); }
        .next-link a { color: var(--accent); text-decoration: none; font-weight: 600; }
        .callout { padding: 0.75rem 1rem; border-radius: 6px; margin: 1rem 0; }
        .callout-warning { background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; }
        .callout-info { background: #dbeafe; border: 1px solid #3b82f6; color: #1e40af; }
        .version-switcher { padding: 0.5rem 1rem; border-bottom: 1px solid var(--border); margin-bottom: 0.5rem; }
        .version-switcher select { width: 100%; padding: 0.4rem 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.85rem; background: var(--bg); color: var(--text); }
        .page-header { margin-bottom: 2rem; }
        .page-header p { color: var(--text-muted); font-size: 1.05rem; }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .content { margin-left: 0; padding: 1.5rem; }
        }
    </style>
</head>
<body>
    @include('_partials.sidebar')

    <main class="content">
        @include('_partials.breadcrumbs')

        <div class="page-header">
            <h1>{{ $page->title }}</h1>
            @if ($page->description)
                <p>{{ $page->description }}</p>
            @endif
        </div>

        @yield('body')

        @include('_partials.next-link')
    </main>

    <script src="/pagefind/pagefind-ui.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
    <script>mermaid.initialize({ startOnLoad: true, theme: 'neutral' });</script>
    @yield('scripts')
</body>
</html>
