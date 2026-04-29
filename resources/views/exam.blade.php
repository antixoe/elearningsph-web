@extends('layouts.secure', ['title' => ($examSession['exam_title'] ?? 'Secure exam session')])

@section('content')
    @php
        $blocked = (bool) ($examSession['blocked'] ?? false);
        $blockedReason = $examSession['blocked_reason'] ?? ($accessLock->locked_reason ?? null);
    @endphp

    <section class="shell" id="examShell">
        <div class="chrome">
            <div class="chrome-left">
                <span class="traffic" aria-hidden="true"><i></i><i></i><i></i></span>
                <div>
                    <div style="font-weight: 800; letter-spacing: -0.03em;">Exam Browser</div>
                    <div class="helper">Secure kiosk mode for {{ $approvedExamHost }}</div>
                </div>
            </div>

            <div class="chrome-right">
                <span class="pill">Host: {{ $approvedExamHost }}</span>
                <span id="statusChip" class="pill {{ $blocked ? 'pill-danger' : 'pill-success' }}">
                    {{ $blocked ? 'Locked' : 'Active' }}
                </span>
                <span class="pill">Violations: <strong id="violationCount" style="margin-left: 4px;">{{ $examSession['violations'] ?? 0 }}/{{ $violationLimit }}</strong></span>
                <button type="button" class="button button-secondary" id="fullscreenBtn" style="min-height: 38px;">Fullscreen</button>
                <button type="button" class="button button-primary" id="outBtn" style="min-height: 38px;">Out</button>
            </div>
        </div>

        <div class="browser-bar" style="display:flex; align-items:center; gap:12px; padding:14px 18px; border-bottom:1px solid rgba(255,255,255,0.08); background: rgba(7, 13, 24, 0.74);">
            <div class="pill" style="flex: none;">{{ $approvedExamHost }}</div>
            <div class="address-pill" style="flex: 1; min-width: 0; padding: 12px 16px; border-radius: 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); color: var(--soft); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                {{ $approvedExamUrl }}
            </div>
            <button type="button" class="button button-secondary" id="reloadBtn" style="min-height: 40px;">Reload</button>
        </div>

        <div class="frame-wrap">
            <div id="blockedOverlay" class="overlay {{ $blocked ? 'show' : '' }}">
                <div class="overlay-card">
                    <span class="eyebrow" style="border-color: rgba(251, 113, 133, 0.32); background: rgba(251, 113, 133, 0.1); color: #fecdd3;">Session locked</span>
                    <h2 style="margin: 14px 0 10px; font-size: 1.9rem;">Access locked</h2>
                    <p class="lead" style="margin: 0 0 18px;">
                        The session is locked because a restricted action was detected. A teacher code is required to use the Out button.
                    </p>
                    <div class="callout" style="margin-bottom: 18px;">
                        <strong style="display:block; margin-bottom: 8px; color: var(--text);">Reason</strong>
                        <span id="blockedReason">{{ $blockedReason ?? 'Security rule triggered' }}</span>
                    </div>
                </div>
            </div>

            <iframe
                id="examFrame"
                class="frame"
                src="{{ route('exam.proxy') }}"
                title="Exam content"
                sandbox="allow-forms allow-scripts allow-same-origin allow-popups"
                referrerpolicy="no-referrer"
                loading="eager"
            ></iframe>
        </div>

        <div id="teacherOutModal" class="overlay">
            <div class="overlay-card">
                <span class="eyebrow" style="border-color: rgba(251, 146, 60, 0.32); background: rgba(251, 146, 60, 0.1); color: #fdba74;">Teacher code</span>
                <h2 style="margin: 14px 0 10px; font-size: 1.8rem;">Out requires teacher approval</h2>
                <p class="lead" style="margin: 0 0 18px;">
                    Enter the teacher code to release the session and clear the lock.
                </p>

                <form method="POST" action="{{ route('exam.out') }}" class="stack">
                    @csrf
                    <div class="field" style="margin-bottom: 0;">
                        <label class="label" for="teacher_code">Teacher code</label>
                        <input
                            class="input"
                            id="teacher_code"
                            name="teacher_code"
                            type="password"
                            placeholder="Enter teacher code"
                            autocomplete="off"
                            required
                        >
                        @error('teacher_code')
                            <div class="helper" style="color: #fda4af;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="toolbar" style="margin-top: 10px;">
                        <button type="submit" class="button button-primary">Release session</button>
                        <button type="button" class="button button-secondary" id="closeOutBtn">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
