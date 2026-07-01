document.addEventListener('DOMContentLoaded', function () {
    var searchContainer = document.getElementById('pagefind-search');
    if (!searchContainer) return;

    var searchDiv = document.createElement('div');
    searchDiv.className = 'pagefind-ui';
    searchDiv.setAttribute('data-pagefind-ui', '');
    searchDiv.setAttribute('data-show-images', 'false');
    searchContainer.appendChild(searchDiv);

    // PageFind auto-initializes .pagefind-ui elements
});
