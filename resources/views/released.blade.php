@extends('layouts.secure', ['title' => 'Session Released'])

@section('content')
    <section class="shell">
        <div class="pad">
            <div class="card">
                <div class="card-inner">
                    <span class="eyebrow">Teacher Release</span>
                    <h1 class="title" style="font-size: clamp(2rem, 6vw, 4.25rem);">Session released</h1>
                    <p class="lead">
                        The exam session was closed with the teacher code for <strong>{{ $approvedExamHost }}</strong>.
                    </p>

                    <div class="callout" style="margin-top: 22px;">
                        <strong style="display:block; margin-bottom: 8px; color: var(--text);">Next step</strong>
                        Close this window or return to the exam browser if another session needs to be started.
                    </div>

                    <div class="toolbar">
                        <a href="{{ route('home') }}" class="button button-primary">Return to browser</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
