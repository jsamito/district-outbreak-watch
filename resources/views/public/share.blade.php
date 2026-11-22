@extends('layouts.public')
@section('title', 'Share the national summary')
@section('content')
    <h1>Share the national summary</h1>
    <p>This card contains national totals only. It never includes district, facility or case information.</p>
    <figure>
        <img src="{{ route('share.png') }}" alt="Card listing national weekly case counts for each disease for the week starting {{ $week?->format('d F Y') }}" style="max-width:100%;border:1px solid #cfd8df;border-radius:6px" width="1200" height="630">
        <figcaption class="text-muted">Week starting {{ $week?->format('d F Y') }}. @if (config('surveillance.synthetic_data'))Sample data. @endif</figcaption>
    </figure>
    <p><a class="btn btn-primary" href="{{ route('share.png') }}" download="outbreak-watch-national-summary.png"><i class="fa fa-download" aria-hidden="true"></i> Download the image</a>
       <button type="button" class="btn btn-default" id="share-btn" hidden><i class="fa fa-share-alt" aria-hidden="true"></i> Share</button></p>
    <script @nonce>
        (function () { var b = document.getElementById('share-btn'); if (navigator.share) { b.hidden = false; b.addEventListener('click', function () { navigator.share({ title: 'National disease situation', url: @js(route('situation')) }); }); } }());
    </script>
@endsection
