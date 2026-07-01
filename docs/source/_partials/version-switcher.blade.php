<div class="version-switcher">
    <select id="version-select" onchange="switchVersion(this.value)">
        <option value="/" selected>latest</option>
        <option value="/2.x/">2.x</option>
    </select>
</div>

<script>
function switchVersion(versionPath) {
    var currentPath = window.location.pathname;
    var trimmed = versionPath.replace(/^\/|\/$/g, '');
    var newPath = trimmed ? '/' + trimmed + '/' : '/';
    window.location.href = newPath;
}
</script>
