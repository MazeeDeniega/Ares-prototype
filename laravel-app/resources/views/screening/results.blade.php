<!DOCTYPE html>
<html>
<head>
    <title>Results — {{ $job->title }}</title>
</head>
<body>
    <script>
    window.__LARAVEL__ = {
        csrf: "{{ csrf_token() }}",
        job: @json($job),
        results: @json($results),
        pref: @json($pref)
    };
    </script>
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
    <div id="app"></div>

    {{-- Old server-rendered table, kept for reference. The React Results
         page (RankingTable component) now replaces this. --}}
    {{--
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        ...
    </style>
    <p><a href="/screening/{{ $job->id }}">&larr; Back to Applicants</a></p>
    <h2>Ranking Results — {{ $job->title }}</h2>
    ... (full original table markup) ...
    --}}
</body>
</html>