(() => {
    const session = @json($examSession);
    const violationLimit = @json($violationLimit);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const shell = document.getElementById('examShell');
    const frame = document.getElementById('examFrame');
    const blockedOverlay = document.getElementById('blockedOverlay');
    const blockedReason = document.getElementById('blockedReason');
    const violationCount = document.getElementById('violationCount');
    const statusChip = document.getElementById('statusChip');
    const fullscreenBtn = document.getElementById('fullscreenBtn');
    const reloadBtn = document.getElementById('reloadBtn');
    const outBtn = document.getElementById('outBtn');
    const teacherOutModal = document.getElementById('teacherOutModal');
    const closeOutBtn = document.getElementById('closeOutBtn');
    const elapsedClock = document.createElement('div');
    const startedAt = new Date(session.started_at || Date.now());
    const initialBlocked = Boolean(session.blocked);
    let blocked = initialBlocked;
    let allowUnload = false;
    const recentSignals = new Map();

    const pad = (value) => String(value).padStart(2, '0');

    elapsedClock.className = 'pill';
    elapsedClock.style.minWidth = '120px';
    elapsedClock.textContent = '00:00:00';
    shell.querySelector('.chrome-right').prepend(elapsedClock);

    const renderElapsed = () => {
        const elapsed = Math.max(0, Date.now() - startedAt.getTime());
        const totalSeconds = Math.floor(elapsed / 1000);
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        elapsedClock.textContent = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
    };

    const setBlocked = (reason) => {
        blocked = true;
        statusChip.className = 'pill pill-danger';
        statusChip.textContent = 'Locked';
        blockedOverlay.classList.add('show');
        blockedReason.textContent = reason || 'Security rule triggered';
        frame.style.pointerEvents = 'none';
    };

    const updateViolations = (count) => {
        violationCount.textContent = `${count}/${violationLimit}`;
    };

    const shouldThrottle = (type) => {
        const now = Date.now();
        const previous = recentSignals.get(type) || 0;
        if (now - previous < 1000) {
            return true;
        }
        recentSignals.set(type, now);
        return false;
    };

    const reportViolation = async (type, detail = '') => {
        if (blocked || shouldThrottle(type)) {
            return;
        }

        try {
            const response = await fetch(@json(route('exam.violation')), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ type, detail }),
            });

            const data = await response.json();
            updateViolations(data.violations ?? 0);

            if (data.blocked) {
                setBlocked(data.blocked_reason || 'Security rule triggered');
            }
        } catch (error) {
            console.error('Violation reporting failed', error);
        }
    };

    const goFullscreen = async () => {
        try {
            if (!document.fullscreenElement) {
                await shell.requestFullscreen();
            }
        } catch (error) {
            console.error('Fullscreen request failed', error);
        }
    };

    const openOutModal = () => {
        teacherOutModal.classList.add('show');
        teacherOutModal.querySelector('input[name="teacher_code"]').focus();
    };

    const closeOutModal = () => {
        teacherOutModal.classList.remove('show');
    };

    reloadBtn.addEventListener('click', () => {
        frame.src = frame.src;
    });

    fullscreenBtn.addEventListener('click', async () => {
        await goFullscreen();
        if (!document.fullscreenElement) {
            reportViolation('fullscreen-denied', 'Fullscreen request was denied');
        }
    });

    outBtn.addEventListener('click', openOutModal);
    closeOutBtn.addEventListener('click', closeOutModal);

    teacherOutModal.addEventListener('click', (event) => {
        if (event.target === teacherOutModal) {
            closeOutModal();
        }
    });

    document.addEventListener('contextmenu', (event) => {
        event.preventDefault();
        reportViolation('contextmenu', 'Right click blocked');
    });

    document.addEventListener('copy', (event) => {
        event.preventDefault();
        reportViolation('copy', 'Copy action blocked');
    });

    document.addEventListener('cut', (event) => {
        event.preventDefault();
        reportViolation('cut', 'Cut action blocked');
    });

    document.addEventListener('paste', (event) => {
        event.preventDefault();
        reportViolation('paste', 'Paste action blocked');
    });

    document.addEventListener('dragstart', (event) => {
        event.preventDefault();
        reportViolation('drag', 'Drag action blocked');
    });

    document.addEventListener('keydown', (event) => {
        const key = event.key.toLowerCase();
        const blockedShortcuts =
            event.key === 'F12' ||
            (event.ctrlKey && event.shiftKey && ['i', 'j', 'c', 'k'].includes(key)) ||
            (event.ctrlKey && ['u', 's', 'p'].includes(key));

        if (blockedShortcuts) {
            event.preventDefault();
            reportViolation('shortcut', `${event.key} shortcut blocked`);
        }

        if (event.key === 'PrintScreen' || event.code === 'PrintScreen') {
            reportViolation('screenshot', 'PrintScreen detected');
        }
    });

    window.addEventListener('blur', () => {
        reportViolation('window-blur', 'Browser window lost focus');
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            reportViolation('tab-switch', 'Tab became hidden');
        }
    });

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && !blocked) {
            reportViolation('fullscreen-exit', 'Fullscreen was exited');
        }
    });

    if (initialBlocked) {
        setBlocked(session.blocked_reason || 'Security rule triggered');
    }

    if (@json($errors->has('teacher_code'))) {
        openOutModal();
    }

    updateViolations(session.violations || 0);
    renderElapsed();
    setInterval(renderElapsed, 1000);

    window.addEventListener('beforeunload', (event) => {
        if (!blocked && !allowUnload) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
})();
</script>
@endpush
