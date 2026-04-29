<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class ExamSessionController extends Controller
{
    private const APPROVED_EXAM_URL = 'https://elsph.permataharapanku.sch.id/';

    private const APPROVED_EXAM_HOST = 'elsph.permataharapanku.sch.id';

    private const VIOLATION_LIMIT = 3;

    private const AUTO_LOCK_TYPES = ['copy', 'cut', 'paste', 'screenshot', 'printscreen'];

    public function home(Request $request): View
    {
        return $this->show($request);
    }

    public function start(Request $request): RedirectResponse
    {
        return redirect()->route('exam.show');
    }

    public function show(Request $request): View|RedirectResponse
    {
        $session = $this->ensureSession($request);
        $lock = $this->currentLock($request);

        return view('exam', [
            'examSession' => $session,
            'violationLimit' => self::VIOLATION_LIMIT,
            'approvedExamUrl' => self::APPROVED_EXAM_URL,
            'approvedExamHost' => self::APPROVED_EXAM_HOST,
            'accessLock' => $lock,
        ]);
    }

    public function released(Request $request): View
    {
        return view('released', [
            'approvedExamHost' => self::APPROVED_EXAM_HOST,
        ]);
    }

    public function proxy(Request $request, string $path = ''): Response
    {
        $session = $request->session()->get('exam_session');

        if (! $session) {
            abort(403);
        }

        $targetUrl = $this->buildTargetUrl($path);
        $headers = $this->forwardHeaders($request);
        $options = [];

        if ($request->query()) {
            $options['query'] = $request->query();
        }

        if (! in_array(strtoupper($request->method()), ['GET', 'HEAD'], true)) {
            $options['body'] = $request->getContent();
        }

        try {
            $response = Http::withHeaders($headers)
                ->withOptions(['allow_redirects' => false])
                ->send($request->method(), $targetUrl, $options);
        } catch (\Throwable $exception) {
            return response($this->proxyErrorPage('Unable to connect to the approved exam host.', $exception->getMessage()), 502)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $body = $response->body();
        $contentType = $response->header('Content-Type', 'text/html; charset=UTF-8');
        $dropHeaders = [
            'content-security-policy',
            'content-security-policy-report-only',
            'cross-origin-embedder-policy',
            'cross-origin-opener-policy',
            'cross-origin-resource-policy',
            'permissions-policy',
            'x-frame-options',
            'x-content-type-options',
            'clear-site-data',
        ];

        if (str_contains(strtolower($contentType), 'text/html')) {
            $body = $this->rewriteProxyHtml($body, $path);
        }

        $proxied = response($body, $response->status());

        foreach ($response->headers() as $name => $values) {
            $lowerName = strtolower($name);

            if (in_array($lowerName, array_merge(['content-length', 'content-encoding', 'transfer-encoding', 'connection'], $dropHeaders), true)) {
                continue;
            }

            foreach ((array) $values as $value) {
                if ($lowerName === 'set-cookie') {
                    $cookie = $this->rewriteProxyCookie($value, $request->isSecure());

                    if ($cookie) {
                        $proxied->headers->setCookie($cookie);
                    }

                    continue;
                }

                $proxied->header($name, $value);
            }
        }

        $proxied->header('Content-Type', $contentType);

        $location = $response->header('Location');
        if ($location && str_starts_with($location, self::APPROVED_EXAM_URL)) {
            $proxied->header('Location', route('exam.proxy', ['path' => ltrim(substr($location, strlen(self::APPROVED_EXAM_URL)), '/')]));
        }

        return $proxied;
    }

    public function violation(Request $request): JsonResponse
    {
        $session = $request->session()->get('exam_session');

        if (! $session) {
            return response()->json([
                'message' => 'No active exam session.',
            ], 409);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'detail' => ['nullable', 'string', 'max:255'],
        ]);

        $type = strtolower($validated['type']);
        $detail = trim($validated['detail'] ?? '');

        if (! ($session['blocked'] ?? false)) {
            $session['violations'] = ($session['violations'] ?? 0) + 1;
            $session['last_violation_at'] = now()->toIso8601String();

            $events = $session['events'] ?? [];
            $events[] = [
                'type' => $type,
                'detail' => $detail,
                'at' => now()->toIso8601String(),
            ];

            $session['events'] = array_slice($events, -10);

            if (in_array($type, self::AUTO_LOCK_TYPES, true)) {
                $session['blocked'] = true;
                $session['blocked_reason'] = $this->violationLabel($type);
                $this->persistLock($request, $type, $session['blocked_reason'], $detail);
            } elseif ($session['violations'] >= self::VIOLATION_LIMIT) {
                $session['blocked'] = true;
                $session['blocked_reason'] = $this->violationLabel($type);
                $this->persistLock($request, $type, $session['blocked_reason'], $detail);
            }

            $request->session()->put('exam_session', $session);
        }

        return response()->json([
            'violations' => $session['violations'] ?? 0,
            'blocked' => $session['blocked'] ?? false,
            'blocked_reason' => $session['blocked_reason'] ?? null,
            'limit' => self::VIOLATION_LIMIT,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget('exam_session');

        return redirect()->route('home');
    }

    public function teacherOut(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'teacher_code' => ['required', 'string', 'max:100'],
        ]);

        $expectedCode = (string) config('exam.teacher_out_code', '');
        $providedCode = trim($validated['teacher_code']);

        if ($expectedCode === '' || ! hash_equals($expectedCode, $providedCode)) {
            return back()
                ->withErrors(['teacher_code' => 'Invalid teacher code.'])
                ->withInput();
        }

        $fingerprint = $this->clientFingerprint($request);
        DB::table('exam_locks')->where('fingerprint', $fingerprint)->delete();

        $request->session()->forget('exam_session');

        return redirect()
            ->route('exam.released')
            ->with('status', 'Session released by teacher.');
    }

    private function buildTargetUrl(string $path): string
    {
        $base = rtrim(self::APPROVED_EXAM_URL, '/');
        $path = ltrim($path, '/');

        return $path === '' ? $base . '/' : $base . '/' . $path;
    }

    private function forwardHeaders(Request $request): array
    {
        $allowed = [
            'accept',
            'accept-language',
            'content-type',
            'cookie',
            'origin',
            'referer',
            'user-agent',
            'x-requested-with',
            'sec-ch-ua',
            'sec-ch-ua-mobile',
            'sec-ch-ua-platform',
            'sec-fetch-dest',
            'sec-fetch-mode',
            'sec-fetch-site',
        ];

        $headers = [
            'host' => self::APPROVED_EXAM_HOST,
        ];

        foreach ($allowed as $name) {
            if ($request->headers->has($name)) {
                $headers[$name] = $request->header($name);
            }
        }

        return $headers;
    }

    private function rewriteProxyHtml(string $html, string $path): string
    {
        $proxyBase = rtrim(route('exam.proxy', ['path' => ltrim($path, '/')]), '/');
        $proxyBase = $proxyBase === route('exam.proxy') ? route('exam.proxy') . '/' : $proxyBase . '/';

        if (! preg_match('/<base\s/i', $html)) {
            $html = preg_replace('/<head([^>]*)>/i', '<head$1><base href="' . e($proxyBase) . '">', $html, 1) ?? $html;
        }

        $host = rtrim(self::APPROVED_EXAM_URL, '/');
        $html = str_replace($host . '/', $proxyBase, $html);

        return $html;
    }

    private function rewriteProxyCookie(string $cookieHeader, bool $secureRequest): ?Cookie
    {
        $segments = array_map('trim', explode(';', $cookieHeader));
        $nameValue = array_shift($segments);

        if (! $nameValue || ! str_contains($nameValue, '=')) {
            return null;
        }

        [$name, $value] = explode('=', $nameValue, 2);
        $options = [
            'path' => '/',
            'domain' => null,
            'secure' => $secureRequest,
            'httponly' => true,
            'samesite' => null,
            'raw' => false,
        ];
        $expires = null;

        foreach ($segments as $segment) {
            if (str_contains($segment, '=')) {
                [$key, $segmentValue] = array_map('trim', explode('=', $segment, 2));
                $lowerKey = strtolower($key);

                if ($lowerKey === 'domain') {
                    continue;
                }

                if ($lowerKey === 'path') {
                    $options['path'] = $segmentValue ?: '/';
                    continue;
                }

                if ($lowerKey === 'expires') {
                    $expires = $segmentValue;
                    continue;
                }

                if ($lowerKey === 'samesite') {
                    $segmentValue = match (strtolower($segmentValue)) {
                        'strict' => 'Strict',
                        'none' => $secureRequest ? 'None' : 'Lax',
                        default => 'Lax',
                    };

                    $options['samesite'] = $segmentValue;
                    continue;
                }

                continue;
            }

            $flag = strtolower($segment);
            if ($flag === 'secure') {
                $options['secure'] = $secureRequest;
                continue;
            }

            if ($flag === 'httponly') {
                $options['httponly'] = true;
            }
        }

        $cookie = Cookie::create(
            trim($name),
            trim($value),
            0,
            $options['path'],
            null,
            $options['secure'],
            $options['httponly'],
            false,
            $options['samesite']
        );

        if ($expires) {
            $cookie = $cookie->withExpires(new \DateTimeImmutable($expires));
        }

        return $cookie;
    }

    private function proxyErrorPage(string $message, string $detail = ''): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Exam unavailable</title>'
            . '<style>body{margin:0;font-family:system-ui,sans-serif;background:#120804;color:#fff;display:grid;place-items:center;min-height:100vh;padding:24px}'
            . '.card{max-width:760px;background:#22140c;border:1px solid rgba(251,146,60,.22);border-radius:24px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.35)}'
            . 'h1{margin:0 0 12px;font-size:28px}p{margin:0 0 10px;line-height:1.6;color:#f4e4d6}.small{opacity:.8;font-size:14px;word-break:break-word}</style>'
            . '</head><body><div class="card"><h1>' . e($message) . '</h1><p>The exam host is refusing the embedded connection or is currently unreachable.</p>'
            . '<p class="small">' . e($detail) . '</p></div></body></html>';
    }

    private function ensureSession(Request $request): array
    {
        $session = $request->session()->get('exam_session');
        $lock = $this->currentLock($request);

        if ($session) {
            if ($lock) {
                $session['blocked'] = true;
                $session['blocked_reason'] = $lock->locked_reason;
                $request->session()->put('exam_session', $session);
                return $session;
            }

            return $session;
        }

        $session = [
            'id' => (string) Str::uuid(),
            'student_name' => 'Candidate',
            'exam_url' => self::APPROVED_EXAM_URL,
            'exam_title' => 'Permata Harapan Ku exam',
            'approved_exam_host' => self::APPROVED_EXAM_HOST,
            'violations' => 0,
            'blocked' => (bool) $lock,
            'blocked_reason' => $lock?->locked_reason,
            'started_at' => now()->toIso8601String(),
            'last_violation_at' => null,
            'events' => [],
        ];

        $request->session()->put('exam_session', $session);

        return $session;
    }

    private function currentLock(Request $request): ?object
    {
        return DB::table('exam_locks')
            ->where('fingerprint', $this->clientFingerprint($request))
            ->first();
    }

    private function persistLock(Request $request, string $type, string $reason, string $detail = ''): void
    {
        DB::table('exam_locks')->updateOrInsert(
            ['fingerprint' => $this->clientFingerprint($request)],
            [
                'last_violation_type' => $type,
                'locked_reason' => $reason,
                'locked_at' => now(),
                'metadata' => json_encode([
                    'detail' => $detail,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function clientFingerprint(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->ip() ?? 'unknown',
            $request->userAgent() ?? 'unknown',
            self::APPROVED_EXAM_HOST,
        ]));
    }

    private function violationLabel(string $type): string
    {
        return match ($type) {
            'copy' => 'Copy attempt',
            'cut' => 'Cut attempt',
            'paste' => 'Paste attempt',
            'screenshot', 'printscreen' => 'Screenshot attempt',
            default => Str::headline(str_replace('-', ' ', $type)),
        };
    }
}
