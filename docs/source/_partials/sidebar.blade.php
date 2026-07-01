<aside class="sidebar">
    <div class="site-title">
        <a href="{{ Illuminate\Support\Str::finish($page->baseUrl, '/') }}" style="color: inherit; text-decoration: none;">
            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%236366f1' stroke-width='2'%3E%3Cpath d='M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'/%3E%3C/svg%3E" alt="" style="vertical-align: middle; margin-right: 0.4rem; margin-bottom: 2px;">
            {{ $page->siteName }}
        </a>
    </div>

    @include('_partials.version-switcher')

    <div id="pagefind-search"></div>

    <nav>
        @php
            $navigation = $page->navigation ?? [];
            $currentPath = $page->getPath();
        @endphp

        @foreach ($navigation as $section)
            <div class="section-title">{{ $section['title'] }}</div>
            <ul class="section-items">
                @foreach ($section['items'] as $item)
                    <li>
                        @php
                            $url = Illuminate\Support\Str::finish($page->baseUrl, '/') . ltrim($item['url'], '/');
                            $isActive = $currentPath === trim($item['url'], '/') || '/' . $currentPath === $item['url'];
                        @endphp
                        <a href="{{ $url }}" class="@if ($isActive) active @endif">{{ $item['label'] }}</a>
                        @if (!empty($item['children']))
                            <ul class="section-items">
                                @foreach ($item['children'] as $child)
                                    <li>
                                        @php
                                            $childUrl = Illuminate\Support\Str::finish($page->baseUrl, '/') . ltrim($child['url'], '/');
                                            $childActive = $currentPath === trim($child['url'], '/') || '/' . $currentPath === $child['url'];
                                        @endphp
                                        <a href="{{ $childUrl }}" class="@if ($childActive) active @endif" style="padding-left: 2rem;">{{ $child['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endforeach
    </nav>
</aside>
