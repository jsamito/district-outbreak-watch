@extends('layouts.public')
@section('title', 'Privacy')
@section('content')
    <h1>Privacy</h1>
    <ul>
        <li>No real patient information is stored. @if (config('surveillance.synthetic_data'))All case records in this system are synthetic. @endif</li>
        <li>Case records contain no names, addresses or phone numbers: a pseudonymous reference, sex, birth year and district only.</li>
        <li>Staff see only the area they are responsible for. Every time a case is opened, the action is recorded in an audit log.</li>
        <li>Individual case records cannot be exported. Exports, emails, public pages and the shareable summary contain aggregated counts only.</li>
        <li>We do not use advertising or third-party analytics, and pages load no fonts, maps or scripts from other websites.</li>
        <li>Signed-in sessions end after a period of inactivity, and authenticated pages are not stored by the browser.</li>
    </ul>
    <h2>What would change with real data</h2>
    <p>Real patient data would need a legal basis under Uganda's Data Protection and Privacy Act, 2019, a data-sharing agreement with the Ministry of Health, hosting agreements covering where data are stored, encryption at rest, and retention rules.</p>
@endsection
