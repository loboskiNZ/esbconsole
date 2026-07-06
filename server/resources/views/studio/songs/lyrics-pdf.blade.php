<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $songTitle }} — Lyrics</title>
    <style>
        @page {
            margin: 2cm 2.2cm;
        }

        body {
            margin: 0;
            color: #000;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 12pt;
            line-height: 1.45;
        }

        h1 {
            margin: 0 0 0.35rem;
            font-size: 20pt;
            font-weight: 700;
            line-height: 1.15;
        }

        .lyrics-pdf__meta {
            margin: 0 0 1.5rem;
            color: #333;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10pt;
        }

        h2 {
            margin: 1.35rem 0 0.55rem;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11pt;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .lyrics-pdf__line {
            margin: 0;
            white-space: pre-wrap;
        }

        .lyrics-pdf__blank {
            height: 0.8rem;
        }
    </style>
</head>
<body>
    <h1>{{ $songTitle }}</h1>

    @if ($metadata)
        <p class="lyrics-pdf__meta">{{ $metadata }}</p>
    @endif

    @foreach ($sections as $section)
        @if (! empty($section['heading']))
            <h2>{{ $section['heading'] }}</h2>
        @endif

        @foreach ($section['lines'] as $line)
            @if ($line === '')
                <div class="lyrics-pdf__blank" aria-hidden="true"></div>
            @else
                <p class="lyrics-pdf__line">{{ $line }}</p>
            @endif
        @endforeach
    @endforeach
</body>
</html>
