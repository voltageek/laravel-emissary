<nav class="breadcrumbs">
    @php
        $crumbs = [];
        $path = '';
        $segments = array_filter(explode('/', trim($page->getPath(), '/')));
    @endphp

    <a href="{{ Illuminate\Support\Str::finish($page->baseUrl, '/') }}">Home</a>

    @foreach ($segments as $segment)
        @php
            $path .= '/' . $segment;
            $title = Illuminate\Support\Str::headline($segment);
        @endphp
        <span>&gt;</span>
        @if (!$loop->last)
            <a href="{{ Illuminate\Support\Str::finish($page->baseUrl, '/') . ltrim($path, '/') }}">{{ $title }}</a>
        @else
            <span>{{ $page->title }}</span>
        @endif
    @endforeach
</nav>
