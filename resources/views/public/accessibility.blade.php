@extends('layouts.public')
@section('title', 'Accessibility statement')
@section('content')
    <h1>Accessibility statement</h1>
    <p>We aim to meet the Web Content Accessibility Guidelines (WCAG) 2.2 at level AA. The system works on laptops, tablets and phones.</p>
    <h2>What we do</h2>
    <ul>
        <li>Every chart has a text summary and a data table with the same numbers.</li>
        <li>Charts use a colour palette designed for colour-blind readers and a second cue (marker shape or line style) for every series.</li>
        <li>Charts and maps never rely on colour alone; maps come with a table of the same values.</li>
        <li>You can use every function with a keyboard, and focus is always visible and never hidden behind other elements.</li>
        <li>Controls have a target size of at least 24 by 24 pixels. Sign-in allows password managers and pasting.</li>
        <li>Charts start at zero, show denominators and flag very small numbers.</li>
    </ul>
    <h2>Known limits</h2>
    <p>Interactive maps cannot be fully operated by screen reader; use the table beside each map. If you find a barrier, please tell your administrator so it can be fixed.</p>
@endsection
