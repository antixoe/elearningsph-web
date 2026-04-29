@extends('layouts.secure', ['title' => 'Secure Exam Browser'])

@section('content')
    <section class="shell">
        <div class="pad">
            <div class="grid grid-2">
                <div>
                    <span class="eyebrow">Secure Exam Browser</span>
                    <h1 class="title">Launch an exam in a locked browser shell.</h1>
                    <p class="lead">
                        Start a monitored session for the approved exam host. The shell blocks common cheating behavior and auto-locks after repeated violations.
                    </p>

                    <div class="toolbar">
                        <span class="pill pill-success">Session-backed blocking</span>
                        <span class="pill">Fullscreen guard</span>
                        <span class="pill">Clipboard and shortcut filters</span>
                    </div>

                    <div style="margin-top: 24px;" class="callout">
                        <strong style="display:block; margin-bottom: 8px; color: var(--text);">Important</strong>
                        Web anti-cheat can deter casual cheating and automatically lock a session, but it cannot fully control the student's device the way a native kiosk app can.
                    </div>
                </div>

                <div class="card">
                    <div class="card-inner">
                        <h2 style="margin: 0 0 6px; font-size: 1.35rem;">Start a secure session</h2>
                        <p class="muted tight" style="margin: 0 0 18px;">
                            This session is locked to the approved exam host.
                        </p>

                        <form method="POST" action="{{ route('exam.start') }}" class="stack">
                            @csrf

                            <div class="field">
                                <label class="label" for="student_name">Student name</label>
                                <input
                                    class="input"
                                    id="student_name"
                                    name="student_name"
                                    type="text"
                                    value="{{ old('student_name') }}"
                                    placeholder="Jane Doe"
                                    required
                                >
                                @error('student_name')
                                    <div class="helper" style="color: #fda4af;">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="field">
                                <label class="label" for="exam_title">Exam title</label>
                                <input
                                    class="input"
                                    id="exam_title"
                                    name="exam_title"
                                    type="text"
                                    value="{{ old('exam_title') }}"
                                    placeholder="Permata Harapan Ku exam"
                                >
                                @error('exam_title')
                                    <div class="helper" style="color: #fda4af;">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="field">
                                <label class="label" for="approved_exam">Approved exam host</label>
                                <input
                                    class="input"
                                    id="approved_exam"
                                    type="text"
                                    value="{{ $approvedExamUrl }}"
                                    readonly
                                >
                                <div class="helper">Students cannot change this destination from the browser shell.</div>
                            </div>

                            <div class="toolbar" style="margin-top: 8px;">
                                <button class="button button-primary" type="submit">Start secure mode</button>
                                @if ($examSession)
                                    <button
                                        class="button button-secondary"
                                        type="button"
                                        onclick="document.getElementById('reset-session-form').submit()"
                                    >
                                        Clear current session
                                    </button>
                                @endif
                            </div>
                        </form>

                        @if ($examSession)
                            <form id="reset-session-form" method="POST" action="{{ route('exam.reset') }}" class="hidden">
                                @csrf
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid grid-3" style="margin-top: 20px;">
                <div class="stat">
                    <div class="stat-value">Approved host</div>
                    <div class="stat-label">{{ $approvedExamHost }} is hard-locked in the session.</div>
                </div>
                <div class="stat">
                    <div class="stat-value">Fullscreen</div>
                    <div class="stat-label">The exam page asks the student to enter fullscreen before starting.</div>
                </div>
                <div class="stat">
                    <div class="stat-value">Auto-lock</div>
                    <div class="stat-label">After 3 violations, the server marks the session blocked until it is reset.</div>
                </div>
            </div>
        </div>
    </section>
@endsection
