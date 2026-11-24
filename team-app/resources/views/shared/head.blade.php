{{-- Theme before paint: the stored choice (light, dark) or the system setting decides html[data-theme] and Bootstrap's data-bs-theme,
     so a dark page never flashes white. Choice is per browser (localStorage "ptm.theme"), changed in the account menu. --}}
<script>(function () { try { var c = localStorage.getItem('ptm.theme') || 'system'; var d = c === 'dark' || (c === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
    var e = document.documentElement; e.setAttribute('data-theme', d ? 'dark' : 'light'); e.setAttribute('data-bs-theme', d ? 'dark' : 'light'); e.setAttribute('data-theme-choice', c); } catch (x) {} })();</script>
<meta name="color-scheme" content="light dark">
{{-- Instant page changes (MDN Speculation Rules API, read 24 Nov 2026): internal links are fetched when the pointer rests on them.
     Excluded: file downloads and exports (GET with work behind them), the storage path, and links marked data-no-prefetch.
     Logging out is a POST form, so it is never fetched. Laravel does not record prefetches as the previous URL. --}}
<script type="speculationrules">{"prefetch":[{"where":{"and":[{"href_matches":"/*"},{"not":{"href_matches":["/attachments/*","/management/export*","/storage/*","/logout"]}},{"not":{"selector_matches":"[data-no-prefetch], [download], [target=_blank]"}}]},"eagerness":"moderate"}]}</script>
